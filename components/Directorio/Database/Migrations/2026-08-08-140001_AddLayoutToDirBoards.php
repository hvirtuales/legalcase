<?php

namespace Components\Directorio\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Qué enseña cada vista: el listado, el mapa, o los dos.
 *
 * No todas las vistas quieren mapa. Un «Quiénes somos» con veinte afiliadas sin
 * sede física no gana nada con medio pantallazo de mapa vacío, y un plano de un
 * centro comercial se lee mejor a pantalla completa que partido con una lista.
 *
 * `both` por defecto: es lo que hacían todas las vistas existentes, así que
 * ninguna cambia de aspecto al aplicar esta migración.
 *
 * VARCHAR + validación en el modelo, no ENUM (portabilidad a los 3 motores, §8).
 */
class AddLayoutToDirBoards extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_dir_boards')) {
            return;
        }
        if ($this->db->fieldExists('layout', 'cmp_dir_boards')) {
            return;
        }

        $this->forge->addColumn('cmp_dir_boards', [
            'layout' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'both',
                'after'      => 'mode',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('cmp_dir_boards') && $this->db->fieldExists('layout', 'cmp_dir_boards')) {
            $this->forge->dropColumn('cmp_dir_boards', 'layout');
        }
    }
}
