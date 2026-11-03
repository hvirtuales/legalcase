<?php

namespace Components\Paginas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bloques personalizados: un trozo del árbol (un bloque o una sección entera)
 * guardado con nombre para volver a soltarlo en cualquier página.
 *
 * Es lo mismo que una plantilla pero a otra escala —aquí un fragmento, allí una
 * página completa—, y por eso son tablas distintas: se listan, se buscan y se
 * insertan en sitios diferentes del editor.
 *
 * Siempre pertenece a una empresa: un bloque suele llevar su marca, sus textos y
 * sus enlaces, así que no tiene sentido compartirlo entre empresas.
 */
class CreatePagBlocksTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_pag_blocks')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            // Tipo del bloque raíz guardado (section, hero, cards…). Solo
            // informativo: sirve para el icono y el filtro de la paleta.
            'root_type'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'section'],
            'blocks'     => ['type' => 'TEXT', 'null' => true],
            'uses'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_pag_blocks');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_pag_blocks', true);
    }
}
