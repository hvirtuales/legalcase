<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Todo lo que cuelga de un lead: historial de etapas, respuestas de formulario,
 * actividades, notas, consultas, documentos, comunicaciones, historial de score
 * y la línea de tiempo.
 *
 * Todas llevan `company_id` además de `lead_id`: filtrar por empresa sin pasar
 * por el lead es lo que permite los listados globales (actividades vencidas,
 * agenda del día) con una sola condición que no se puede olvidar.
 *
 * `lead_id` es CASCADE en la base, pero el lead usa soft-delete y **un
 * soft-delete no dispara el CASCADE**: la limpieza la hace `LeadService::delete()`.
 */
class CreateLflLeadDetailTables extends Migration
{
    private function base(): array
    {
        return [
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'lead_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ];
    }

    private function user(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
    }

    private function dt(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    private function finish(string $table, array $userCols = []): void
    {
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'lead_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lead_id', 'cmp_lfl_leads', 'id', 'CASCADE', 'CASCADE');
        foreach ($userCols as $col) {
            $this->forge->addForeignKey($col, 'users', 'id', 'SET NULL', 'CASCADE');
        }
        $this->forge->createTable($table);
    }

    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_stage_history')) {
            $this->forge->addField($this->base() + [
                'from_stage_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'to_stage_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'user_id'             => $this->user(),
                'seconds_in_previous' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'          => $this->dt(),
            ]);
            $this->finish('cmp_lfl_stage_history', ['user_id']);
        }

        if (! $this->db->tableExists('cmp_lfl_submissions')) {
            $this->forge->addField($this->base() + [
                'form_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'answers'     => ['type' => 'TEXT', 'null' => true],
                'labels'      => ['type' => 'TEXT', 'null' => true], // instantánea de las etiquetas
                'is_complete' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'channel'     => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'internal'], // public|internal
                'user_id'     => $this->user(),
                'ip'          => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at'  => $this->dt(),
            ]);
            $this->forge->addForeignKey('form_id', 'cmp_lfl_forms', 'id', 'SET NULL', 'CASCADE');
            $this->finish('cmp_lfl_submissions', ['user_id']);
        }

        if (! $this->db->tableExists('cmp_lfl_activities')) {
            $this->forge->addField($this->base() + [
                'assigned_user_id' => $this->user(),
                'activity_type'    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'follow_up'],
                'title'            => ['type' => 'VARCHAR', 'constraint' => 150],
                'description'      => ['type' => 'TEXT', 'null' => true],
                'due_at'           => $this->dt(),
                'completed_at'     => $this->dt(),
                'status'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pending'], // pending|done|cancelled
                'priority'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'medium'],
                'created_by'       => $this->user(),
                'created_at'       => $this->dt(),
                'updated_at'       => $this->dt(),
                'deleted_at'       => $this->dt(),
            ]);
            $this->forge->addKey(['company_id', 'status', 'due_at']);
            $this->finish('cmp_lfl_activities', ['assigned_user_id', 'created_by']);
        }

        if (! $this->db->tableExists('cmp_lfl_notes')) {
            $this->forge->addField($this->base() + [
                'body'            => ['type' => 'TEXT'],
                'is_transferable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'      => $this->user(),
                'created_at'      => $this->dt(),
                'updated_at'      => $this->dt(),
                'deleted_at'      => $this->dt(),
            ]);
            $this->finish('cmp_lfl_notes', ['created_by']);
        }

        if (! $this->db->tableExists('cmp_lfl_appointments')) {
            $this->forge->addField($this->base() + [
                'lawyer_user_id'   => $this->user(),
                'scheduled_at'     => ['type' => 'DATETIME'],
                'duration_min'     => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
                'mode'             => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'video'], // in_person|video|phone
                'location'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'scheduled'],
                'result'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'notes'            => ['type' => 'TEXT', 'null' => true],
                'reschedule_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_by'       => $this->user(),
                'created_at'       => $this->dt(),
                'updated_at'       => $this->dt(),
                'deleted_at'       => $this->dt(),
            ]);
            $this->forge->addKey(['company_id', 'scheduled_at']);
            $this->finish('cmp_lfl_appointments', ['lawyer_user_id', 'created_by']);
        }

        if (! $this->db->tableExists('cmp_lfl_documents')) {
            $this->forge->addField($this->base() + [
                'document_type_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'original_name'    => ['type' => 'VARCHAR', 'constraint' => 255],
                'storage_key'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'mime_type'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'size'             => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'hash'             => ['type' => 'VARCHAR', 'constraint' => 64],
                'is_authorized'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'source'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'internal'], // internal|public
                'uploaded_by'      => $this->user(),
                'created_at'       => $this->dt(),
                'updated_at'       => $this->dt(),
                'deleted_at'       => $this->dt(),
            ]);
            $this->forge->addForeignKey('document_type_id', 'cmp_lfl_document_types', 'id', 'SET NULL', 'CASCADE');
            $this->finish('cmp_lfl_documents', ['uploaded_by']);
        }

        if (! $this->db->tableExists('cmp_lfl_communications')) {
            $this->forge->addField($this->base() + [
                'channel'      => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'call'], // call|email|whatsapp|sms
                'direction'    => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'out'],   // out|in
                'subject'      => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'body'         => ['type' => 'TEXT', 'null' => true],
                'outcome'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'duration_sec' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'logged'], // logged|sent|failed
                'error'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_by'   => $this->user(),
                'created_at'   => $this->dt(),
                'updated_at'   => $this->dt(),
            ]);
            $this->finish('cmp_lfl_communications', ['created_by']);
        }

        if (! $this->db->tableExists('cmp_lfl_score_history')) {
            $this->forge->addField($this->base() + [
                'score'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'previous'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'breakdown'  => ['type' => 'TEXT', 'null' => true],
                'reason'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'created_at' => $this->dt(),
            ]);
            $this->finish('cmp_lfl_score_history');
        }

        if (! $this->db->tableExists('cmp_lfl_timeline')) {
            $this->forge->addField($this->base() + [
                'type'       => ['type' => 'VARCHAR', 'constraint' => 30],
                'summary'    => ['type' => 'VARCHAR', 'constraint' => 255],
                'meta'       => ['type' => 'TEXT', 'null' => true],
                'user_id'    => $this->user(),
                'created_at' => $this->dt(),
            ]);
            $this->finish('cmp_lfl_timeline', ['user_id']);
        }
    }

    public function down()
    {
        foreach (['cmp_lfl_timeline', 'cmp_lfl_score_history', 'cmp_lfl_communications', 'cmp_lfl_documents',
            'cmp_lfl_appointments', 'cmp_lfl_notes', 'cmp_lfl_activities', 'cmp_lfl_submissions',
            'cmp_lfl_stage_history'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
