<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catálogos de la firma: ajustes, equipo comercial, etapas del pipeline,
 * fuentes, campañas, servicios jurídicos, tipos de documento y reglas de scoring.
 *
 * Cada tabla se crea con su propia guarda `tableExists()`: la migración se puede
 * reejecutar (reinstalación, reparación) sin abortar en la primera que ya exista.
 *
 * `cmp_lfl_services.form_id` va **sin FK** a propósito: los formularios se crean
 * en la migración siguiente y el formulario apunta a su vez al servicio; la
 * referencia circular se resuelve en el modelo, no en la base.
 */
class CreateLflCatalogTables extends Migration
{
    private function ts(bool $soft = true): array
    {
        $f = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        if ($soft) {
            $f['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        return $f;
    }

    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
    }

    public function up()
    {
        // Ajustes (1 fila por empresa)
        if (! $this->db->tableExists('cmp_lfl_settings')) {
            $this->forge->addField([
                'id'               => $this->id(),
                'company_id'       => $this->fk(),
                'currency'         => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],
                'no_response_days' => ['type' => 'INT', 'constraint' => 11, 'default' => 7],
                'consent_text'     => ['type' => 'TEXT', 'null' => true],
                'public_intro'     => ['type' => 'TEXT', 'null' => true],
            ] + $this->ts(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_settings');
        }

        // Equipo comercial: la función de cada usuario DENTRO de la firma
        if (! $this->db->tableExists('cmp_lfl_members')) {
            $this->forge->addField([
                'id'                 => $this->id(),
                'company_id'         => $this->fk(),
                'user_id'            => $this->fk(),
                'role'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'advisor'], // supervisor|advisor|lawyer|auditor
                'docketwise_user_id' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'user_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_members');
        }

        // Etapas del pipeline (configurables por firma)
        if (! $this->db->tableExists('cmp_lfl_stages')) {
            $this->forge->addField([
                'id'          => $this->id(),
                'company_id'  => $this->fk(),
                'name'        => ['type' => 'VARCHAR', 'constraint' => 80],
                'code'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], // código de sistema
                'kind'        => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'open'], // open|won|converted|lost
                'sort'        => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'probability' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'color'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'secondary'],
                'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'sort']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_stages');
        }

        // Fuentes (Web, Meta, Google, WhatsApp, Referidos…)
        if (! $this->db->tableExists('cmp_lfl_sources')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'kind'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'other'], // web|meta|google|whatsapp|referral|other
                'utm_source' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'utm_source']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_sources');
        }

        // Campañas
        if (! $this->db->tableExists('cmp_lfl_campaigns')) {
            $this->forge->addField([
                'id'           => $this->id(),
                'company_id'   => $this->fk(),
                'source_id'    => $this->fk(true),
                'name'         => ['type' => 'VARCHAR', 'constraint' => 120],
                'utm_campaign' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'budget'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
                'starts_on'    => ['type' => 'DATE', 'null' => true],
                'ends_on'      => ['type' => 'DATE', 'null' => true],
                'notes'        => ['type' => 'TEXT', 'null' => true],
                'is_active'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'utm_campaign']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('source_id', 'cmp_lfl_sources', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_campaigns');
        }

        // Servicios jurídicos (visa familiar, asilo, ciudadanía…)
        if (! $this->db->tableExists('cmp_lfl_services')) {
            $this->forge->addField([
                'id'               => $this->id(),
                'company_id'       => $this->fk(),
                'name'             => ['type' => 'VARCHAR', 'constraint' => 120],
                'description'      => ['type' => 'TEXT', 'null' => true],
                'is_priority'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'form_id'          => $this->fk(true),
                'owner_user_id'    => $this->fk(true),
                'matter_type_id'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'matter_type_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'sort'             => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'sort']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_services');
        }

        // Tipos de documento preliminar
        if (! $this->db->tableExists('cmp_lfl_document_types')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'sort'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_document_types');
        }

        // Reglas de scoring (criterio fijo en código, puntos por firma)
        if (! $this->db->tableExists('cmp_lfl_scoring_rules')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'criterion'  => ['type' => 'VARCHAR', 'constraint' => 40],
                'points'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'criterion']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_scoring_rules');
        }
    }

    public function down()
    {
        foreach (['cmp_lfl_scoring_rules', 'cmp_lfl_document_types', 'cmp_lfl_services', 'cmp_lfl_campaigns',
            'cmp_lfl_sources', 'cmp_lfl_stages', 'cmp_lfl_members', 'cmp_lfl_settings'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
