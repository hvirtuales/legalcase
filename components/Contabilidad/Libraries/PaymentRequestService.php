<?php

namespace Components\Contabilidad\Libraries;

use Components\Contabilidad\Models\BeneficiaryModel;
use Components\Contabilidad\Models\PaymentRequestModel;

/**
 * Reglas de negocio de cuentas de cobro. Los totales se calculan server-side.
 * La retención la define el admin después (§6.7).
 */
class PaymentRequestService
{
    /** Referencia SOL-000001 única por empresa (§6.2). */
    public function nextReference(int $companyId): string
    {
        $model = new PaymentRequestModel();
        $n     = $model->where('company_id', $companyId)->countAllResults() + 1;
        do {
            $ref = 'SOL-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            $n++;
        } while ($model->where('company_id', $companyId)->where('reference', $ref)->countAllResults() > 0);

        return $ref;
    }

    /**
     * Calcula ítems + totales de la solicitud (subtotal + IVA; sin retención aún).
     * @return array{items:array, amount:float, iva_amount:float, total:float, concept:string}
     */
    public function computeItems(array $items): array
    {
        $subtotal = 0.0;
        $iva      = 0.0;
        $out      = [];
        $concepts = [];

        foreach ($items as $it) {
            $desc = trim((string) ($it['description'] ?? ''));
            if ($desc === '' && (float) ($it['amount'] ?? 0) == 0.0) {
                continue;
            }
            $amount  = round((float) ($it['amount'] ?? 0), 2);
            $ivaRate = (float) ($it['iva_rate'] ?? 0);
            $ivaAmt  = round($amount * $ivaRate / 100, 2);
            $subtotal += $amount;
            $iva      += $ivaAmt;
            $concepts[] = $desc;
            $out[]      = [
                'description'      => $desc,
                'amount'           => $amount,
                'iva_rate'         => $ivaRate,
                'iva_amount'       => $ivaAmt,
                'retencion_rate'   => 0,
                'retencion_amount' => 0,
            ];
        }

        return [
            'items'      => $out,
            'amount'     => round($subtotal, 2),
            'iva_amount' => round($iva, 2),
            'total'      => round($subtotal + $iva, 2),
            'concept'    => implode(' · ', $concepts),
        ];
    }

    /**
     * Aplica una tasa de retención a cada concepto y recalcula (§6.7).
     * @return array{items:array, retencion_amount:float, total_amount:float}
     */
    public function applyRetencion(array $request, float $rate): array
    {
        $items = json_decode((string) ($request['items_json'] ?? '[]'), true) ?: [];
        $ret   = 0.0;
        foreach ($items as &$it) {
            $base                   = round((float) ($it['amount'] ?? 0), 2);
            $it['retencion_rate']   = $rate;
            $it['retencion_amount'] = round($base * $rate / 100, 2);
            $ret += $it['retencion_amount'];
        }
        unset($it);

        $total = round((float) $request['amount'] + (float) $request['iva_amount'] - $ret, 2);

        return ['items' => $items, 'retencion_amount' => round($ret, 2), 'total_amount' => $total];
    }

    /**
     * Registra/actualiza el tercero desde la solicitud (upsert, §6.8). Un campo
     * vacío NO borra el valor previo.
     */
    public function upsertBeneficiary(int $companyId, array $c): void
    {
        $model    = new BeneficiaryModel();
        $existing = null;
        if (($c['id_number'] ?? '') !== '') {
            $existing = $model->where('company_id', $companyId)->where('id_number', $c['id_number'])
                ->where('id_type', $c['id_type'])->first()
                ?? $model->where('company_id', $companyId)->where('id_number', $c['id_number'])->first();
        }

        $fields = [
            'name'                => $c['name'] ?? '',
            'email'               => $c['email'] ?? '',
            'phone'               => $c['phone'] ?? '',
            'city'                => $c['city'] ?? '',
            'address'             => $c['address'] ?? '',
            'contact_name'        => $c['contact'] ?? '',
            'bank_name'           => $c['bank'] ?? '',
            'bank_account_type'   => $c['account_type'] ?? 'Ahorros',
            'bank_account_number' => $c['account'] ?? '',
        ];

        if ($existing) {
            // Solo sobreescribe con valores no vacíos (conserva lo previo)
            $update = array_filter($fields, static fn ($v) => trim((string) $v) !== '');
            if ($update) {
                $model->update($existing['id'], $update);
            }
        } else {
            $model->insert(array_merge($fields, [
                'company_id' => $companyId,
                'id_type'    => $c['id_type'] ?? 'CC',
                'id_number'  => $c['id_number'] ?? '',
            ]));
        }
    }
}
