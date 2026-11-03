<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El mapa: un centro comercial, una feria, un campus.
 *
 * `public_token` es aleatorio y no el id, como el resto de enlaces públicos de
 * la plataforma: con el id se podrían recorrer los mapas de todas las empresas.
 * `is_published` apaga la página pública sin perder el token —un QR ya impreso
 * no puede cambiar— ni los datos.
 *
 * Portabilidad (contrato §8): sin ENUM ni JSON nativo; VARCHAR + validación en
 * el modelo, y TEXT + json_encode donde haga falta guardar estructura.
 */
class CreateMapMapsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_map_maps')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'  => ['type' => 'TEXT', 'null' => true],
            'public_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'is_published' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'views'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('public_token');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_map_maps');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_map_maps', true);
    }
}
