<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Grupos familiares.
 *
 * Un grupo familiar agrupa personas y se ancla a un inmueble (`unit_id`), que es
 * como lo piensa la administración: «la familia del 501». La pertenencia vive en
 * `cmp_cop_people.family_id` — una persona pertenece a un grupo, no a varios — y el
 * parentesco en `family_role`.
 *
 * **`head_person_id` no lleva FK a propósito.** La referencia es circular (la familia
 * apunta a una persona y la persona apunta a su familia), y una FK circular obliga a
 * un orden de creación frágil y a `NULL` temporales al insertar. Es la misma decisión
 * que toma el núcleo con `companies.parent_id`. La integridad la mantiene el servicio,
 * que solo acepta como cabeza a alguien que ya sea miembro.
 */
class CreateCopFamiliesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_families')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unit_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
            // Sin FK: referencia circular con `cmp_cop_people.family_id`.
            'head_person_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'notes'          => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addKey('unit_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cop_families');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_families', true);
    }
}
