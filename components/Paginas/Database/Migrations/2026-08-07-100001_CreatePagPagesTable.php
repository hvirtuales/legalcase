<?php

namespace Components\Paginas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Páginas web construidas por bloques.
 *
 * `blocks` guarda el **árbol JSON** de la página en una columna TEXT (portabilidad
 * §8: nada de JSON nativo; el modelo hace `json_encode`/`json_decode`). El HTML
 * NO se persiste: se genera en cada visita con `Libraries\Renderer`, así un
 * cambio de plantilla o de escapado se aplica a todo lo publicado sin migrar
 * datos.
 *
 * `slug` es único **por empresa**, no globalmente: la URL pública lleva delante
 * el slug de la empresa (`/pagina/{empresa}/{pagina}`), que sí es único.
 * `status` es VARCHAR + validación en el modelo (§8, sin ENUM).
 */
class CreatePagPagesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_pag_pages')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 180],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 180],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'], // draft|published
            // Árbol de bloques (JSON serializado). Ver Libraries\BlockRegistry.
            'blocks'           => ['type' => 'TEXT', 'null' => true],
            // Ajustes de presentación de la página (JSON): color de acento,
            // ancho, tipografía, si muestra la cabecera con la marca…
            'settings'         => ['type' => 'TEXT', 'null' => true],
            'meta_title'       => ['type' => 'VARCHAR', 'constraint' => 180, 'default' => ''],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'og_image'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'views'            => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'published_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // El listado filtra por empresa, respeta el soft-delete y ordena por
        // fecha: el índice cubre las tres cosas en ese orden.
        $this->forge->addKey(['company_id', 'deleted_at', 'created_at']);
        // La página pública se resuelve por (empresa, slug, publicada).
        $this->forge->addKey(['company_id', 'slug']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_pag_pages');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_pag_pages', true);
    }
}
