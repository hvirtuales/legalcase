<?php

namespace Components\Contabilidad\Libraries;

use Components\Contabilidad\Models\PucAccountModel;
use Components\Contabilidad\Models\TaxConfigModel;

/**
 * Datos semilla de catálogos: impuestos por defecto (por empresa, §7.2) y un
 * PUC colombiano de arranque (global, §7.3). Los seeds son idempotentes.
 */
class CatalogService
{
    /** [code, name, category, rate, is_additive] */
    public const DEFAULT_TAXES = [
        ['IVA0', 'IVA 0% – Excluido', 'iva', 0, 1],
        ['IVA5', 'IVA 5% – Reducido', 'iva', 5, 1],
        ['IVA19', 'IVA 19% – General', 'iva', 19, 1],
        ['RFTE0', 'Rte. Fuente 0% – No aplica', 'retencion', 0, 0],
        ['RFTE1', 'Rte. Fuente 1% – Compras', 'retencion', 1, 0],
        ['RFTE25', 'Rte. Fuente 2.5%', 'retencion', 2.5, 0],
        ['RFTE35', 'Rte. Fuente 3.5% – Servicios', 'retencion', 3.5, 0],
        ['RFTE4', 'Rte. Fuente 4% – Arrend. muebles', 'retencion', 4, 0],
        ['RFTE6', 'Rte. Fuente 6% – Arrend. inmuebles', 'retencion', 6, 0],
        ['RFTE11', 'Rte. Fuente 11% – Honorarios', 'retencion', 11, 0],
        ['RFTE15', 'Rte. Fuente 15% – Dividendos', 'retencion', 15, 0],
        ['RFTE20', 'Rte. Fuente 20% – Honorarios P.N.', 'retencion', 20, 0],
        ['RICA', 'ReteICA 0.414%', 'other', 0.414, 0],
        ['RIVA', 'ReteIVA 15%', 'other', 15, 0],
    ];

    /** [code, name, type, nature, level, parent_code] — incluye las cuentas usadas por el asiento automático. */
    public const DEFAULT_PUC = [
        ['1', 'Activo', 'activo', 'debito', 1, null],
        ['11', 'Disponible', 'activo', 'debito', 2, '1'],
        ['1105', 'Caja', 'activo', 'debito', 3, '11'],
        ['1110', 'Bancos', 'activo', 'debito', 3, '11'],
        ['13', 'Deudores', 'activo', 'debito', 2, '1'],
        ['1365', 'Anticipo de impuestos - Retención en la fuente', 'activo', 'debito', 3, '13'],
        ['136005', 'Retención en la fuente (anticipo)', 'activo', 'debito', 4, '1365'],
        ['2', 'Pasivo', 'pasivo', 'credito', 1, null],
        ['23', 'Cuentas por pagar', 'pasivo', 'credito', 2, '2'],
        ['2380', 'Acreedores varios (tarjetas)', 'pasivo', 'credito', 3, '23'],
        ['2365', 'Retención en la fuente por pagar', 'pasivo', 'credito', 3, '23'],
        ['236515', 'Retención en la fuente por pagar', 'pasivo', 'credito', 4, '2365'],
        ['24', 'Impuestos, gravámenes y tasas', 'pasivo', 'credito', 2, '2'],
        ['2408', 'Impuesto sobre las ventas (IVA)', 'pasivo', 'credito', 3, '24'],
        ['240805', 'IVA generado', 'pasivo', 'credito', 4, '2408'],
        ['240810', 'IVA descontable', 'pasivo', 'credito', 4, '2408'],
        ['3', 'Patrimonio', 'patrimonio', 'credito', 1, null],
        ['4', 'Ingresos', 'ingreso', 'credito', 1, null],
        ['41', 'Operacionales', 'ingreso', 'credito', 2, '4'],
        ['4145', 'Ingresos operacionales (servicios)', 'ingreso', 'credito', 3, '41'],
        ['5', 'Gastos', 'gasto', 'debito', 1, null],
        ['51', 'Operacionales de administración', 'gasto', 'debito', 2, '5'],
        ['5110', 'Honorarios y servicios', 'gasto', 'debito', 3, '51'],
        ['6', 'Costos de ventas', 'costo', 'debito', 1, null],
        ['7', 'Costos de producción o de operación', 'costo', 'debito', 1, null],
        // Cuentas genéricas para "otros impuestos" (ReteICA, ReteIVA, …)
        ['1355', 'Anticipo de otros impuestos y retenciones', 'activo', 'debito', 3, '13'],
        ['2368', 'Otras retenciones / impuestos por pagar', 'pasivo', 'credito', 3, '23'],
        ['5115', 'Impuestos (otros)', 'gasto', 'debito', 3, '51'],
    ];

    /** Siembra los impuestos por defecto para una empresa (idempotente por code). @return int creados */
    public function seedTaxes(int $companyId): int
    {
        $model = new TaxConfigModel();
        $count = 0;
        foreach (self::DEFAULT_TAXES as $i => [$code, $name, $category, $rate, $additive]) {
            if (!$model->where('company_id', $companyId)->where('code', $code)->first()) {
                $model->insert([
                    'company_id' => $companyId, 'name' => $name, 'code' => $code, 'category' => $category,
                    'rate' => $rate, 'is_additive' => $additive, 'is_active' => 1, 'sort_order' => $i,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /** Siembra el PUC global de arranque (idempotente por code). @return int creados */
    public function seedPuc(): int
    {
        $model = new PucAccountModel();
        $count = 0;
        foreach (self::DEFAULT_PUC as [$code, $name, $type, $nature, $level, $parent]) {
            if (!$model->where('code', $code)->first()) {
                $model->insert([
                    'code' => $code, 'name' => $name, 'type' => $type, 'nature' => $nature,
                    'level' => $level, 'parent_code' => $parent, 'is_active' => 1,
                ]);
                $count++;
            }
        }

        return $count;
    }
}
