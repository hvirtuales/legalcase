<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mascotas.
 *
 * Cuelgan del **inmueble** y no de la persona: el perro se queda en el 501 aunque
 * cambie el residente que lo pasea, y a la administración lo que le importa —quién
 * responde por él, a qué puerta llamar— es la puerta. `person_id` señala al
 * responsable cuando se sabe, y es `SET NULL` para que dar de baja a una persona no
 * borre la mascota del censo.
 *
 * `is_dangerous` marca los ejemplares de manejo especial, que en Colombia (Ley 2054
 * de 2020) obligan a póliza y bozal en zonas comunes: es lo que la portería necesita
 * ver de un vistazo, así que es una bandera y no un texto en las notas.
 */
class CreateCopPetsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_pets')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unit_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'person_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 60],
            'species'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'perro'], // perro|gato|ave|pez|roedor|reptil|otro
            'breed'           => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'color'           => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'size'            => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true], // pequeno|mediano|grande
            'birthdate'       => ['type' => 'DATE', 'null' => true],
            'chip'            => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'vaccine_date'    => ['type' => 'DATE', 'null' => true],
            'vaccine_expires' => ['type' => 'DATE', 'null' => true],
            'is_dangerous'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'photo'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addKey('unit_id');
        $this->forge->addKey('person_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'cmp_cop_units', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('person_id', 'cmp_cop_people', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_cop_pets');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_pets', true);
    }
}
