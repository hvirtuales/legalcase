<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Redes sociales de la empresa.
 *
 * Van en `companies` (núcleo) y no en un componente: son datos de la empresa,
 * como el sitio web o el WhatsApp, y cualquier componente puede mostrarlos
 * (hoy los usa la tarjeta digital del vendedor).
 */
class AddSocialLinksToCompanies extends Migration
{
    /** @var list<string> */
    private array $columns = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];

    public function up()
    {
        if (!$this->db->tableExists('companies')) {
            return;
        }

        $fields = [];
        foreach ($this->columns as $col) {
            if (!$this->db->fieldExists($col, 'companies')) {
                $fields[$col] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            }
        }

        if ($fields) {
            $this->forge->addColumn('companies', $fields);
        }
    }

    public function down()
    {
        foreach ($this->columns as $col) {
            if ($this->db->fieldExists($col, 'companies')) {
                $this->forge->dropColumn('companies', $col);
            }
        }
    }
}
