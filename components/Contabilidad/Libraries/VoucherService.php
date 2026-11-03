<?php

namespace Components\Contabilidad\Libraries;

use Components\Contabilidad\Models\PucAccountModel;
use Components\Contabilidad\Models\VoucherEntryModel;
use Components\Contabilidad\Models\VoucherItemModel;
use Components\Contabilidad\Models\VoucherItemTaxModel;
use Components\Contabilidad\Models\VoucherModel;
use Components\Contabilidad\Models\VoucherTypeModel;

/**
 * Reglas de negocio de comprobantes (todo server-side). No confía en totales
 * enviados por el cliente: los recalcula a partir de los ítems.
 */
class VoucherService
{
    /** Cuentas PUC usadas por la auto-generación del asiento (§6.5) — fallback si no están en el catálogo. */
    public const ACCOUNTS = [
        '1105'   => 'Caja',
        '1110'   => 'Bancos',
        '2380'   => 'Acreedores varios (tarjetas)',
        '136005' => 'Anticipo de impuestos - Retención en la fuente',
        '236515' => 'Retención en la fuente por pagar',
        '240805' => 'IVA generado',
        '240810' => 'IVA descontable',
        '4145'   => 'Ingresos operacionales',
        '5110'   => 'Gastos',
        '1355'   => 'Anticipo de otros impuestos y retenciones',
        '2368'   => 'Otras retenciones / impuestos por pagar',
        '5115'   => 'Impuestos (otros)',
    ];

    private const PAYMENT_ACCOUNT = [
        'Efectivo'              => '1105',
        'Transferencia bancaria' => '1110',
        'Consignación bancaria'  => '1110',
        'Cheque'                => '1110',
        'Tarjeta débito'        => '1110',
        'PSE'                   => '1110',
        'Tarjeta crédito'       => '2380',
    ];

    private const DEFAULT_TYPES = [
        ['Ingreso', 'ING'],
        ['Egreso', 'EGR'],
        ['Documento Equivalente', 'DE'],
    ];

    public const PAYMENT_METHODS = [
        'Efectivo', 'Transferencia bancaria', 'Consignación bancaria', 'Cheque',
        'Tarjeta débito', 'Tarjeta crédito', 'PSE',
    ];

    /** @var array<string,string>|null cache code→name del PUC */
    private ?array $pucMap = null;

    /** Nombre de la cuenta desde el PUC (catálogo), con fallback a las constantes. */
    public function pucName(string $code): string
    {
        if ($this->pucMap === null) {
            $this->pucMap = [];
            foreach ((new PucAccountModel())->findAll() as $a) {
                $this->pucMap[$a['code']] = $a['name'];
            }
        }

        return $this->pucMap[$code] ?? (self::ACCOUNTS[$code] ?? $code);
    }

    /** Mapa medio de pago → cuenta (para el auto-generar en el cliente). */
    public static function paymentAccountMap(): array
    {
        return self::PAYMENT_ACCOUNT;
    }

    /** Mapa código → nombre de las cuentas del asiento (nombres del PUC, fallback a constantes). */
    public function accountNames(): array
    {
        $out = [];
        foreach (array_keys(self::ACCOUNTS) as $code) {
            $out[$code] = $this->pucName($code);
        }

        return $out;
    }

    /** Auto-siembra y deduplica los tipos de la empresa. Devuelve los tipos vigentes. */
    public function ensureTypes(int $companyId): array
    {
        $model    = new VoucherTypeModel();
        $existing = $model->where('company_id', $companyId)->orderBy('id', 'asc')->findAll();

        $byName = [];
        foreach ($existing as $t) {
            $n = $t['name'];
            if (!isset($byName[$n]) || (int) $t['current_sequence'] > (int) $byName[$n]['current_sequence']) {
                $byName[$n] = $t;
            }
        }
        foreach (self::DEFAULT_TYPES as [$name, $prefix]) {
            if (!isset($byName[$name])) {
                $id            = $model->insert(['company_id' => $companyId, 'name' => $name, 'prefix' => $prefix, 'current_sequence' => 0]);
                $byName[$name] = $model->find($id);
            }
        }

        return array_values($byName);
    }

    /**
     * Recalcula ítems y totales (§6.3), incluyendo "otros impuestos" por ítem.
     *
     * @param array $items     cada uno con description/amount/iva_rate/retencion_rate y opcional other_taxes[] (ids)
     * @param array $otherDefs [tax_config_id => ['name','rate','is_additive']]
     */
    public function computeTotals(array $items, array $otherDefs = []): array
    {
        $subtotal = 0.0;
        $iva      = 0.0;
        $ret      = 0.0;
        $oAdd     = 0.0; // otros aditivos (suman)
        $oSub     = 0.0; // otros sustractivos (restan)
        $out      = [];
        $order    = 0;

        foreach ($items as $it) {
            $desc = trim((string) ($it['description'] ?? ''));
            if ($desc === '' && (float) ($it['amount'] ?? 0) == 0.0) {
                continue;
            }
            $amount  = round((float) ($it['amount'] ?? 0), 2);
            $ivaRate = (float) ($it['iva_rate'] ?? 0);
            $retRate = (float) ($it['retencion_rate'] ?? 0);
            $ivaAmt  = round($amount * $ivaRate / 100, 2);
            $retAmt  = round($amount * $retRate / 100, 2);

            $subtotal += $amount;
            $iva      += $ivaAmt;
            $ret      += $retAmt;

            $itemTaxes = [];
            foreach ((array) ($it['other_taxes'] ?? []) as $tid) {
                $tid = (int) $tid;
                if (!isset($otherDefs[$tid])) {
                    continue;
                }
                $d   = $otherDefs[$tid];
                $amt = round($amount * (float) $d['rate'] / 100, 2);
                if (!empty($d['is_additive'])) {
                    $oAdd += $amt;
                } else {
                    $oSub += $amt;
                }
                $itemTaxes[] = ['tax_config_id' => $tid, 'name' => $d['name'], 'rate' => (float) $d['rate'], 'amount' => $amt, 'is_additive' => !empty($d['is_additive']) ? 1 : 0];
            }

            $out[] = [
                'description'      => $desc,
                'amount'           => $amount,
                'iva_rate'         => $ivaRate,
                'iva_amount'       => $ivaAmt,
                'retencion_rate'   => $retRate,
                'retencion_amount' => $retAmt,
                'sort_order'       => $order++,
                'taxes'            => $itemTaxes,
            ];
        }

        $otherSigned = round($oAdd - $oSub, 2);

        return [
            'items'             => $out,
            'subtotal'          => round($subtotal, 2),
            'total_iva'         => round($iva, 2),
            'total_retencion'   => round($ret, 2),
            'total_other_taxes' => $otherSigned,
            'other_additive'    => round($oAdd, 2),
            'other_subtractive' => round($oSub, 2),
            'total_amount'      => round($subtotal + $iva - $ret + $otherSigned, 2),
        ];
    }

    /** Numeración PREFIX-000001 con incremento atómico (§6.1). */
    public function nextNumber(int $typeId): string
    {
        $db = \Config\Database::connect();
        $db->transStart();
        $db->table('cmp_cont_voucher_types')->where('id', $typeId)
            ->set('current_sequence', 'current_sequence + 1', false)->update();
        $row = $db->table('cmp_cont_voucher_types')->select('prefix, current_sequence')
            ->where('id', $typeId)->get()->getRow();
        $db->transComplete();

        return $row->prefix . '-' . str_pad((string) $row->current_sequence, 6, '0', STR_PAD_LEFT);
    }

    /** Asiento de partida doble estándar (§6.5) con nombres del PUC. Debe = Haber por construcción. */
    public function generateEntries(array $v, string $typeName): array
    {
        $pay     = self::PAYMENT_ACCOUNT[$v['payment_method'] ?? ''] ?? '1110';
        $payName = null;
        // El origen explícito (cuenta bancaria / caja) manda sobre el medio de pago (§6.5)
        $st = $v['source_type'] ?? '';
        if ($st === 'cash') {
            $pay     = '1105'; // Caja
            $payName = ($v['source_account_label'] ?? '') ?: null;
        } elseif ($st === 'bank') {
            $pay = '1110'; // Bancos
            $lbl = (string) ($v['source_account_label'] ?? '');
            $payName = $this->pucName('1110') . ($lbl !== '' ? ' — ' . $lbl : '');
        }
        $sub     = (float) $v['subtotal'];
        $iva     = (float) $v['total_iva'];
        $ret     = (float) $v['total_retencion'];
        $neto    = (float) $v['total_amount'];
        $oAdd    = (float) ($v['other_additive'] ?? 0);
        $oSub    = (float) ($v['other_subtractive'] ?? 0);
        $entries = [];
        $order   = 0;

        $add = function (string $code, float $debit, float $credit, string $desc, ?string $name = null) use (&$entries, &$order) {
            if (round($debit, 2) == 0.0 && round($credit, 2) == 0.0) {
                return;
            }
            $entries[] = [
                'account_code' => $code,
                'account_name' => $name ?: $this->pucName($code),
                'debit'        => round($debit, 2),
                'credit'       => round($credit, 2),
                'description'  => $desc,
                'sort_order'   => $order++,
            ];
        };

        if (mb_strtolower($typeName) === 'ingreso') {
            $add($pay, $neto, 0, 'Recaudo', $payName);
            $add('136005', $ret, 0, 'Retención en la fuente');
            $add('1355', $oSub, 0, 'Otras retenciones/anticipos');
            $add('4145', 0, $sub, 'Ingreso');
            $add('240805', 0, $iva, 'IVA generado');
            $add('2368', 0, $oAdd, 'Otros impuestos por pagar');
        } else { // Egreso / Documento Equivalente
            $add('5110', $sub, 0, 'Gasto');
            $add('240810', $iva, 0, 'IVA descontable');
            $add('5115', $oAdd, 0, 'Otros impuestos');
            $add($pay, 0, $neto, 'Pago', $payName);
            $add('236515', 0, $ret, 'Retención practicada');
            $add('2368', 0, $oSub, 'Otras retenciones por pagar');
        }

        return $entries;
    }

    /**
     * Crea y persiste un comprobante completo (numeración + totales + ítems +
     * asiento) en una transacción. Reutilizable desde controladores o procesos
     * (p. ej. auto-Egreso al aprobar una cuenta de cobro). Devuelve el id.
     *
     * @param array $header date, beneficiary_name, beneficiary_id, description, payment_method, source_account_label
     * @param array $rawItems ítems con description/amount/iva_rate/retencion_rate (+ opcional other_taxes)
     */
    public function createVoucher(int $companyId, array $type, array $header, array $rawItems, array $otherDefs = []): int
    {
        $calc = $this->computeTotals($rawItems, $otherDefs);
        $data = array_merge([
            'beneficiary_name'     => '',
            'beneficiary_id'       => '',
            'description'          => null,
            'payment_method'       => 'Efectivo',
            'source_account_label' => null,
            'date'                 => date('Y-m-d'),
        ], $header, [
            'company_id'        => $companyId,
            'type_id'           => (int) $type['id'],
            'number'            => $this->nextNumber((int) $type['id']),
            'status'            => 'emitido',
            'subtotal'          => $calc['subtotal'],
            'total_iva'         => $calc['total_iva'],
            'total_retencion'   => $calc['total_retencion'],
            'total_other_taxes' => $calc['total_other_taxes'],
            'total_amount'      => $calc['total_amount'],
        ]);

        $db = \Config\Database::connect();
        $db->transStart();

        $vm = new VoucherModel();
        $vm->insert($data);
        $vid = (int) $vm->getInsertID();

        $im        = new VoucherItemModel();
        $itemTaxes = new VoucherItemTaxModel();
        foreach ($calc['items'] as $it) {
            $taxes = $it['taxes'] ?? [];
            unset($it['taxes']);
            $it['voucher_id'] = $vid;
            $im->insert($it);
            $iid = (int) $im->getInsertID();
            foreach ($taxes as $tx) {
                $tx['item_id'] = $iid;
                $itemTaxes->insert($tx);
            }
        }

        $em        = new VoucherEntryModel();
        $entryData = array_merge($data, ['other_additive' => $calc['other_additive'], 'other_subtractive' => $calc['other_subtractive']]);
        foreach ($this->generateEntries($entryData, $type['name']) as $en) {
            $en['voucher_id'] = $vid;
            $em->insert($en);
        }

        $db->transComplete();

        return $vid;
    }

    /** Balance de la empresa: ingresos − egresos (comprobantes no anulados) (§6.9). */
    public function companyBalance(int $companyId): float
    {
        $db  = \Config\Database::connect();
        $row = $db->table('cmp_cont_vouchers v')
            ->join('cmp_cont_voucher_types t', 't.id = v.type_id')
            ->select("COALESCE(SUM(CASE WHEN t.name = 'Ingreso' THEN v.total_amount ELSE 0 END), 0) AS ing")
            ->select("COALESCE(SUM(CASE WHEN t.name = 'Egreso' THEN v.total_amount ELSE 0 END), 0) AS egr")
            ->where('v.company_id', $companyId)
            ->where('v.status !=', 'anulado')
            ->where('v.deleted_at', null)
            ->get()->getRow();

        return round((float) $row->ing - (float) $row->egr, 2);
    }
}
