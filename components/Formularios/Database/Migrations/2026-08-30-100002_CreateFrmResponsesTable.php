<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Una respuesta al formulario.
 *
 * `data` es la fuente de verdad (JSON: idCampo => valor) y `labels` una
 * INSTANTANEA idCampo => etiqueta: renombrar una pregunta después no reescribe
 * lo ya recibido, ni cambia los encabezados de una exportación de hace un mes.
 * Es la misma promesa que el snapshot de cliente y vendedor en una cotización.
 *
 * `form_version` dice a qué versión del formulario se contestó.
 * `token` es el enlace del comprobante: aleatorio, nunca el id.
 * `reference` (FRM-000001) se numera de forma TRANSACCIONAL, como los
 * comprobantes de Contabilidad y los radicados de PQRSF.
 */
class CreateFrmResponsesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_frm_responses')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'form_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token'            => ['type' => 'VARCHAR', 'constraint' => 64],
            'reference'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => ''],
            'form_version'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'complete'],
            'data'             => ['type' => 'TEXT', 'null' => true],
            'labels'           => ['type' => 'TEXT', 'null' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'ip'               => ['type' => 'VARCHAR', 'constraint' => 45, 'default' => ''],
            'user_agent'       => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'device'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'desktop'],
            'duration_seconds' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_read'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->addKey(['form_id', 'deleted_at', 'created_at']);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addKey('is_read');
        $this->forge->addForeignKey('form_id', 'cmp_frm_forms', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_frm_responses');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_frm_responses', true);
    }
}
