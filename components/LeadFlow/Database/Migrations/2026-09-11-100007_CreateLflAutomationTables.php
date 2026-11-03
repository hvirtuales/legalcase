<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Automatizaciones (§19): DISPARADOR → CONDICIONES → ACCIONES.
 *
 * Las condiciones y las acciones son **TEXT con JSON** (§8, sin JSON nativo) y se
 * reconstruyen contra `AutomationRegistry` al guardar, igual que el árbol de un
 * formulario: lo que llega del navegador nunca se guarda tal cual.
 *
 * `cmp_lfl_automation_runs.ref` es la llave de «una sola vez»: con
 * `once_per_lead` vale `lead:<id>` (o `activity:<id>` para las vencidas) y el
 * UNIQUE impide que la misma automatización se repita sobre lo mismo. Cuando la
 * automatización puede repetirse, `ref` va a NULL — y un UNIQUE admite varios NULL,
 * así que las dos formas conviven en el mismo índice.
 */
class CreateLflAutomationTables extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_automations')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'name'          => ['type' => 'VARCHAR', 'constraint' => 120],
                'trigger_event' => ['type' => 'VARCHAR', 'constraint' => 30],
                'match_mode'    => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'all'], // all|any
                'conditions'    => ['type' => 'TEXT', 'null' => true],
                'actions'       => ['type' => 'TEXT', 'null' => true],
                'once_per_lead' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'rr_index'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0], // reparto por turnos
                'runs'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'last_run_at'   => ['type' => 'DATETIME', 'null' => true],
                'created_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'trigger_event', 'is_active']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_automations');
        }

        if (! $this->db->tableExists('cmp_lfl_automation_runs')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'automation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'lead_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'ref'           => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'status'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ok'], // ok|error
                'actions'       => ['type' => 'TEXT', 'null' => true],
                'message'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['automation_id', 'ref']);
            $this->forge->addKey(['company_id', 'created_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('automation_id', 'cmp_lfl_automations', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('lead_id', 'cmp_lfl_leads', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_automation_runs');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_automation_runs', true);
        $this->forge->dropTable('cmp_lfl_automations', true);
    }
}
