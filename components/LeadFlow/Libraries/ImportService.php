<?php

namespace Components\LeadFlow\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;

/**
 * Importación de leads desde CSV.
 *
 * Detecta solo el separador (`;` o `,`) y la codificación (UTF-8 o el ANSI que
 * escribe Excel en Windows), acepta cabeceras en español o en inglés, resuelve
 * servicio, fuente y campaña **por nombre** dentro de la firma, y pasa cada fila
 * por `LeadService::create()` — las mismas reglas de plan, duplicados y scoring
 * que el alta manual. Una fila mala no aborta el archivo: se informa su línea.
 */
class ImportService
{
    public const MAX_ROWS  = 5000;
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const COLUMNS = ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'whatsapp', 'country', 'city',
        'preferred_language', 'service', 'source', 'campaign', 'potential_value', 'summary'];

    private const ALIASES = [
        'nombre' => 'first_name', 'nombres' => 'first_name', 'primer_nombre' => 'first_name',
        'segundo_nombre' => 'middle_name', 'apellido' => 'last_name', 'apellidos' => 'last_name',
        'correo' => 'email', 'e-mail' => 'email', 'telefono' => 'phone', 'teléfono' => 'phone', 'celular' => 'phone',
        'pais' => 'country', 'país' => 'country', 'ciudad' => 'city', 'idioma' => 'preferred_language',
        'language' => 'preferred_language', 'servicio' => 'service', 'fuente' => 'source', 'campana' => 'campaign',
        'campaña' => 'campaign', 'valor' => 'potential_value', 'valor_potencial' => 'potential_value',
        'resumen' => 'summary', 'notas' => 'summary', 'notes' => 'summary',
    ];

    public static function template(): string
    {
        $rows = [
            implode(';', self::COLUMNS),
            'María;José;López;maria.lopez@example.com;+1 305 555 0199;+1 305 555 0199;US;Miami;es;'
                . lang('Lfl.svcFamilyVisa') . ';' . lang('Lfl.srcMeta') . ';;3500;' . lang('Lfl.impExampleSummary'),
        ];

        return "\xEF\xBB\xBF" . implode("\r\n", $rows) . "\r\n";
    }

    /**
     * @param array $opts skip_duplicates, service_id, source_id, campaign_id, assigned_user_id
     *
     * @return array{ok:bool,message:string,created:int,duplicates:int,errors:array<int,string>}
     */
    public function process(int $companyId, ?UploadedFile $file, array $opts = []): array
    {
        $out = ['ok' => false, 'message' => '', 'created' => 0, 'duplicates' => 0, 'errors' => []];

        if (! $file || ! $file->isValid()) {
            $out['message'] = lang('Lfl.errImportFile');

            return $out;
        }
        if ($file->getSize() > self::MAX_BYTES) {
            $out['message'] = lang('Lfl.errImportSize');

            return $out;
        }

        $content = (string) file_get_contents($file->getTempName());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (! $lines || count($lines) < 2) {
            $out['message'] = lang('Lfl.errImportEmpty');

            return $out;
        }

        $sep     = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map(static function ($h) {
            $h = mb_strtolower(trim($h));
            $h = str_replace(' ', '_', $h);

            return self::ALIASES[$h] ?? $h;
        }, str_getcsv($lines[0], $sep));

        if (! in_array('first_name', $headers, true) || ! in_array('last_name', $headers, true)) {
            $out['message'] = lang('Lfl.errImportHeaders');

            return $out;
        }

        $byName = static function (array $options): array {
            $map = [];
            foreach ($options as $id => $name) {
                $map[mb_strtolower(trim((string) $name))] = (int) $id;
            }

            return $map;
        };
        $services  = $byName((new ServiceModel())->options($companyId, false));
        $sources   = $byName((new SourceModel())->options($companyId, false));
        $campaigns = $byName((new CampaignModel())->options($companyId, false));

        $leads = new LeadService();
        $rows  = array_slice($lines, 1, self::MAX_ROWS);

        foreach ($rows as $i => $line) {
            $lineNo = $i + 2;
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $sep);
            $row   = [];
            foreach ($headers as $k => $h) {
                if (in_array($h, self::COLUMNS, true)) {
                    $row[$h] = trim((string) ($cells[$k] ?? ''));
                }
            }

            $data = array_intersect_key($row, array_flip(['first_name', 'middle_name', 'last_name', 'email', 'phone',
                'whatsapp', 'country', 'city', 'preferred_language', 'potential_value', 'summary']));
            $data['service_id']  = $services[mb_strtolower($row['service'] ?? '')] ?? ($opts['service_id'] ?? null);
            $data['source_id']   = $sources[mb_strtolower($row['source'] ?? '')] ?? ($opts['source_id'] ?? null);
            $data['campaign_id'] = $campaigns[mb_strtolower($row['campaign'] ?? '')] ?? ($opts['campaign_id'] ?? null);
            if (! empty($opts['assigned_user_id'])) {
                $data['assigned_user_id'] = (int) $opts['assigned_user_id'];
            }
            if (isset($data['country'])) {
                $data['country'] = strtoupper($data['country']);
            }

            $res = $leads->create($companyId, $data, [
                'channel' => 'import',
                'force'   => empty($opts['skip_duplicates']),
            ]);

            if ($res['ok']) {
                $out['created']++;
            } elseif (! empty($res['duplicates'])) {
                $out['duplicates']++;
            } else {
                $out['errors'][$lineNo] = $res['message'];
                // Plan agotado o servicio desactivado: el resto de filas fallaría igual.
                if ((new PlanGuard())->blockReason($companyId)) {
                    break;
                }
            }
        }

        $out['ok']      = true;
        $out['message'] = lang('Lfl.okImported', [$out['created'], $out['duplicates'], count($out['errors'])]);

        return $out;
    }
}
