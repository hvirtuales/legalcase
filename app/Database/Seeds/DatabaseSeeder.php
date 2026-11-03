<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder maestro: levanta la app desde cero con datos base usables.
 * Uso: php spark db:seed DatabaseSeeder
 * Todos los sub-seeders son idempotentes (puedes correrlo varias veces).
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call('RoleSeeder');        // roles
        $this->call('PermissionSeeder');  // permisos + asignaciones por rol
        $this->call('AppSettingSeeder');  // settings por defecto
        $this->call('PlanSeeder');        // planes iniciales (Trial, Profesional)
        $this->call('SectorSeeder');      // sectores de arranque (clasificación de empresas)
        $this->call('UserSeeder');        // admin inicial (admin / admin123) con Super Admin
    }
}
