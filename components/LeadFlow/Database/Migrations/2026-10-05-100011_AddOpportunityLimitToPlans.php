<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El cupo de oportunidades en `plans` (contrato §11).
 *
 * **Es la palanca de la membresía premium del §10 del insumo**: cuántas oportunidades
 * abiertas puede reclamar una firma al mes. Va en su propia migración y no colgada de la
 * anterior porque toca una tabla del núcleo, y eso se revisa aparte.
 *
 * `0` = ilimitado, como `max_leads` y `max_profiles`. Y como en todo el componente, **una
 * columna que no existe significa «permitido»**.
 */
class AddOpportunityLimitToPlans extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        if (! $this->db->fieldExists('max_opportunities', 'plans')) {
            $this->forge->addColumn('plans', [
                'max_opportunities' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            ]);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if ($this->db->tableExists('plans') && $this->db->fieldExists('max_opportunities', 'plans')) {
            $this->forge->dropColumn('plans', 'max_opportunities');
            $this->db->resetDataCache();
        }
    }
}
