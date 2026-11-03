<?php

namespace Components\LegalCase\Database\Migrations;

use CodeIgniter\Database\Migration;
use Components\LegalCase\Database\Columns;

/**
 * Núcleo del dominio: configuración por despacho, contactos, asuntos, equipo,
 * partes, procesos, actuaciones, plazos y tareas.
 *
 * Decisiones de esquema que valen para todas las migraciones del componente:
 *
 *  · **Asunto ≠ proceso.** El asunto (matter) es la unidad de contratación, trabajo y
 *    comunicación; puede existir sin demanda y tener 0..N procesos.
 *  · **Una sola ruta de borrado en cascada por tabla.** La empresa borra sus asuntos
 *    y sus contactos; el asunto borra sus hijos. Los hijos llevan `company_id`
 *    indexado (es el filtro de aislamiento) pero sin una segunda FK a `companies`:
 *    SQL Server rechaza dos caminos de cascada hacia la misma fila.
 *  · **Las referencias a `users` no llevan FK.** Una tabla con tres columnas de
 *    usuario (responsable, validador, autor) tendría tres caminos `SET NULL` hacia
 *    `users`, que SQL Server tampoco admite; el núcleo borra usuarios con soft-delete,
 *    así que la referencia no queda colgando.
 *  · Idempotente: cada tabla se crea solo si no existe.
 */
class CreateLgcCoreTables extends Migration
{
    use Columns;

    public function up()
    {
        // ---- Configuración por despacho ------------------------------------------
        if (!$this->db->tableExists('cmp_lgc_settings')) {
            $this->forge->addField([
                'id'                   => $this->id(),
                'company_id'           => $this->fk(),
                'matter_prefix'        => $this->str(10, 'LC'),
                'matter_sequence'      => $this->int(0),
                // self = quien redacta puede publicar; reviewer = publica otra persona con lgc.approve
                'approval_policy'      => $this->str(20, 'self'),
                // single = basta una validación; dual = los plazos críticos exigen dos personas
                'deadline_policy'      => $this->str(20, 'single'),
                'default_cadence_days' => $this->int(15),
                'response_sla_hours'   => $this->int(48),
                'portal_enabled'       => $this->flag(1),
                'notify_email'         => $this->flag(1),
                'portal_help'          => $this->text(),
            ] + $this->stamps(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_settings');
        }

        // ---- Contactos (personas naturales o jurídicas) ---------------------------
        if (!$this->db->tableExists('cmp_lgc_contacts')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'kind'       => $this->str(10, 'person'),
                'name'       => $this->str(190),
                'doc_type'   => $this->str(20),
                'doc_number' => $this->str(40),
                'email'      => $this->str(190),
                'phone'      => $this->str(40),
                'address'    => $this->str(255),
                'city'       => $this->str(100),
                'country'    => $this->str(2, 'CO'),
                'locale'     => $this->str(5, 'es'),
                'notes'      => $this->text(),
                'created_by' => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'name']);
            $this->forge->addKey(['company_id', 'doc_number']);
            $this->forge->addKey(['company_id', 'email']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_contacts');
        }

        // ---- Asuntos jurídicos ----------------------------------------------------
        if (!$this->db->tableExists('cmp_lgc_matters')) {
            $this->forge->addField([
                'id'                 => $this->id(),
                'company_id'         => $this->fk(),
                'number'             => $this->str(30),
                'title'              => $this->str(190),
                // Lo que ve el cliente: un nombre comprensible, nunca la estrategia.
                'public_title'       => $this->str(190),
                'specialty'          => $this->str(40),
                'country'            => $this->str(2, 'CO'),
                'jurisdiction'       => $this->str(120),
                'status'             => $this->str(20, 'open'),
                // firm = todo el despacho con lgc.view; team = solo el equipo del asunto
                'visibility'         => $this->str(10, 'firm'),
                'sensitivity'        => $this->str(10, 'normal'),
                'risk_level'         => $this->str(10, 'medium'),
                'client_contact_id'  => $this->fk(true),
                'lead_lawyer_id'     => $this->fk(true),
                // --- interno: nunca se publica ---
                'description'        => $this->text(),
                'objectives'         => $this->text(),
                'strategy'           => $this->text(),
                // --- de cara al cliente ---
                'public_stage'       => $this->str(120),
                'stage_hidden'       => $this->flag(0),
                'opened_at'          => $this->dt(),
                'closed_at'          => $this->dt(),
                'close_reason'       => $this->str(40),
                'next_update_due_at' => $this->dt(),
                'last_published_at'  => $this->dt(),
                'created_by'         => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'number']);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey(['company_id', 'lead_lawyer_id']);
            $this->forge->addKey(['company_id', 'next_update_due_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_matters');
        }

        // ---- Equipo del asunto (participante interno) -------------------------------
        if (!$this->db->tableExists('cmp_lgc_matter_team')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'matter_id'  => $this->fk(),
                'user_id'    => $this->fk(),
                'role'       => $this->str(20, 'collaborator'),
                'created_at' => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['matter_id', 'user_id']);
            $this->forge->addKey(['company_id', 'user_id']);
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_matter_team');
        }

        // ---- Partes del asunto (contactos con un papel) -----------------------------
        if (!$this->db->tableExists('cmp_lgc_participants')) {
            $this->forge->addField([
                'id'            => $this->id(),
                'company_id'    => $this->fk(),
                'matter_id'     => $this->fk(),
                'contact_id'    => $this->fk(),
                'role'          => $this->str(20, 'client'),
                // El acceso al portal es POR ASUNTO: el mismo contacto puede verlo en uno y no en otro.
                'portal_access' => $this->flag(0),
                'notes'         => $this->str(255),
            ] + $this->stamps(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['matter_id', 'contact_id', 'role']);
            $this->forge->addKey(['company_id', 'contact_id']);
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_participants');
        }

        // ---- Procesos (judiciales o administrativos) --------------------------------
        if (!$this->db->tableExists('cmp_lgc_proceedings')) {
            $this->forge->addField([
                'id'             => $this->id(),
                'company_id'     => $this->fk(),
                'matter_id'      => $this->fk(),
                'kind'           => $this->str(20, 'judicial'),
                'filing_number'  => $this->str(60),
                'authority'      => $this->str(190),
                'jurisdiction'   => $this->str(120),
                'procedure_type' => $this->str(120),
                'stage'          => $this->str(120),
                'status'         => $this->str(20, 'active'),
                'started_on'     => $this->date(),
                'ended_on'       => $this->date(),
                'notes'          => $this->text(),
                'created_by'     => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'filing_number']);
            $this->forge->addKey('matter_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_proceedings');
        }

        // ---- Actuaciones / eventos (con su fuente) ----------------------------------
        if (!$this->db->tableExists('cmp_lgc_events')) {
            $this->forge->addField([
                'id'            => $this->id(),
                'company_id'    => $this->fk(),
                'matter_id'     => $this->fk(),
                'proceeding_id' => $this->fk(true),
                'occurred_at'   => $this->dt(),
                'kind'          => $this->str(20, 'other'),
                'title'         => $this->str(190),
                'detail'        => $this->text(),
                'source'        => $this->str(120),
                'source_ref'    => $this->str(190),
                'is_relevant'   => $this->flag(0),
                'created_by'    => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['matter_id', 'occurred_at']);
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_events');
        }

        // ---- Plazos / términos (registro manual con validación) ---------------------
        if (!$this->db->tableExists('cmp_lgc_deadlines')) {
            $this->forge->addField([
                'id'                  => $this->id(),
                'company_id'          => $this->fk(),
                'matter_id'           => $this->fk(),
                'proceeding_id'       => $this->fk(true),
                'event_id'            => $this->fk(true),
                'title'               => $this->str(190),
                'basis'               => $this->text(),
                'starts_on'           => $this->date(),
                'due_at'              => $this->dt(),
                'is_critical'         => $this->flag(0),
                'status'              => $this->str(20, 'to_validate'),
                'assigned_to'         => $this->fk(true),
                'validated_by'        => $this->fk(true),
                'validated_at'        => $this->dt(),
                'second_validated_by' => $this->fk(true),
                'second_validated_at' => $this->dt(),
                'done_at'             => $this->dt(),
                'created_by'          => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status', 'due_at']);
            $this->forge->addKey('matter_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_deadlines');
        }

        // ---- Tareas -------------------------------------------------------------------
        if (!$this->db->tableExists('cmp_lgc_tasks')) {
            $this->forge->addField([
                'id'          => $this->id(),
                'company_id'  => $this->fk(),
                'matter_id'   => $this->fk(),
                'kind'        => $this->str(20, 'task'),
                'title'       => $this->str(190),
                'detail'      => $this->text(),
                'due_at'      => $this->dt(),
                'assigned_to' => $this->fk(true),
                'status'      => $this->str(20, 'open'),
                // Marca de idempotencia de las tareas que crea el sistema (p. ej. «toca actualizar»).
                'ref'         => $this->str(80, null, true),
                'done_at'     => $this->dt(),
                'created_by'  => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status', 'due_at']);
            $this->forge->addKey(['company_id', 'ref']);
            $this->forge->addKey('matter_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_tasks');
        }
    }

    public function down()
    {
        foreach ([
            'cmp_lgc_tasks', 'cmp_lgc_deadlines', 'cmp_lgc_events', 'cmp_lgc_proceedings',
            'cmp_lgc_participants', 'cmp_lgc_matter_team', 'cmp_lgc_matters', 'cmp_lgc_contacts', 'cmp_lgc_settings',
        ] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
