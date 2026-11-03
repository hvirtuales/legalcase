<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Crea el índice `companies_parent_id` en las bases donde no llegó a crearse.
 *
 * La migración `2026-08-05-000001` lo intentaba, pero preguntaba por la columna
 * recién añadida con `fieldExists()`, que **cachea** los nombres de columna: la
 * lista era la de antes del `ALTER`, así que la condición daba false y el índice
 * se saltaba en silencio. Allí ya se arregló (con `resetDataCache()`), pero una
 * base que ya la aplicó no la vuelve a ejecutar — de ahí esta.
 *
 * Sin el índice, «las empresas de este grupo» (`parent_id = N`) es un recorrido
 * completo de `companies`: con dos empresas da igual, con dos mil no.
 */
class AddParentIndexToCompanies extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('companies') || !$this->db->fieldExists('parent_id', 'companies')) {
            return;
        }

        if (!$this->indexExists('companies', 'companies_parent_id')) {
            $this->db->query('CREATE INDEX companies_parent_id ON companies (parent_id)');
        }
    }

    public function down()
    {
        if ($this->indexExists('companies', 'companies_parent_id')) {
            $this->forge->dropKey('companies', 'companies_parent_id', false);
        }
    }

    /** ¿Existe ya ese índice? (idempotencia; `getIndexData` es portable.) */
    private function indexExists(string $table, string $index): bool
    {
        try {
            foreach ($this->db->getIndexData($table) as $name => $data) {
                if ($name === $index || ($data->name ?? '') === $index) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }
}
