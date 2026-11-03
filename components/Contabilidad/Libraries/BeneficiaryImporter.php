<?php

namespace Components\Contabilidad\Libraries;

/**
 * Importador de Beneficiarios/Terceros desde CSV (§5.8). Cabeceras esperadas
 * (delimitador `;`, con alias tolerantes):
 *   nombre;tipo_id;numero_id;email;telefono;ciudad;direccion;contacto;notas;banco;tipo_cuenta;numero_cuenta
 * Normaliza cabeceras (minúsculas/sin acentos), mapea por alias y devuelve filas
 * listas para insertar/actualizar. La deduplicación (id_type+id_number) y el modo
 * add/upsert los resuelve el controlador.
 */
class BeneficiaryImporter
{
    /** Campo → alias aceptados (ya normalizados). */
    private const ALIASES = [
        'name'                => ['nombre', 'razonsocial', 'nombrerazonsocial', 'tercero', 'beneficiario', 'name'],
        'id_type'             => ['tipoid', 'tipodocumento', 'tipodeid', 'tipo', 'idtype'],
        'id_number'           => ['numeroid', 'numerodocumento', 'numerodeid', 'documento', 'nit', 'cedula', 'idnumber'],
        'email'               => ['email', 'correo', 'correoelectronico', 'mail'],
        'phone'               => ['telefono', 'celular', 'phone', 'movil'],
        'city'                => ['ciudad', 'city', 'municipio'],
        'address'             => ['direccion', 'address', 'domicilio'],
        'contact_name'        => ['contacto', 'nombrecontacto', 'contactname', 'contact'],
        'notes'               => ['notas', 'observaciones', 'notes'],
        'bank_name'           => ['banco', 'entidadbancaria', 'bankname'],
        'bank_account_type'   => ['tipocuenta', 'tipodecuenta', 'accounttype'],
        'bank_account_number' => ['numerocuenta', 'numerodecuenta', 'cuenta', 'accountnumber'],
    ];

    /** Cabecera canónica de la plantilla descargable (§5.8). */
    public const TEMPLATE_HEADERS = [
        'nombre', 'tipo_id', 'numero_id', 'email', 'telefono', 'ciudad',
        'direccion', 'contacto', 'notas', 'banco', 'tipo_cuenta', 'numero_cuenta',
    ];

    private const ID_TYPES      = ['NIT', 'CC', 'CE', 'PAS', 'TI', 'RUT'];
    private const ACCOUNT_TYPES = ['Ahorros', 'Corriente'];

    /**
     * Lee el CSV y devuelve filas normalizadas (sin company_id; lo pone el controlador).
     *
     * @return array{rows:array<int,array<string,mixed>>, skipped:int}
     */
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
            $name = $get('name');
            if ($name === '') {
                $skipped++;
                continue; // sin nombre → se salta
            }

            $rows[] = [
                'name'                => mb_substr($name, 0, 200),
                'id_type'             => $this->normalizeIdType($get('id_type')),
                'id_number'           => mb_substr($get('id_number'), 0, 50),
                'email'               => $get('email'),
                'phone'               => $get('phone'),
                'city'                => $get('city'),
                'address'             => $get('address'),
                'contact_name'        => $get('contact_name'),
                'notes'               => $get('notes') ?: null,
                'bank_name'           => $get('bank_name'),
                'bank_account_type'   => $this->normalizeAccountType($get('bank_account_type')),
                'bank_account_number' => $get('bank_account_number'),
            ];
        }
        fclose($fh);

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    private function normalizeIdType(string $s): string
    {
        $s = strtoupper(trim($s));
        foreach (self::ID_TYPES as $t) {
            if ($s === $t) {
                return $t;
            }
        }

        return $s === '' ? 'NIT' : $s;
    }

    private function normalizeAccountType(string $s): string
    {
        $s = trim(mb_strtolower($s));
        foreach (self::ACCOUNT_TYPES as $t) {
            if ($s === mb_strtolower($t)) {
                return $t;
            }
        }

        return 'Ahorros';
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u', 'à' => 'a', 'è' => 'e']);

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
