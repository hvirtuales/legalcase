<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Imagen de portada de la empresa (banner 800×300). Complementa a `logo` y se usa
 * como cabecera en las tarjetas y fichas que muestran a la empresa.
 * Idempotente y portable.
 */
class AddBannerToCompanies extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('banner', 'companies')) {
            $this->forge->addColumn('companies', [
                'banner' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'logo'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('banner', 'companies')) {
            $this->forge->dropColumn('companies', 'banner');
        }
    }
}
