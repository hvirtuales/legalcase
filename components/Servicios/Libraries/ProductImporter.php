<?php

namespace Components\Servicios\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\Servicios\Models\CategoryModel;
use Components\Servicios\Models\ProductModel;
use Components\Servicios\Models\ProductPriceModel;

/**
 * Importación masiva de productos desde una plantilla CSV.
 *
 * Decisiones que conviene conocer:
 *
 * - **Se actualiza por código** (único por empresa): volver a subir el archivo
 *   corrige en vez de duplicar, que es como se usa esto en la práctica.
 * - **La categoría va por id** y tiene que existir en la empresa; si no, la fila
 *   se rechaza. Nunca se inventa una categoría ni se deja el producto colgado de
 *   la de otra empresa.
 * - **Fila con error no aborta el archivo**: se importa lo bueno y se devuelve el
 *   detalle de lo que falló, con su número de línea.
 * - CSV y no Excel: no añade dependencias y Excel/Sheets lo abren igual. Se
 *   detectan solos el separador (`;` o `,`) y la codificación (UTF-8 o la que
 *   escribe Excel en Windows).
 */
class ProductImporter
{
    /** Columnas de la plantilla, en orden. */
    public const COLUMNS = [
        'codigo', 'nombre', 'descripcion', 'categoria_id', 'valor_unitario', 'cantidad',
        'tipo_descuento', 'descuento', 'impuesto', 'valor1', 'valor2', 'valor3', 'enlace', 'activo',
    ];

    /** Plantilla lista para descargar (con BOM para que Excel respete los acentos). */
    public function template(): string
    {
        $rows = [
            self::COLUMNS,
            ['SRV-010', 'Consultoría estratégica', 'Sesión de 4 horas', '', '1200000', '1', '%', '0', '19', '1100000', '1000000', '', '', '1'],
            ['PRD-020', 'Licencia anual', 'Por usuario', '', '450000', '1', '%', '10', '19', '', '', '', 'https://…', '1'],
        ];

        $out = "\xEF\xBB\xBF";

        foreach ($rows as $row) {
            $out .= implode(';', array_map(static fn ($v) => str_contains((string) $v, ';') ? '"' . $v . '"' : $v, $row)) . "\r\n";
        }

        return $out;
    }

    /**
     * Procesa el archivo.
     *
     * @return array{created:int,updated:int,skipped:int,errors:list<string>}
     */
    public function import(UploadedFile $file, int $companyId): array
    {
        $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $raw = file_get_contents($file->getTempName());
        if ($raw === false || trim($raw) === '') {
            $res['errors'][] = lang('Quo.impErrEmpty');

            return $res;
        }

        // Excel en Windows suele guardar en ANSI: se normaliza a UTF-8.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $delim = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';

        // La cabecera manda: así el orden de las columnas puede cambiar.
        $head = array_map(
            static fn ($h) => strtolower(trim(str_replace(['"', "\xC2\xA0"], '', $h))),
            str_getcsv(array_shift($lines), $delim)
        );
        if (!in_array('codigo', $head, true) || !in_array('nombre', $head, true)) {
            $res['errors'][] = lang('Quo.impErrHeader');

            return $res;
        }

        $products   = new ProductModel();
        $prices     = new ProductPriceModel();
        $categories = array_keys((new CategoryModel())->mapFor($companyId));
        $line       = 1;

        foreach ($lines as $raw) {
            $line++;
            if (trim($raw) === '') {
                continue;
            }

            $cols = str_getcsv($raw, $delim);
            $row  = [];

            foreach ($head as $i => $name) {
                $row[$name] = trim((string) ($cols[$i] ?? ''));
            }

            $code = $row['codigo'] ?? '';
            $name = $row['nombre'] ?? '';

            if ($code === '' || $name === '') {
                $res['errors'][] = lang('Quo.impErrRequired', [$line]);
                $res['skipped']++;

                continue;
            }

            $categoryId = (int) ($row['categoria_id'] ?? 0);
            if ($categoryId && !in_array($categoryId, $categories, true)) {
                $res['errors'][] = lang('Quo.impErrCategory', [$line, $categoryId]);
                $res['skipped']++;

                continue;
            }

            $data = [
                'company_id'       => $companyId,
                'category_id'      => $categoryId ?: null,
                'code'             => mb_substr($code, 0, 60),
                'name'             => mb_substr($name, 0, 200),
                'description'      => (string) ($row['descripcion'] ?? ''),
                'unit_price'       => $this->num($row['valor_unitario'] ?? 0),
                'default_quantity' => $this->num($row['cantidad'] ?? 1) ?: 1,
                'discount_type'    => ($row['tipo_descuento'] ?? '%') === '$' ? '$' : '%',
                'discount_value'   => $this->num($row['descuento'] ?? 0),
                'tax_percent'      => $this->num($row['impuesto'] ?? 0),
                'details_url'      => mb_substr((string) ($row['enlace'] ?? ''), 0, 255),
                'is_active'        => in_array(strtolower((string) ($row['activo'] ?? '1')), ['0', 'no', 'false', 'inactivo'], true) ? 0 : 1,
            ];

            $existing = $products->where('company_id', $companyId)->where('code', $data['code'])->first();

            if ($existing) {
                $products->skipValidation(true)->update((int) $existing['id'], $data);
                $products->skipValidation(false);
                $id = (int) $existing['id'];
                $res['updated']++;
            } else {
                if (!$products->insert($data)) {
                    $res['errors'][] = lang('Quo.impErrRow', [$line, implode(' ', $products->errors())]);
                    $res['skipped']++;

                    continue;
                }
                $id = (int) $products->getInsertID();
                $res['created']++;
            }

            // Los 3 valores: en la plantilla solo va el precio; el resto de las
            // condiciones se heredan del producto.
            $tiers = [];

            foreach ([1, 2, 3] as $t) {
                $value = trim((string) ($row['valor' . $t] ?? ''));
                $tiers[$t] = [
                    'unit_price'     => $value === '' ? '' : (string) $this->num($value),
                    'quantity'       => $data['default_quantity'],
                    'discount_type'  => $data['discount_type'],
                    'discount_value' => 0,
                    'tax_percent'    => $data['tax_percent'],
                ];
            }
            $prices->saveTiers($id, $tiers);
        }

        return $res;
    }

    /**
     * Interpreta el número venga como venga: «1.200.000,50», «1,200,000.50»,
     * «1.200.000», «1200000.50» o «1200000».
     *
     * Las reglas, por si algún día sorprende:
     * 1. Con coma Y punto, el **último** separador es el decimal.
     * 2. Con un solo tipo de separador repetido, son miles.
     * 3. Con un solo separador seguido de **exactamente 3 dígitos**, son miles
     *    (`1.250` es mil doscientos cincuenta): en precios, tres decimales son
     *    rarísimos y escribir el punto de miles es lo habitual aquí.
     */
    private function num($value): float
    {
        $value = str_replace([' ', "\xC2\xA0", '$'], '', trim((string) $value));
        if ($value === '') {
            return 0.0;
        }

        $dots   = substr_count($value, '.');
        $commas = substr_count($value, ',');

        if ($dots && $commas) {
            return (float) (strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(['.', ','], ['', '.'], $value)   // 1.200.000,50
                : str_replace(',', '', $value));               // 1,200,000.50
        }

        $sep = $dots ? '.' : ($commas ? ',' : '');
        if ($sep === '') {
            return (float) $value;
        }

        $count = $dots ?: $commas;
        $tail  = strlen(substr($value, strrpos($value, $sep) + 1));

        // Repetido, o un único separador con 3 dígitos detrás → miles.
        if ($count > 1 || $tail === 3) {
            return (float) str_replace($sep, '', $value);
        }

        return (float) str_replace($sep, '.', $value);
    }
}
