<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Token de la tarjeta digital pública del vendedor.
 *
 * La tarjeta se comparte por enlace, así que la URL se resuelve por **token
 * aleatorio y no por id**: con el id cualquiera podría recorrer el maestro de
 * vendedores de todas las empresas (mismo criterio que el enlace público de la
 * cotización).
 */
class AddPublicTokenToSellers extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_quo_sellers')) {
            return;
        }

        if (!$this->db->fieldExists('public_token', 'cmp_quo_sellers')) {
            $this->forge->addColumn('cmp_quo_sellers', [
                'public_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            ]);
            // Índice por Forge (portable). No se declara UNIQUE: SQL Server solo
            // admite un NULL en un índice único y la columna nace nula.
            $this->forge->addKey('public_token', false, false, 'cmp_quo_sellers_public_token');
            $this->forge->processIndexes('cmp_quo_sellers');
        }

        // Los vendedores que ya existían estrenan token para que su tarjeta
        // funcione sin tener que volver a guardarlos.
        $rows = $this->db->table('cmp_quo_sellers')->select('id')
            ->groupStart()->where('public_token', null)->orWhere('public_token', '')->groupEnd()
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $this->db->table('cmp_quo_sellers')
                ->where('id', $row['id'])
                ->update(['public_token' => bin2hex(random_bytes(16))]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('public_token', 'cmp_quo_sellers')) {
            $this->forge->dropColumn('cmp_quo_sellers', 'public_token');
        }
    }
}
