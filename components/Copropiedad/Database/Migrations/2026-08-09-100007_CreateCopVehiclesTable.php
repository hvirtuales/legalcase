<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vehículos.
 *
 * Cuelgan del inmueble por el mismo motivo que las mascotas: la portería busca por
 * placa y lo que necesita saber es **a qué puerta pertenece**.
 *
 * `parking_unit_id` apunta al parqueadero —que es otra fila de `cmp_cop_units`, ver
 * esa migración—, y **no lleva FK** porque los inmuebles usan soft-delete y un
 * `SET NULL` casi nunca dispararía; desasignar es explícito, lo hace el servicio.
 * Sin esto no habría forma de contestar «¿de quién es el carro mal parqueado en el
 * puesto 23?», que es literalmente la llamada de las nueve de la noche.
 *
 * `plate` es opcional: una bicicleta no la tiene, y en un conjunto se registran.
 */
class CreateCopVehiclesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_vehicles')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unit_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'person_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // Sin FK: apunta a un inmueble con soft-delete (ver cabecera).
            'parking_unit_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'kind'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'carro'], // carro|camioneta|moto|bicicleta|otro
            'plate'           => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true],
            'brand'           => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'model'           => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'color'           => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'year'            => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            // Calcomanía o tag de acceso que reparte la administración.
            'sticker'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Buscar por placa es LA consulta de la portería.
        $this->forge->addKey(['company_id', 'plate']);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addKey('unit_id');
        $this->forge->addKey('parking_unit_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'cmp_cop_units', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('person_id', 'cmp_cop_people', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_cop_vehicles');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_vehicles', true);
    }
}
