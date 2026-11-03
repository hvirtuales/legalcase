<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOtherTaxesToVouchers extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('total_other_taxes', 'cmp_cont_vouchers')) {
            return;
        }
        $this->forge->addColumn('cmp_cont_vouchers', [
            'total_other_taxes' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'total_retencion'],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('total_other_taxes', 'cmp_cont_vouchers')) {
            $this->forge->dropColumn('cmp_cont_vouchers', 'total_other_taxes');
        }
    }
}
