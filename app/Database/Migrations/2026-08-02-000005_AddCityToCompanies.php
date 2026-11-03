<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ciudad de la empresa. Hasta ahora solo existía `address`, pero los documentos
 * (pie de página de las cotizaciones, membretes) necesitan la ciudad como dato
 * independiente. Idempotente y portable.
 */
class AddCityToCompanies extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('city', 'companies')) {
            $this->forge->addColumn('companies', [
                'city' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => '', 'after' => 'address'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('city', 'companies')) {
            $this->forge->dropColumn('companies', 'city');
        }
    }
}
