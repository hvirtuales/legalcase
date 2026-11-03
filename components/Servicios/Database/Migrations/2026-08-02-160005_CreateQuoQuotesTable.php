<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La cotización. Guarda un **snapshot** de cliente y vendedor además de sus FK:
 * editar después el maestro NO debe cambiar un documento ya emitido (es la
 * promesa que hace el manual de la app original). Las FK son `SET NULL` para que
 * borrar un cliente no borre su historial de cotizaciones.
 *
 * Los totales se persisten calculados (por el servidor, nunca por el navegador).
 */
class CreateQuoQuotesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_quotes')) {
            return;
        }

        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'number'            => ['type' => 'VARCHAR', 'constraint' => 40],
            'doc_type'          => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Cotización'],

            'client_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'client_name'       => ['type' => 'VARCHAR', 'constraint' => 200, 'default' => ''],
            'client_nit'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'client_city'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'client_address'    => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'client_contact'    => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'client_phone'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'client_email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],

            'seller_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'seller_name'       => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'seller_email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'seller_phone'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'seller_card_url'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],

            'issue_date'        => ['type' => 'DATE', 'null' => true],
            'validity_days'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'payment_form'      => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Contado'],
            'payment_method'    => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'Transferencia bancaria'],
            'currency'          => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],

            'intro_html'        => ['type' => 'TEXT', 'null' => true],
            'terms_html'        => ['type' => 'TEXT', 'null' => true],
            'show_detail'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            'subtotal'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'discount_total'    => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'taxable_base'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_total'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'retention_percent' => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'retention_total'   => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total'             => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],

            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pendiente'], // pendiente|aprobada|facturada|rechazada
            'created_by'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'number']);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addKey(['company_id', 'issue_date']);
        $this->forge->addKey('seller_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('client_id', 'cmp_quo_clients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('seller_id', 'cmp_quo_sellers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_quo_quotes');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_quo_quotes', true);
    }
}
