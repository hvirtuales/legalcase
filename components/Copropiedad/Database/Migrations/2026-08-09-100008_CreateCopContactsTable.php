<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contactos de emergencia.
 *
 * Cuelgan del inmueble (`unit_id`) y, si se conoce, de la persona concreta a la que
 * cubren (`person_id`). Las dos referencias son opcionales pero **al menos una es
 * obligatoria**, y eso lo hace valer el modelo: un contacto de emergencia que no
 * está atado a nadie no sirve para nada a las tres de la mañana.
 *
 * `person_id` es `SET NULL` a propósito: si la persona se da de baja, el contacto se
 * conserva ligado al inmueble en vez de desaparecer.
 *
 * Nota deliberada: el contacto de emergencia **no** es una `cmp_cop_people`. Suele ser
 * alguien de fuera del conjunto —una hermana en otra ciudad, el médico— y meterlo en
 * el censo lo contaría como residente en las estadísticas y lo ofrecería en los
 * selectores de propietario.
 */
class CreateCopContactsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_contacts')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unit_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'person_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'relationship' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'phone'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'alt_phone'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'        => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            // A quién se llama primero.
            'is_primary'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'notes'        => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addKey('unit_id');
        $this->forge->addKey('person_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'cmp_cop_units', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('person_id', 'cmp_cop_people', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_cop_contacts');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_contacts', true);
    }
}
