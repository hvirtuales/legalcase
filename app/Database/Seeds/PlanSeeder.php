<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run()
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [
            [
                'name' => 'Trial', 'description' => 'Plan de prueba gratuito',
                'max_companies' => 1, 'service_days' => 30, 'price_amount' => 0, 'price_currency' => 'USD', 'is_active' => 1, 'created_at' => $now,
            ],
            [
                'name' => 'Profesional', 'description' => 'Plan profesional anual',
                'max_companies' => 5, 'service_days' => 365, 'price_amount' => 49.00, 'price_currency' => 'USD', 'is_active' => 1, 'created_at' => $now,
            ],
        ];

        $table = $this->db->table('plans');
        foreach ($rows as $row) {
            if (!$table->getWhere(['name' => $row['name']])->getRow()) {
                $table->insert($row);
            }
        }
    }
}
