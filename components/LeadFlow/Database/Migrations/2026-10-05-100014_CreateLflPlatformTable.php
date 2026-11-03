<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ajustes **globales** del componente: `cmp_lfl_platform`.
 *
 * Hacía falta un sitio para lo que no es de ninguna empresa. `cmp_lfl_settings` es por
 * firma y `cmp_lfl_providers` guarda la app OAuth de Docketwise; la portada del
 * directorio no es ni una cosa ni la otra: hay **una sola portada** porque hay una sola
 * página de inicio.
 *
 * No se usa `app_settings` del núcleo a propósito. Es una tabla que el componente no
 * posee, y el contrato pide que todo lo suyo viaje en su carpeta: una instalación de
 * destino podría no tenerla, o tenerla con otra forma.
 *
 * Clave/valor y no columnas: lo que hoy es una portada mañana será dos cosas más, y
 * cada una no merece una migración.
 */
class CreateLflPlatformTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_lfl_platform')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // `k` y no `key`: es palabra reservada en MySQL y obligaría a entrecomillar
            // en los tres motores.
            'k'          => ['type' => 'VARCHAR', 'constraint' => 64],
            'v'          => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('k');
        $this->forge->createTable('cmp_lfl_platform', true);
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_platform', true);
    }
}
