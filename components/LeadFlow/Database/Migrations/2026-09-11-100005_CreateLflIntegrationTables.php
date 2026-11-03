<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La capa de integración (§20, §21, §37): la app OAuth de la plataforma, la
 * conexión de cada firma, los ids externos, la bitácora, las conversiones, la
 * caché de contactos de Docketwise y el almacén del adaptador simulado.
 *
 * - `cmp_lfl_providers` es **global a propósito** (sin `company_id`): una sola app
 *   OAuth registrada ante Docketwise para toda la plataforma; cada firma autoriza
 *   su propia cuenta y guarda sus tokens en `cmp_lfl_integrations` (cifrados).
 * - `cmp_lfl_conversions.lead_id` es **UNIQUE**: es la llave de idempotencia. Un
 *   segundo clic o un reintento encuentran la fila y continúan; no pueden abrir
 *   otra conversión del mismo lead. No usa soft-delete, así el UNIQUE es seguro.
 * - `cmp_lfl_ext_contacts` existe porque la API v1 **no permite buscar contactos**
 *   por email ni teléfono: se descarga la lista bajo demanda y se compara aquí.
 */
class CreateLflIntegrationTables extends Migration
{
    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function company(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
    }

    private function dt(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_providers')) {
            $this->forge->addField([
                'id'            => $this->id(),
                'provider'      => ['type' => 'VARCHAR', 'constraint' => 30],
                'client_id'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'client_secret' => ['type' => 'TEXT', 'null' => true], // cifrado
                'redirect_uri'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'scope'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'public write'],
                'mode'          => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'simulated'], // simulated|live
                'created_at'    => $this->dt(),
                'updated_at'    => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('provider');
            $this->forge->createTable('cmp_lfl_providers');
        }

        if (! $this->db->tableExists('cmp_lfl_integrations')) {
            $this->forge->addField([
                'id'                 => $this->id(),
                'company_id'         => $this->company(),
                'provider'           => ['type' => 'VARCHAR', 'constraint' => 30],
                'mode'               => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'simulated'],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'disconnected'], // disconnected|pending|connected|error
                'access_token'       => ['type' => 'TEXT', 'null' => true],  // cifrado
                'refresh_token'      => ['type' => 'TEXT', 'null' => true],  // cifrado
                'expires_at'         => $this->dt(),
                'oauth_state'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'connected_at'       => $this->dt(),
                'connected_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'contacts_synced_at' => $this->dt(),
                'last_error'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'         => $this->dt(),
                'updated_at'         => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'provider']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('connected_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_integrations');
        }

        if (! $this->db->tableExists('cmp_lfl_mappings')) {
            $this->forge->addField([
                'id'          => $this->id(),
                'company_id'  => $this->company(),
                'provider'    => ['type' => 'VARCHAR', 'constraint' => 30],
                'entity_type' => ['type' => 'VARCHAR', 'constraint' => 20], // contact|matter|summary|note|document
                'local_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'external_id' => ['type' => 'VARCHAR', 'constraint' => 64],
                'created_at'  => $this->dt(),
                'updated_at'  => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'provider', 'entity_type', 'local_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_mappings');
        }

        if (! $this->db->tableExists('cmp_lfl_integration_logs')) {
            $this->forge->addField([
                'id'               => $this->id(),
                'company_id'       => $this->company(),
                'provider'         => ['type' => 'VARCHAR', 'constraint' => 30],
                'lead_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'action'           => ['type' => 'VARCHAR', 'constraint' => 40],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ok'], // ok|error
                'http_code'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'message'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'request_summary'  => ['type' => 'TEXT', 'null' => true],
                'response_summary' => ['type' => 'TEXT', 'null' => true],
                'created_at'       => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status', 'created_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_integration_logs');
        }

        if (! $this->db->tableExists('cmp_lfl_conversions')) {
            $this->forge->addField([
                'id'                  => $this->id(),
                'company_id'          => $this->company(),
                'lead_id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'provider'            => ['type' => 'VARCHAR', 'constraint' => 30],
                'status'              => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pending'], // pending|running|done|failed
                'options'             => ['type' => 'TEXT', 'null' => true],
                'contact_external_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'matter_external_id'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'attempts'            => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'last_error'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'requested_by'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'started_at'          => $this->dt(),
                'finished_at'         => $this->dt(),
                'created_at'          => $this->dt(),
                'updated_at'          => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('lead_id');
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('lead_id', 'cmp_lfl_leads', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('requested_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_conversions');
        }

        if (! $this->db->tableExists('cmp_lfl_ext_contacts')) {
            $this->forge->addField([
                'id'          => $this->id(),
                'company_id'  => $this->company(),
                'provider'    => ['type' => 'VARCHAR', 'constraint' => 30],
                'external_id' => ['type' => 'VARCHAR', 'constraint' => 64],
                'first_name'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'last_name'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'email_norm'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'phone_norm'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'synced_at'   => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'provider', 'external_id']);
            $this->forge->addKey(['company_id', 'email_norm']);
            $this->forge->addKey(['company_id', 'phone_norm']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_ext_contacts');
        }

        // El «Docketwise» del adaptador simulado: una cuenta falsa por firma.
        if (! $this->db->tableExists('cmp_lfl_sim_records')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->company(),
                'entity'     => ['type' => 'VARCHAR', 'constraint' => 20], // contact|matter|note|document|user|matter_type
                'payload'    => ['type' => 'TEXT', 'null' => true],
                'created_at' => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'entity']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_sim_records');
        }
    }

    public function down()
    {
        foreach (['cmp_lfl_sim_records', 'cmp_lfl_ext_contacts', 'cmp_lfl_conversions', 'cmp_lfl_integration_logs',
            'cmp_lfl_mappings', 'cmp_lfl_integrations', 'cmp_lfl_providers'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
