<?php

namespace Components\Paginas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mensajes recibidos por el bloque «formulario de contacto».
 *
 * El formulario lo dibuja el bloque, así que sus campos los decide quien arma la
 * página: no puede haber columnas fijas para nombre/correo/teléfono. Los valores
 * van en `data` como JSON serializado (TEXT, §8) y el controlador solo acepta los
 * campos que el bloque declara — nunca lo que venga suelto en el POST.
 *
 * `block_uid` es el id del bloque dentro del árbol de la página: permite saber
 * de QUÉ formulario vino el mensaje cuando la página tiene más de uno.
 */
class CreatePagSubmissionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_pag_submissions')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'page_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'block_uid'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
            'form_title' => ['type' => 'VARCHAR', 'constraint' => 180, 'default' => ''],
            'data'       => ['type' => 'TEXT', 'null' => true],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'default' => ''],
            'is_read'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at', 'created_at']);
        $this->forge->addKey('page_id');
        $this->forge->addKey('is_read');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('page_id', 'cmp_pag_pages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_pag_submissions');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_pag_submissions', true);
    }
}
