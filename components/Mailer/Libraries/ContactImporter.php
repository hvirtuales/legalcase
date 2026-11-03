<?php

namespace Components\Mailer\Libraries;

/**
 * Importador de contactos desde CSV (§bases de datos). Cabeceras con alias
 * tolerantes (delimitador `;` o `,`, autodetectado):
 *   email;nombre;entidad
 * Convierte el archivo a UTF-8 (Excel suele guardar en Windows-1252/ANSI, lo que
 * rompe las tildes), valida sintaxis/dominio desechable (anti-spam), normaliza a
 * minúsculas y deduplica dentro del archivo. La deduplicación contra la lista
 * existente la hace el controlador. Devuelve filas listas + estadísticas.
 */
class ContactImporter
{
    private const ALIASES = [
        'email'  => ['email', 'correo', 'correoelectronico', 'mail', 'e-mail'],
        'name'   => ['nombre', 'name', 'nombrecompleto', 'contacto'],
        'entity' => ['entidad', 'entity', 'organizacion', 'organización', 'empresa', 'compania', 'compañia', 'institucion'],
    ];

    public const TEMPLATE_HEADERS = ['email', 'nombre', 'entidad'];

    /**
     * `rows`: filas válidas. `invalid`: filas que no pasaron la validación de
     * correo (con `reason`), para revisión/edición. `duplicates`: repetidos en
     * el archivo (descartados).
     *
     * @return array{rows:array<int,array{email:string,name:?string,entity:?string}>, invalid:array<int,array{email:string,name:?string,entity:?string,reason:string}>, duplicates:int}
     */
    public function fromCsv(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return ['rows' => [], 'invalid' => [], 'duplicates' => 0, 'roles' => []];
        }
        $content = $this->toUtf8($content);
        $lines   = preg_split('/\r\n|\r|\n/', $content) ?: [];
        // Descarta líneas totalmente vacías conservando el orden.
        $lines = array_values(array_filter($lines, static fn ($l) => trim((string) $l) !== ''));
        if (!$lines) {
            return ['rows' => [], 'invalid' => [], 'duplicates' => 0, 'roles' => []];
        }

        $delim  = $this->detectDelimiter($lines[0]);
        $header = str_getcsv($lines[0], $delim);
        $norm   = array_map([$this, 'normalizeHeader'], $header);
        $map    = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $norm, true);
                if ($idx !== false) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        // Con cabecera reconocible se saltan las cabeceras; si no, col 0=email,1=nombre,2=entidad.
        if (isset($map['email'])) {
            $dataLines = array_slice($lines, 1);
        } else {
            $map       = ['email' => 0, 'name' => 1, 'entity' => 2];
            $dataLines = $lines;
        }

        $validator   = new EmailValidator();
        $rows        = [];
        $invalidRows = [];
        $seen        = [];
        $duplicates  = 0;
        // Las direcciones de área (`info@`, `ventas@`) se cuentan aparte: no
        // se rechazan —puede haber razones para escribirles— pero quien
        // importa merece saber cuántas entraron.
        $roles       = [];
        foreach ($dataLines as $line) {
            $r      = str_getcsv($line, $delim);
            $email  = strtolower(trim((string) ($r[$map['email']] ?? '')));
            $name   = isset($map['name']) ? trim((string) ($r[$map['name']] ?? '')) : '';
            $entity = isset($map['entity']) ? trim((string) ($r[$map['entity']] ?? '')) : '';
            // Salta una fila de cabecera que se haya colado.
            if ($email === 'email' || $email === 'correo') {
                continue;
            }
            $check = $validator->check($email, true); // incluye verificación de dominio (MX)
            if (!$check['ok']) {
                $invalidRows[] = [
                    'email'  => mb_substr($email, 0, 190),
                    'name'   => $name !== '' ? mb_substr($name, 0, 190) : null,
                    'entity' => $entity !== '' ? mb_substr($entity, 0, 190) : null,
                    'reason' => $check['reason'],
                ];
                continue;
            }
            if (isset($seen[$email])) {
                $duplicates++;
                continue;
            }
            $seen[$email] = true;
            if (!empty($check['role']) && count($roles) < 50) {
                $roles[] = $email;
            }
            $rows[]       = [
                'email'  => mb_substr($email, 0, 190),
                'name'   => $name !== '' ? mb_substr($name, 0, 190) : null,
                'entity' => $entity !== '' ? mb_substr($entity, 0, 190) : null,
            ];
        }

        return ['rows' => $rows, 'invalid' => $invalidRows, 'duplicates' => $duplicates, 'roles' => $roles];
    }

    /** Normaliza el contenido a UTF-8 (quita BOM; convierte desde Windows-1252/Latin-1). */
    private function toUtf8(string $s): string
    {
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s); // BOM UTF-8
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        $enc = mb_detect_encoding($s, ['UTF-8', 'Windows-1252', 'ISO-8859-15', 'ISO-8859-1'], true) ?: 'Windows-1252';

        return mb_convert_encoding($s, 'UTF-8', $enc);
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]/', '', $h);
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') >= substr_count($line, ',') ? ';' : ',';
    }
}
