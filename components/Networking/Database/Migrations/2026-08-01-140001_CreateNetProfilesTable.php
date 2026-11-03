<?php

namespace Components\Networking\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perfil compartible de cada usuario en la red. No duplica nombre/correo/teléfono
 * (eso vive en `users`): solo lo propio de la red y las preferencias de privacidad.
 * Portable (VARCHAR/TEXT/TINYINT, todo por Forge) e idempotente.
 */
class CreateNetProfilesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_net_profiles')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'share_profile' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'headline'      => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'position'      => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
            'company_name'  => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'city'          => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'bio'           => ['type' => 'TEXT', 'null' => true],
            'interests'     => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'show_email'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'show_phone'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addKey('share_profile');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_net_profiles');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_net_profiles', true);
    }
}
