<?php

namespace Components\Contabilidad\Libraries;

/**
 * Importador del Plan Único de Cuentas (PUC) desde CSV (§5.10). Cabeceras
 * esperadas (delimitador `;`, con alias tolerantes):
 *   codigo;nombre;tipo;naturaleza;nivel;codigo_padre;descripcion
 * El PUC es GLOBAL (sin company_id). La deduplicación por `code` y el modo
 * add/upsert los resuelve el controlador.
 */
class PucImporter
{
    /** Campo → alias aceptados (ya normalizados). */
    private const ALIASES = [
        'code'        => ['codigo', 'cuenta', 'code', 'cuentapuc'],
        'name'        => ['nombre', 'descripcioncuenta', 'name', 'denominacion'],
        'type'        => ['tipo', 'clase', 'type'],
        'nature'      => ['naturaleza', 'nature'],
        'level'       => ['nivel', 'level'],
        'parent_code' => ['codigopadre', 'padre', 'parentcode', 'cuentapadre'],
        'description' => ['descripcion', 'detalle', 'description', 'notas'],
    ];

    /** Cabecera canónica de la plantilla descargable. */
    public const TEMPLATE_HEADERS = ['codigo', 'nombre', 'tipo', 'naturaleza', 'nivel', 'codigo_padre', 'descripcion'];

    private const TYPES     = ['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto', 'costo'];
    private const NATURES   = ['debito', 'credito'];

    /** @return array{rows:array<int,array<string,mixed>>, skipped:int} */
    public function fromCsv(string $path): array
    {
        $delim = $this->detectDelimiter($path);
        $fh    = @fopen($path, 'r');
        if (!$fh) {
            return ['rows' => [], 'skipped' => 0];
        }
        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            fclose($fh);

            return ['rows' => [], 'skipped' => 0];
        }
        $norm = array_map([$this, 'normalizeHeader'], $header);
        $map  = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $norm, true);
                if ($idx !== false) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        $rows    = [];
        $skipped = 0;
        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            if (count(array_filter($r, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // fila vacía
            }
            $get  = static fn (string $f) => isset($map[$f]) ? trim((string) ($r[$map[$f]] ?? '')) : '';
            $code = preg_replace('/\s+/', '', $get('code'));
            $name = $get('name');
            if ($code === '' || $name === '') {
                $skipped++;
                continue; // sin código o nombre → se salta
            }

            $level = (int) preg_replace('/\D/', '', $get('level'));
            if ($level < 1) {
                $level = min(4, max(1, (int) ceil(strlen($code) / 2))); // inferir por longitud del código
            }

            $rows[] = [
                'code'        => mb_substr($code, 0, 20),
                'name'        => mb_substr($name, 0, 200),
                'type'        => $this->normalizeType($get('type')),
                'nature'      => $this->normalizeNature($get('nature'), $get('type')),
                'level'       => $level,
                'parent_code' => preg_replace('/\s+/', '', $get('parent_code')) ?: null,
                'description' => $get('description') ?: null,
                'is_active'   => 1,
            ];
        }
        fclose($fh);

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    private function normalizeType(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        foreach (self::TYPES as $t) {
            if ($s === $t) {
                return $t;
            }
        }
        // Sinónimos frecuentes.
        return match (true) {
            str_starts_with($s, 'ingreso')          => 'ingreso',
            str_starts_with($s, 'gasto')            => 'gasto',
            str_starts_with($s, 'costo')            => 'costo',
            str_starts_with($s, 'pasivo')           => 'pasivo',
            str_starts_with($s, 'patrimonio')       => 'patrimonio',
            default                                 => 'activo',
        };
    }

    private function normalizeNature(string $s, string $type): string
    {
        $s = trim(mb_strtolower($s));
        $s = strtr($s, ['é' => 'e', 'í' => 'i', 'ó' => 'o']);
        if (in_array($s, self::NATURES, true)) {
            return $s;
        }
        if (str_starts_with($s, 'deb') || $s === 'db' || $s === 'd') {
            return 'debito';
        }
        if (str_starts_with($s, 'cred') || str_starts_with($s, 'cr') || $s === 'c') {
            return 'credito';
        }
        // Inferir por tipo si no viene: activo/gasto/costo → débito; pasivo/patrimonio/ingreso → crédito.
        $t = $this->normalizeType($type);

        return in_array($t, ['pasivo', 'patrimonio', 'ingreso'], true) ? 'credito' : 'debito';
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);

        return preg_replace('/[^a-z0-9]/', '', $h);
    }

    private function detectDelimiter(string $path): string
    {
        $line = '';
        $fh   = @fopen($path, 'r');
        if ($fh) {
            $line = (string) fgets($fh);
            fclose($fh);
        }

        return substr_count($line, ';') >= substr_count($line, ',') ? ';' : ',';
    }
}
