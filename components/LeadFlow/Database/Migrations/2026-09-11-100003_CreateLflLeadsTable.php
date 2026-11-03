<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La entidad Lead (§8 del documento de requerimientos).
 *
 * Además de los campos del documento lleva tres columnas **normalizadas**
 * (`email_norm`, `phone_norm`, `whatsapp_norm`) indexadas por empresa: son las
 * que usa la prevención de duplicados (§23). Normalizar al guardar y no al
 * buscar es lo que permite comparar con un índice en vez de recorrer la tabla.
 *
 * `status` (open|won|lost|converted) se deriva del tipo de la etapa; se guarda
 * desnormalizado porque es por lo que más se filtra.
 */
class CreateLflLeadsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_lfl_leads')) {
            return;
        }

        $fk  = static fn (bool $null = true) => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
        $str = static fn (int $n) => ['type' => 'VARCHAR', 'constraint' => $n, 'null' => true];
        $dt  = ['type' => 'DATETIME', 'null' => true];

        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'             => $fk(false),
            'first_name'             => ['type' => 'VARCHAR', 'constraint' => 80],
            'middle_name'            => $str(80),
            'last_name'              => ['type' => 'VARCHAR', 'constraint' => 80],
            'email'                  => $str(150),
            'email_norm'             => $str(150),
            'phone'                  => $str(30),
            'phone_norm'             => $str(20),
            'whatsapp'               => $str(30),
            'whatsapp_norm'          => $str(20),
            'country'                => $str(2),
            'city'                   => $str(80),
            'preferred_language'     => $str(5),
            'service_id'             => $fk(),
            'source_id'              => $fk(),
            'campaign_id'            => $fk(),
            'form_id'                => $fk(),
            'assigned_user_id'       => $fk(),
            'lawyer_user_id'         => $fk(),
            'stage_id'               => $fk(),
            'stage_entered_at'       => $dt,
            'priority'               => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'low'], // low|medium|high
            'priority_manual'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'score'                  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'open'], // open|won|lost|converted
            'lost_reason'            => $str(40),
            'lost_note'              => $str(255),
            'potential_value'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'won_value'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'requested_consultation' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'consent_at'             => $dt,
            'consent_ip'             => $str(45),
            'first_response_at'      => $dt,
            'last_contact_at'        => $dt,
            'last_inbound_at'        => $dt,
            'next_activity_at'       => $dt,
            'won_at'                 => $dt,
            'converted_at'           => $dt,
            'utm_source'             => $str(150),
            'utm_medium'             => $str(150),
            'utm_campaign'           => $str(150),
            'utm_content'            => $str(150),
            'utm_term'               => $str(150),
            'landing_page'           => $str(255),
            'referrer'               => $str(255),
            'summary'                => ['type' => 'TEXT', 'null' => true],
            'created_by'             => $fk(),
            'created_at'             => $dt,
            'updated_at'             => $dt,
            'deleted_at'             => $dt,
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addKey(['company_id', 'stage_id']);
        $this->forge->addKey(['company_id', 'assigned_user_id']);
        $this->forge->addKey(['company_id', 'email_norm']);
        $this->forge->addKey(['company_id', 'phone_norm']);
        $this->forge->addKey(['company_id', 'whatsapp_norm']);
        $this->forge->addKey(['company_id', 'created_at']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('service_id', 'cmp_lfl_services', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('source_id', 'cmp_lfl_sources', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('campaign_id', 'cmp_lfl_campaigns', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('form_id', 'cmp_lfl_forms', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('stage_id', 'cmp_lfl_stages', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('assigned_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('lawyer_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_lfl_leads');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_leads', true);
    }
}
