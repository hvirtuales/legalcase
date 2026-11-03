<?php

namespace Components\LegalCase\Database\Migrations;

use CodeIgniter\Database\Migration;
use Components\LegalCase\Database\Columns;

/**
 * LegalCase Connect: plan de comunicación, actualizaciones al cliente y mensajería.
 *
 * · `cmp_lgc_comm_plans` — un plan por asunto (cadencia, SLA de respuesta,
 *   responsable). Sus fechas son **compromisos de comunicación**, no plazos
 *   judiciales: viven en otra tabla y en otra pantalla a propósito (§9.5).
 * · `cmp_lgc_updates` — la actualización publicable. Estados (§8.5):
 *   draft → pending_review → approved → published → superseded | revoked.
 *   Un texto publicado no se edita: se crea otra versión que lo reemplaza
 *   (`supersedes_id`) y la anterior queda en el historial.
 * · `cmp_lgc_threads` + `cmp_lgc_messages` — conversación por asunto con estado
 *   de ticket y hora objetivo de primera respuesta (§6.8).
 */
class CreateLgcConnectTables extends Migration
{
    use Columns;

    public function up()
    {
        if (!$this->db->tableExists('cmp_lgc_comm_plans')) {
            $this->forge->addField([
                'id'                 => $this->id(),
                'company_id'         => $this->fk(),
                'matter_id'          => $this->fk(),
                // periodic | on_event | hybrid | on_request
                'mode'               => $this->str(20, 'hybrid'),
                'cadence_days'       => $this->int(15),
                'response_sla_hours' => $this->int(48),
                'next_update_due_at' => $this->dt(),
                'owner_id'           => $this->fk(true),
                'is_active'          => $this->flag(1),
                'notes'              => $this->str(255),
            ] + $this->stamps(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('matter_id');
            $this->forge->addKey(['company_id', 'is_active', 'next_update_due_at']);
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_comm_plans');
        }

        if (!$this->db->tableExists('cmp_lgc_updates')) {
            $this->forge->addField([
                'id'              => $this->id(),
                'company_id'      => $this->fk(),
                'matter_id'       => $this->fk(),
                // regular | no_news | welcome | hearing | closing
                'kind'            => $this->str(20, 'regular'),
                'status'          => $this->str(20, 'draft'),
                'title'           => $this->str(190),
                // Estructura de lenguaje claro (§6.6)
                'what_happened'   => $this->text(),
                'what_it_means'   => $this->text(),
                'what_we_do'      => $this->text(),
                'client_action'   => $this->text(),
                'next_update_at'  => $this->dt(),
                'stage_label'     => $this->str(120),
                // «Sin novedades» solo con una consulta verificable (§6.13-4)
                'verified_source' => $this->str(190),
                'verified_at'     => $this->dt(),
                'version'         => $this->int(1),
                'supersedes_id'   => $this->fk(true),
                'author_id'       => $this->fk(true),
                'submitted_at'    => $this->dt(),
                'reviewed_by'     => $this->fk(true),
                'reviewed_at'     => $this->dt(),
                'review_note'     => $this->str(255),
                'published_by'    => $this->fk(true),
                'published_at'    => $this->dt(),
                'revoked_by'      => $this->fk(true),
                'revoked_at'      => $this->dt(),
                'revoke_reason'   => $this->str(255),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['matter_id', 'status']);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_updates');
        }

        if (!$this->db->tableExists('cmp_lgc_threads')) {
            $this->forge->addField([
                'id'                    => $this->id(),
                'company_id'            => $this->fk(),
                'matter_id'             => $this->fk(),
                'subject'               => $this->str(190),
                // new | assigned | waiting_client | answered | closed
                'status'                => $this->str(20, 'new'),
                'priority'              => $this->str(10, 'normal'),
                'assigned_to'           => $this->fk(true),
                'opened_by_account'     => $this->fk(true),
                'opened_by_user'        => $this->fk(true),
                'first_response_due_at' => $this->dt(),
                'first_response_at'     => $this->dt(),
                'last_message_at'       => $this->dt(),
                'closed_at'             => $this->dt(),
            ] + $this->stamps(false));
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey('matter_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_threads');
        }

        if (!$this->db->tableExists('cmp_lgc_messages')) {
            $this->forge->addField([
                'id'                => $this->id(),
                'company_id'        => $this->fk(),
                'matter_id'         => $this->fk(),
                'thread_id'         => $this->fk(),
                // in = del cliente; out = del despacho
                'direction'         => $this->str(3, 'in'),
                'body'              => $this->text(),
                'author_user_id'    => $this->fk(true),
                'author_account_id' => $this->fk(true),
                // Acuse automático: administrativo, nunca asesoría
                'is_auto'           => $this->flag(0),
                'created_at'        => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['thread_id', 'created_at']);
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('thread_id', 'cmp_lgc_threads', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_messages');
        }
    }

    public function down()
    {
        foreach (['cmp_lgc_messages', 'cmp_lgc_threads', 'cmp_lgc_updates', 'cmp_lgc_comm_plans'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
