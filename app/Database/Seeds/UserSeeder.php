<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = $this->db->table('users');

        // Admin inicial (idempotente). ¡Cambia la contraseña tras el primer acceso!
        $admin = $users->getWhere(['username' => 'admin'])->getRow();
        if (!$admin) {
            $users->insert([
                'username'           => 'admin',
                'email'              => 'admin@example.com',
                'password'           => password_hash('admin123', PASSWORD_DEFAULT),
                'first_name'         => 'Super',
                'last_name'          => 'Admin',
                'is_active'          => 1,
                'auth_method'        => 'password',
                'preferred_language' => 'es',
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $userId = $this->db->insertID();
        } else {
            $userId = (int) $admin->id;
        }

        // Asignar rol Super Admin (idempotente)
        $role = $this->db->table('roles')->getWhere(['name' => 'Super Admin'])->getRow();
        if ($role) {
            $ur = $this->db->table('user_roles');
            if (!$ur->getWhere(['user_id' => $userId, 'role_id' => $role->id])->getRow()) {
                $ur->insert(['user_id' => $userId, 'role_id' => $role->id]);
            }
        }
    }
}
