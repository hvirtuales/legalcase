<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enlaza el comprobante con su cuenta/caja de origen (§3.13): source_type
 * (bank|cash) + source_id. Y liga los movimientos de caja auto-generados a su
 * comprobante (voucher_id) para poder sincronizarlos/reversarlos. Idempotente.
 */
class AddSourceAccountToVouchers extends Migration
{
    public function up()
    {
        $vf = [];
        if (!$this->db->fieldExists('source_type', 'cmp_cont_vouchers')) {
            $vf['source_type'] = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true]; // bank|cash
        }
        if (!$this->db->fieldExists('source_id', 'cmp_cont_vouchers')) {
            $vf['source_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
        }
        if ($vf) {
            $this->forge->addColumn('cmp_cont_vouchers', $vf);
        }

        if (!$this->db->fieldExists('voucher_id', 'cmp_cont_cash_transactions')) {
            $this->forge->addColumn('cmp_cont_cash_transactions', [
                'voucher_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            ]);
            $this->forge->addKey('voucher_id');
        }
    }

    public function down()
    {
        foreach (['source_type', 'source_id'] as $c) {
            if ($this->db->fieldExists($c, 'cmp_cont_vouchers')) {
                $this->forge->dropColumn('cmp_cont_vouchers', $c);
            }
        }
        if ($this->db->fieldExists('voucher_id', 'cmp_cont_cash_transactions')) {
            $this->forge->dropColumn('cmp_cont_cash_transactions', 'voucher_id');
        }
    }
}
