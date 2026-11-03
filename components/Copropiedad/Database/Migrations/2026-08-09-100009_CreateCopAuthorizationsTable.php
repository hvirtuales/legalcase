<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Autorizaciones especiales: permisos con nombre, fecha y quien responde por ellos.
 *
 * Es lo que hoy vive en un cuaderno en la portería: «al señor del 302 le pueden
 * subir el mercado», «la abuela del 705 puede recoger al niño», «el 1104 muda el
 * sábado de 8 a 12». Aquí queda con **quién la otorgó**, **desde y hasta cuándo** y
 * si sigue viva.
 *
 * La persona autorizada puede estar **dentro o fuera** del censo, y por eso hay dos
 * vías: `person_id` si es alguien registrado, o los tres campos `authorized_*` si es
 * un tercero. Obligar a censar a la niñera para poder autorizarla habría llenado el
 * registro de residentes que no lo son.
 *
 * `status` (VARCHAR + validación, §8) distingue **revocada** de **vencida**: la
 * primera es una decisión —alguien la quitó— y la segunda es el calendario. Se
 * separan porque en una reclamación no es lo mismo.
 */
class CreateCopAuthorizationsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_authorizations')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unit_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'kind'             => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => 'ingreso'], // ingreso|recogida_menor|visitante_frecuente|mudanza|remodelacion|mascota|parqueadero|domicilio|otro
            // Autorizado: o alguien del censo…
            'person_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // …o un tercero, con sus datos sueltos.
            'authorized_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'authorized_doc'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'authorized_phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            // Quién responde por la autorización (el titular del inmueble).
            'granted_by_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'start_date'       => ['type' => 'DATE', 'null' => true],
            'end_date'         => ['type' => 'DATE', 'null' => true],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'vigente'], // vigente|vencida|revocada
            'details'          => ['type' => 'TEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'status', 'deleted_at']);
        $this->forge->addKey('unit_id');
        $this->forge->addKey('person_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'cmp_cop_units', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('person_id', 'cmp_cop_people', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('granted_by_id', 'cmp_cop_people', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_cop_authorizations');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_authorizations', true);
    }
}
