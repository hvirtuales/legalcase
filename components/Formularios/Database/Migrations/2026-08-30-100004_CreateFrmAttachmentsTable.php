<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Archivos subidos al responder (campo `file`) y la firma dibujada (`signature`,
 * que se guarda como PNG de verdad, no como una cadena inventada).
 *
 * Viven en `writable/uploads/formularios/`, FUERA de `public/`: un documento de
 * identidad o una firma no pueden quedar accesibles por una URL adivinable. La
 * descarga pasa siempre por el controlador, que comprueba permiso Y empresa.
 */
class CreateFrmAttachmentsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_frm_attachments')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'response_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'form_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'field_id'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'stored_name'   => ['type' => 'VARCHAR', 'constraint' => 190],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
            'size'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('response_id');
        $this->forge->addKey(['company_id', 'form_id']);
        $this->forge->addForeignKey('response_id', 'cmp_frm_responses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_frm_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_frm_attachments', true);
    }
}
