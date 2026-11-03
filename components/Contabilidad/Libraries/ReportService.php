<?php

namespace Components\Contabilidad\Libraries;

/**
 * Agregaciones para el panel/reportes del componente. Portable: usa Query
 * Builder + SQL estándar (CASE/COALESCE/GROUP BY); el agrupado mensual se hace
 * en PHP para no depender de funciones de fecha específicas de cada motor.
 */
class ReportService
{
    private function base(int $companyId, ?string $from, ?string $to)
    {
        $b = \Config\Database::connect()->table('cmp_cont_vouchers v')
            ->join('cmp_cont_voucher_types t', 't.id = v.type_id')
            ->where('v.company_id', $companyId)
            ->where('v.status !=', 'anulado')
            ->where('v.deleted_at', null);
        if ($from) {
            $b->where('v.date >=', $from);
        }
        if ($to) {
            $b->where('v.date <=', $to);
        }

        return $b;
    }

    /** Totales del rango. @return array{ingresos,egresos,balance,iva,retencion,other,count} */
    public function summary(int $companyId, ?string $from, ?string $to): array
    {
        $row = $this->base($companyId, $from, $to)->select(
            "COALESCE(SUM(CASE WHEN t.name = 'Ingreso' THEN v.total_amount ELSE 0 END), 0) AS ingresos,"
            . " COALESCE(SUM(CASE WHEN t.name = 'Egreso' THEN v.total_amount ELSE 0 END), 0) AS egresos,"
            . ' COALESCE(SUM(v.total_iva), 0) AS iva,'
            . ' COALESCE(SUM(v.total_retencion), 0) AS retencion,'
            . ' COALESCE(SUM(v.total_other_taxes), 0) AS other,'
            . ' COUNT(*) AS count',
            false
        )->get()->getRowArray() ?: [];

        $ingresos = (float) ($row['ingresos'] ?? 0);
        $egresos  = (float) ($row['egresos'] ?? 0);

        return [
            'ingresos'  => $ingresos,
            'egresos'   => $egresos,
            'balance'   => round($ingresos - $egresos, 2),
            'iva'       => (float) ($row['iva'] ?? 0),
            'retencion' => (float) ($row['retencion'] ?? 0),
            'other'     => (float) ($row['other'] ?? 0),
            'count'     => (int) ($row['count'] ?? 0),
        ];
    }

    /** Desglose por tipo de comprobante. */
    public function byType(int $companyId, ?string $from, ?string $to): array
    {
        return $this->base($companyId, $from, $to)
            ->select('t.name AS type_name, COUNT(*) AS n, COALESCE(SUM(v.total_amount), 0) AS total', false)
            ->groupBy('t.name')
            ->orderBy('total', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Serie mensual ingresos vs egresos (agrupado en PHP). Rellena los meses del
     * rango (máx. 12) aunque no tengan movimientos.
     * @return array<int, array{month:string, ingresos:float, egresos:float}>
     */
    public function monthly(int $companyId, ?string $from, ?string $to): array
    {
        $rows = $this->base($companyId, $from, $to)
            ->select('v.date AS d, t.name AS type_name, v.total_amount AS total', false)
            ->get()->getResultArray();

        $buckets = [];
        foreach ($rows as $r) {
            $ym = substr((string) $r['d'], 0, 7);
            $buckets[$ym] ??= ['ingresos' => 0.0, 'egresos' => 0.0];
            if ($r['type_name'] === 'Ingreso') {
                $buckets[$ym]['ingresos'] += (float) $r['total'];
            } elseif ($r['type_name'] === 'Egreso') {
                $buckets[$ym]['egresos'] += (float) $r['total'];
            }
        }

        // Meses del rango (o últimos 6 si no hay rango) para rellenar vacíos
        $months = $this->monthSequence($from, $to, array_keys($buckets));

        $out = [];
        foreach ($months as $ym) {
            $out[] = [
                'month'    => $ym,
                'ingresos' => round($buckets[$ym]['ingresos'] ?? 0, 2),
                'egresos'  => round($buckets[$ym]['egresos'] ?? 0, 2),
            ];
        }

        return $out;
    }

    /** Métricas de cuentas de cobro. */
    public function requestCounts(int $companyId): array
    {
        $rows = \Config\Database::connect()->table('cmp_cont_payment_requests')
            ->select('status, COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS total', false)
            ->where('company_id', $companyId)->groupBy('status')->get()->getResultArray();

        $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0, 'pending_amount' => 0.0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['n'];
            $out['total'] += (int) $r['n'];
            if ($r['status'] === 'pending') {
                $out['pending_amount'] = (float) $r['total'];
            }
        }

        return $out;
    }

    /** Secuencia de meses YYYY-MM a mostrar (máx. 12). */
    private function monthSequence(?string $from, ?string $to, array $present): array
    {
        $end   = $to ? substr($to, 0, 7) : date('Y-m');
        $start = $from ? substr($from, 0, 7) : date('Y-m', strtotime('-5 months', strtotime($end . '-01')));

        $months = [];
        $cur    = $start . '-01';
        $limit  = 0;
        while (strtotime($cur) <= strtotime($end . '-01') && $limit < 12) {
            $months[] = substr($cur, 0, 7);
            $cur      = date('Y-m-01', strtotime($cur . ' +1 month'));
            $limit++;
        }
        if (!$months) {
            $months = $present ?: [date('Y-m')];
        }

        return $months;
    }
}
