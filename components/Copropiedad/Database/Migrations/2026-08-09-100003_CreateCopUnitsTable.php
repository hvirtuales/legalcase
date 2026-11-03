<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Inmuebles: casas, apartamentos, locales, oficinas, **parqueaderos y depósitos**.
 *
 * **Todos son filas de esta tabla, no campos de otra.** En propiedad horizontal un
 * parqueadero tiene matrícula inmobiliaria y coeficiente propios y puede venderse
 * o arrendarse aparte del apartamento; un campo `parqueaderos INT` en el
 * apartamento no permitiría decir *cuál* es, ni arrendarlo a un tercero, ni
 * adjuntarle su escritura. Lo que sí existe es la adscripción: `parent_unit_id`
 * dice a qué apartamento está asignado.
 *
 * **`parent_unit_id` no lleva FK a propósito** (auto-referencia): la tabla usa
 * soft-delete, así que un `ON DELETE SET NULL` casi nunca dispararía, y la
 * auto-referencia en el mismo `CREATE TABLE` es de las cosas que cada motor trata
 * distinto. Desadscribir es explícito, lo hace el servicio.
 *
 * `coefficient` es el **coeficiente de copropiedad** en porcentaje —lo que pondera
 * el voto en asamblea y el reparto de la cuota—, con seis decimales porque en un
 * conjunto grande el de un depósito puede ser 0,004521 %. La suma de todos debe dar
 * 100: el panel lo comprueba y avisa, pero no lo impone, porque durante la carga
 * inicial es normal ir a medias.
 *
 * `occupancy` es VARCHAR + validación (§8). Lo recalcula `RegistryService` desde las
 * relaciones vigentes, salvo los estados que **ninguna relación puede deducir**
 * («en venta», «en remodelación»), que son una decisión humana y no se pisan.
 */
class CreateCopUnitsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_units')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'block_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // Sin FK: auto-referencia + soft-delete (ver cabecera).
            'parent_unit_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 30],
            'kind'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'apartamento'], // apartamento|casa|local|oficina|parqueadero|deposito|otro
            'floor'          => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'area'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'coefficient'    => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
            'bedrooms'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'bathrooms'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'matricula'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'occupancy'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'desocupado'], // desocupado|propietario|arrendado|ocupado|en_venta|remodelacion
            'notes'          => ['type' => 'TEXT', 'null' => true],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at', 'code']);
        $this->forge->addKey(['company_id', 'block_id']);
        $this->forge->addKey('parent_unit_id');
        $this->forge->addKey('occupancy');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('block_id', 'cmp_cop_blocks', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_cop_units');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_units', true);
    }
}
