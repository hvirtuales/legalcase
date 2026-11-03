<?php

namespace Components\Contabilidad\Libraries;

use Components\Contabilidad\Models\CashBoxModel;
use Components\Contabilidad\Models\CashTransactionModel;

/**
 * Lógica de caja menor: saldo por reproducción de movimientos (§6.6).
 * Orden estable por created_at y, en empate, por id. ingreso suma, egreso
 * resta; arqueo/ajuste FIJAN el saldo al efectivo contado (balance_after).
 */
class CashBoxService
{
    public const TYPES = ['ingreso', 'egreso', 'arqueo', 'ajuste'];

    /**
     * Movimientos de la caja en orden cronológico estable. Con $excludeVoucherId
     * omite los movimientos ligados a ese comprobante (para validar saldo al
     * editar sin contar su propio movimiento anterior).
     */
    public function movements(int $cashBoxId, ?int $excludeVoucherId = null): array
    {
        $m = (new CashTransactionModel())->where('cash_box_id', $cashBoxId);
        if ($excludeVoucherId !== null) {
            $m->groupStart()->where('voucher_id !=', $excludeVoucherId)->orWhere('voucher_id', null)->groupEnd();
        }

        return $m->orderBy('created_at', 'asc')->orderBy('id', 'asc')->findAll();
    }

    /** Saldo disponible excluyendo los movimientos del comprobante dado (para validar al editar). */
    public function availableBalance(int $cashBoxId, ?int $excludeVoucherId = null): float
    {
        return $this->balance($cashBoxId, null, $this->movements($cashBoxId, $excludeVoucherId));
    }

    /**
     * Sincroniza el movimiento de caja ligado a un comprobante: borra el anterior
     * (si lo hay) y crea uno nuevo si el origen es una caja y el tipo es
     * Ingreso/Egreso (Documento Equivalente no genera movimiento).
     */
    public function syncVoucherMovement(int $voucherId, ?string $sourceType, ?int $sourceId, string $typeName, float $amount, ?string $date, string $reference): void
    {
        $tm = new CashTransactionModel();
        $tm->where('voucher_id', $voucherId)->delete();

        if ($sourceType !== 'cash' || !$sourceId) {
            return;
        }
        $t = mb_strtolower($typeName);
        $mtype = $t === 'ingreso' ? 'ingreso' : ($t === 'egreso' ? 'egreso' : null);
        if ($mtype === null) {
            return;
        }
        $prev         = $this->balance($sourceId);
        $balanceAfter = $mtype === 'ingreso' ? $prev + $amount : $prev - $amount;

        $tm->insert([
            'cash_box_id'   => $sourceId,
            'type'          => $mtype,
            'concept'       => 'Comprobante ' . $reference,
            'amount'        => round($amount, 2),
            'balance_after' => round($balanceAfter, 2),
            'date'          => $date ?: date('Y-m-d'),
            'reference'     => $reference,
            'voucher_id'    => $voucherId,
        ]);
    }

    /** Borra los movimientos de caja ligados a un comprobante (al eliminar/anular). */
    public function removeVoucherMovements(int $voucherId): void
    {
        (new CashTransactionModel())->where('voucher_id', $voucherId)->delete();
    }

    /** Saldo actual de la caja (reproducción desde el saldo inicial). */
    public function balance(int $cashBoxId, ?array $box = null, ?array $movements = null): float
    {
        $box ??= (new CashBoxModel())->find($cashBoxId);
        $bal = $box ? (float) $box['initial_balance'] : 0.0;

        foreach ($movements ?? $this->movements($cashBoxId) as $t) {
            if ($t['type'] === 'ingreso') {
                $bal += (float) $t['amount'];
            } elseif ($t['type'] === 'egreso') {
                $bal -= (float) $t['amount'];
            } else { // arqueo | ajuste → fija al efectivo contado
                $bal = (float) $t['balance_after'];
            }
        }

        return round($bal, 2);
    }
}
