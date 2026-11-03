<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Personas del registro: propietarios, residentes, arrendatarios y ocupantes.
 *
 * **Una persona, una fila — el papel lo pone la relación.** No hay tabla de
 * propietarios y otra de residentes: en un conjunto real la misma persona es
 * propietaria del 501, residente del 501 y propietaria del parqueadero 23, y con
 * tablas separadas habría que teclearla tres veces y mantener tres teléfonos que un
 * día dejarían de coincidir. El papel vive en `cmp_cop_unit_people.role`.
 *
 * `user_id` enlaza —opcionalmente— con una cuenta de la plataforma, para cuando el
 * residente entra al portal. Es `SET NULL`: dar de baja la cuenta no borra a la
 * persona del censo.
 *
 * El documento es único por empresa; se comprueba en el modelo y no con un índice
 * UNIQUE porque la tabla usa soft-delete, y un UNIQUE impediría volver a registrar a
 * quien se dio de baja por error.
 */
class CreateCopPeopleTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_people')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'family_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'family_role' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], // jefe|conyuge|hijo|padre|hermano|otro
            'user_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'doc_type'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'CC'], // CC|CE|TI|RC|PA|NIT
            'doc_number'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'first_name'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'last_name'   => ['type' => 'VARCHAR', 'constraint' => 60],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'whatsapp'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'birthdate'   => ['type' => 'DATE', 'null' => true],
            'occupation'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'photo'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'       => ['type' => 'TEXT', 'null' => true],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at', 'last_name']);
        // Buscar por documento es lo que hace la portería veinte veces al día.
        $this->forge->addKey(['company_id', 'doc_number']);
        $this->forge->addKey('family_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('family_id', 'cmp_cop_families', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_cop_people');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_people', true);
    }
}
