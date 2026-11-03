<?php

namespace Components\Directorio\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * **Vistas** del directorio: cada una es una página pública con un subconjunto
 * de las empresas del grupo.
 *
 * En la interfaz son «vistas» —la palabra del encargo—, pero en el código son
 * `board`: `View` ya significa otra cosa en CI4 y un `ViewModel` al lado de
 * `app/Views/` sería una trampa para el siguiente que lea esto.
 *
 * Dos modos, y solo dos a propósito:
 *
 *   - **`sector`**: todas las empresas del grupo que estén en ese sector. Se
 *     mantiene sola — una empresa nueva del grupo aparece sin tocar nada.
 *   - **`manual`**: exactamente las que se marquen (pivote `cmp_dir_board_companies`).
 *     Para una feria, una promoción o un piso del centro comercial.
 *
 * `sector_id` apunta al catálogo del núcleo (`sectors`) y **sin FK**: esa tabla
 * usa soft-delete, así que un `ON DELETE SET NULL` casi nunca dispararía. Una
 * vista cuyo sector desapareció se queda sin empresas y se ve al momento, que
 * es mejor que fallar al pintar.
 */
class CreateDirBoardsTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_dir_boards')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'title'       => ['type' => 'VARCHAR', 'constraint' => 150],
                'slug'        => ['type' => 'VARCHAR', 'constraint' => 160],
                'intro'       => ['type' => 'TEXT', 'null' => true],
                // VARCHAR + validación en el modelo, no ENUM (portabilidad §8).
                'mode'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'sector'],
                'sector_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                // Encuadre inicial del mapa. Vacío = se encuadra solo sobre los
                // marcadores que haya, que es lo correcto el 90 % de las veces.
                'center_lat'  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'center_lng'  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'zoom'        => ['type' => 'TINYINT', 'constraint' => 2, 'null' => true],
                'sort_order'  => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 0],
                'is_published' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
                'views'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('tenant_id');
            $this->forge->addKey('slug');
            $this->forge->addForeignKey('tenant_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_dir_boards', true);
        }

        if (!$this->db->tableExists('cmp_dir_board_companies')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'board_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 0],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['board_id', 'company_id']);
            $this->forge->addForeignKey('board_id', 'cmp_dir_boards', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_dir_board_companies', true);
        }
    }

    public function down()
    {
        // La pivote primero: tiene FK a la otra.
        $this->forge->dropTable('cmp_dir_board_companies', true);
        $this->forge->dropTable('cmp_dir_boards', true);
    }
}
