<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nivel intermedio «tenant»: un centro comercial o una cámara de comercio que
 * AGRUPA empresas.
 *
 * No se crea una tabla `tenants` propia: un tenant tiene nombre, NIT, logo,
 * color de marca, canales, plan y suscripción — exactamente lo que `companies`
 * ya sabe guardar. Duplicarlo obligaría a un segundo juego de pantallas para lo
 * mismo y a tocar el aislamiento de los componentes. Con dos columnas sale casi
 * gratis:
 *
 *   - `type`      'company' (por defecto, lo de siempre) | 'tenant'
 *   - `parent_id` a qué tenant pertenece la empresa. NULL = empresa suelta.
 *
 * **Un solo nivel de anidación**: un tenant no cuelga de otro. La regla se
 * valida al guardar (`CompanyModel::canBeParent()`); sin ella, el día que
 * alguien encadene tres niveles habría que reescribir todas las consultas de
 * alcance.
 *
 * **Sin FK en `parent_id`, a propósito.** `companies` usa soft-delete, así que
 * un `ON DELETE SET NULL` no llegaría a dispararse casi nunca (es el mismo
 * caso ya documentado de las categorías de Servicios). Descolgar a las hijas
 * es responsabilidad explícita de `TenantController::delete()`. Queda el índice,
 * que es lo que de verdad usan las consultas de alcance.
 */
class AddTenantToCompanies extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('companies')) {
            return;
        }

        $fields = [];

        if (!$this->db->fieldExists('type', 'companies')) {
            // VARCHAR + validación en el modelo (nada de ENUM: portabilidad §8).
            $fields['type'] = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'company'];
        }
        if (!$this->db->fieldExists('parent_id', 'companies')) {
            $fields['parent_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
        }
        if (!$this->db->fieldExists('can_create_children', 'companies')) {
            // ¿El administrador del tenant puede dar de alta empresas por su
            // cuenta (consumiendo su cupo)? Por defecto NO: quien responde por
            // los datos de una empresa nueva es quien la crea.
            $fields['can_create_children'] = ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0];
        }

        if ($fields) {
            $this->forge->addColumn('companies', $fields);
            // `fieldExists()` cachea los nombres de columna: sin esto, el
            // `fieldExists('parent_id')` de abajo sigue devolviendo false con la
            // lista de ANTES del ALTER y el índice no se creaba nunca.
            $this->db->resetDataCache();
        }

        // Índice para el alcance: «las hijas de este tenant».
        if ($this->db->fieldExists('parent_id', 'companies') && !$this->indexExists('companies', 'companies_parent_id')) {
            $this->db->query('CREATE INDEX companies_parent_id ON companies (parent_id)');
        }
    }

    public function down()
    {
        if ($this->indexExists('companies', 'companies_parent_id')) {
            $this->forge->dropKey('companies', 'companies_parent_id', false);
        }
        foreach (['type', 'parent_id', 'can_create_children'] as $col) {
            if ($this->db->fieldExists($col, 'companies')) {
                $this->forge->dropColumn('companies', $col);
            }
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
