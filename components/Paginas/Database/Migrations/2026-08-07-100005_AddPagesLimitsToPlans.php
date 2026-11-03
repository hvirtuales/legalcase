<?php

namespace Components\Paginas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Servicio y cupo del componente Páginas en `plans` (contrato §11).
 *
 *   - pages_enabled: el plan incluye el constructor de páginas (default 1).
 *   - max_pages:     páginas vivas por empresa (0 = ilimitado).
 *
 * Vive en el componente porque las columnas son **suyas**: se instalan y se
 * revierten con él. `plans` es del núcleo, así que esta migración solo AÑADE
 * columnas propias, comprueba que la tabla exista (el núcleo la crea antes) y
 * pregunta por cada columna antes de crearla. Nunca redefine la tabla.
 *
 * El formulario de planes NO hay que tocarlo: `ComponentManager::planFormGroups()`
 * genera el recuadro desde el `plan_flag`/`plan_limits` del Manifest, y
 * `PlanModel` suma estas columnas a sus `allowedFields` en el constructor. Los
 * tres entregables que el §11.4 del contrato pedía editar en el núcleo ya no
 * existen.
 */
class AddPagesLimitsToPlans extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        $fields = [];
        if (!$this->db->fieldExists('pages_enabled', 'plans')) {
            $fields['pages_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_pages', 'plans')) {
            $fields['max_pages'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0]; // 0 = ilimitado
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        foreach (['max_pages', 'pages_enabled'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
