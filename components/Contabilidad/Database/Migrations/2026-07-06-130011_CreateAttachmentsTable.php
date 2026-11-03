<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adjuntos polimórficos del componente (comprobantes, cuentas de cobro,
 * beneficiarios). Los archivos viven en writable/uploads/contabilidad
 * (privados); aquí solo se guarda la ruta relativa.
 */
class CreateAttachmentsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_attachments')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'owner_type'    => ['type' => 'VARCHAR', 'constraint' => 30], // voucher|payment_request|beneficiary
            'owner_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mimetype'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'size'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'file_path'     => ['type' => 'VARCHAR', 'constraint' => 255], // relativa a writable/uploads/contabilidad
            'uploaded_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['owner_type', 'owner_id']);
        $this->forge->createTable('cmp_cont_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_attachments', true);
    }
}
