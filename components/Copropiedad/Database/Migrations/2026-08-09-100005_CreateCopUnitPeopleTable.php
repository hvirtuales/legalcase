<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La relación persona ↔ inmueble. **Y, por lo tanto, el historial.**
 *
 * No existe una tabla de «historial de propietarios y residentes» porque sería la
 * copia de esta con las filas viejas: dos sitios donde mirar y la posibilidad de que
 * discrepen. Aquí, cerrar una relación **no la borra** — le pone `end_date` y
 * `is_current = 0` — así que:
 *
 *   · quién vive hoy en el 501  →  `unit_id = X AND is_current = 1`
 *   · quién vivía en 2019       →  el rango de `start_date`/`end_date`
 *   · dónde ha vivido Fulano    →  `person_id = Y` ordenado por fecha
 *
 * Las tres preguntas se contestan con la misma fila, así que no pueden discrepar.
 * (Misma decisión que Networking: «la solicitud ES la conexión».)
 *
 * `role` (VARCHAR + validación, §8) es lo que convierte a una persona en propietario,
 * residente, arrendatario u ocupante. Una misma persona puede tener **dos papeles a la
 * vez** sobre el mismo inmueble —lo normal: propietario *y* residente—, y son dos
 * filas, porque pueden empezar y terminar en fechas distintas (se compra en 2020 y
 * se entra a vivir en 2022).
 *
 * `share` es el porcentaje de propiedad, para los inmuebles con varios dueños. Solo
 * tiene sentido con `role = propietario`; el servicio avisa si los porcentajes
 * vigentes de un inmueble no suman 100.
 */
class CreateCopUnitPeopleTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_unit_people')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unit_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'person_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 20], // propietario|residente|arrendatario|ocupante
            'share'      => ['type' => 'DECIMAL', 'constraint' => '7,4', 'null' => true],
            // El titular: a quien se le notifica y quien firma. Uno por papel.
            'is_main'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'start_date' => ['type' => 'DATE', 'null' => true],
            'end_date'   => ['type' => 'DATE', 'null' => true],
            'is_current' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // «Quién vive hoy aquí» es la consulta caliente del componente.
        $this->forge->addKey(['unit_id', 'is_current', 'deleted_at']);
        $this->forge->addKey(['person_id', 'is_current']);
        $this->forge->addKey(['company_id', 'role']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'cmp_cop_units', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('person_id', 'cmp_cop_people', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cop_unit_people');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_unit_people', true);
    }
}
