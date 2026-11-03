<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $now   = date('Y-m-d H:i:s');
        $roles = [
            ['Super Admin', 'Acceso total al sistema con todos los permisos'],
            ['Admin', 'Administrador con gestión de usuarios y configuración'],
            ['Auditor', 'Acceso de solo lectura a registros de auditoría'],
            ['Usuario', 'Usuario estándar con acceso básico al sistema'],
            ['Usuario invitado', 'Este usuario no puede ver nada'],
        ];

        $table = $this->db->table('roles');
        foreach ($roles as [$name, $desc]) {
            if (!$table->getWhere(['name' => $name])->getRow()) {
                $table->insert(['name' => $name, 'description' => $desc, 'created_at' => $now]);
            }
        }
    }
}
