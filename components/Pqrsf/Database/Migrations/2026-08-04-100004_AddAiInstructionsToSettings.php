<?php

namespace Components\Pqrsf\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Instrucciones propias de la empresa para la IA, y lo que la IA devuelve.
 *
 * Hasta ahora el papel del asistente estaba escrito en el código y lo único
 * ajustable era el tono. Con `ai_instructions` cada empresa escribe su propia
 * plantilla de análisis —con marcadores `{{asunto}}` y `{{descripcion}}`— y
 * puede pedir la respuesta **en JSON**; de ahí salen la categoría, la prioridad
 * sugerida, el resumen, el análisis y las acciones propuestas, que se guardan
 * como **sugerencias** (nunca pisan lo que decida una persona).
 *
 * Portable (§8): TEXT/VARCHAR, sin JSON nativo (las acciones van como TEXT con
 * `json_encode`), todo por Forge y `up()` idempotente.
 */
class AddAiInstructionsToSettings extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_pqr_settings') && !$this->db->fieldExists('ai_instructions', 'cmp_pqr_settings')) {
            $this->forge->addColumn('cmp_pqr_settings', [
                'ai_instructions' => ['type' => 'TEXT', 'null' => true, 'after' => 'ai_tone'],
            ]);
        }

        if ($this->db->tableExists('cmp_pqr_requests') && !$this->db->fieldExists('ai_category', 'cmp_pqr_requests')) {
            $this->forge->addColumn('cmp_pqr_requests', [
                'ai_category' => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => '', 'after' => 'ai_summary'],
                'ai_priority' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '', 'after' => 'ai_category'],
                'ai_analysis' => ['type' => 'TEXT', 'null' => true, 'after' => 'ai_priority'],
                'ai_actions'  => ['type' => 'TEXT', 'null' => true, 'after' => 'ai_analysis'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('ai_instructions', 'cmp_pqr_settings')) {
            $this->forge->dropColumn('cmp_pqr_settings', 'ai_instructions');
        }

        foreach (['ai_category', 'ai_priority', 'ai_analysis', 'ai_actions'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_pqr_requests')) {
                $this->forge->dropColumn('cmp_pqr_requests', $col);
            }
        }
    }
}
