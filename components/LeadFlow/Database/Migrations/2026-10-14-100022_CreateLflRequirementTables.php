<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Checklist documental: qué papeles exige cada servicio y cuáles ha traído el cliente.
 *
 * ── Por qué dos tablas y no una ──────────────────────────────────────────────
 *
 * `cmp_lfl_service_requirements` es la PLANTILLA por servicio («para una visa familiar
 * hacen falta pasaporte, partida de matrimonio y tres pruebas de convivencia»).
 * `cmp_lfl_matter_requirements` es la INSTANCIA de ese expediente, copiada al abrirlo.
 *
 * La copia no es redundancia: es el mismo criterio del snapshot de la cotización. La
 * firma afina su plantilla cada dos meses, y un expediente abierto en enero no puede
 * cambiar de requisitos en marzo porque alguien editó el catálogo —el cliente firmó
 * con una lista concreta—. Además el requisito tiene estado PROPIO que no existe ni en
 * la plantilla ni en el documento: `waived` (se le exime), `rejected` con su nota, y
 * su `due_at`. Calcularlo al vuelo cruzando documentos perdería justo eso.
 *
 * ── Por qué no se toca `cmp_lfl_document_types` ──────────────────────────────
 *
 * Ese catálogo es plano a propósito (`name`, `sort`, `is_active`) y lo usan la subida
 * de documentos y la conversión a Docketwise. La obligatoriedad y la relación con el
 * servicio viven aquí, así que el catálogo sigue siendo un catálogo y nada de lo que
 * ya lo usa cambia.
 *
 * ── Sin UNIQUE en la plantilla ───────────────────────────────────────────────
 *
 * «Pasaporte» del titular y «Pasaporte» del cónyuge son dos filas legítimas del mismo
 * servicio y del mismo tipo de documento. Lo que las distingue es `applies_to`.
 */
class CreateLflRequirementTables extends Migration
{
    private function ts(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];
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
        // ── La plantilla, por servicio ──────────────────────────────────────
        if (! $this->db->tableExists('cmp_lfl_service_requirements')) {
            $this->forge->addField([
                'id'               => $this->id(),
                'company_id'       => $this->fk(),
                'service_id'       => $this->fk(),
                // Opcional: hay requisitos que no encajan en ningún tipo del catálogo
                // («carta de empleo»), y obligar a crear uno solo para listarlos
                // ensuciaría el catálogo que usa la subida de documentos.
                'document_type_id' => $this->fk(true),
                'label'            => ['type' => 'VARCHAR', 'constraint' => 120],
                'is_required'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                // client|spouse|dependent|employer|other — de quién es el papel.
                'applies_to'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'client'],
                'notes'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'sort'             => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'service_id', 'sort']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('service_id', 'cmp_lfl_services', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('document_type_id', 'cmp_lfl_document_types', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_service_requirements');
        }

        // ── La instancia, por expediente ────────────────────────────────────
        if (! $this->db->tableExists('cmp_lfl_matter_requirements')) {
            $this->forge->addField([
                'id'               => $this->id(),
                'company_id'       => $this->fk(),
                'matter_id'        => $this->fk(),
                // De qué fila de la plantilla salió. A SET NULL: borrar la plantilla no
                // puede llevarse por delante el checklist de un expediente vivo.
                'requirement_id'   => $this->fk(true),
                // Copia congelada: editar la plantilla no reescribe lo ya exigido.
                'label'            => ['type' => 'VARCHAR', 'constraint' => 120],
                'document_type_id' => $this->fk(true),
                'is_required'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'applies_to'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'client'],
                'sort'             => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                // pending|received|approved|rejected|waived
                'status'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pending'],
                'document_id'      => $this->fk(true),
                'due_at'           => ['type' => 'DATETIME', 'null' => true],
                'received_at'      => ['type' => 'DATETIME', 'null' => true],
                'reviewed_at'      => ['type' => 'DATETIME', 'null' => true],
                'reviewed_by'      => $this->fk(true),
                'note'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['matter_id', 'status']);
            $this->forge->addKey(['company_id', 'due_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('matter_id', 'cmp_lfl_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('requirement_id', 'cmp_lfl_service_requirements', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('document_type_id', 'cmp_lfl_document_types', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('document_id', 'cmp_lfl_documents', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('reviewed_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_matter_requirements');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_matter_requirements', true);
        $this->forge->dropTable('cmp_lfl_service_requirements', true);
    }
}
