<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        // [key, value, type]
        $defaults = [
            ['app_name', 'Sistema de Autenticación', 'text'],
            ['app_description', 'Sistema completo de autenticación multi-método con gestión de usuarios, roles y permisos', 'text'],
            ['login_image', '', 'image'],
            ['login_background', 'gradient', 'text'],
            ['favicon', '', 'image'],
            ['bg_color_primary', '#e10e30', 'text'],
            ['bg_color_secondary', '#b90b26', 'text'],
            ['theme_color_primary', '#e10e30', 'text'],
            ['public_registration_enabled', '1', 'boolean'],
            ['auth_magic_link_enabled', '1', 'boolean'],
            ['auth_oauth_enabled', '0', 'boolean'],
            ['auth_max_attempts', '5', 'text'],
            ['auth_methods_enabled', json_encode(['password' => true, 'magic_link' => true, 'oauth' => false, 'ldap' => false, 'api_token' => true]), 'text'],
        ];

        $table = $this->db->table('app_settings');
        foreach ($defaults as [$key, $value, $type]) {
            if (!$table->getWhere(['setting_key' => $key])->getRow()) {
                $table->insert(['setting_key' => $key, 'setting_value' => $value, 'setting_type' => $type, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}
