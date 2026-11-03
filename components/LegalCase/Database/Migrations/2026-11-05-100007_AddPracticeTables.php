<?php

namespace Components\LegalCase\Database\Migrations;

use CodeIgniter\Database\Migration;
use Components\LegalCase\Database\Columns;

/**
 * Versión 1.2 — gestión de la práctica que quedó fuera del MVP:
 *
 *  · `cmp_lgc_calendar_days`  — días no hábiles propios del despacho (cierres, suspensiones de
 *    términos decretadas, vacancias). Los festivos nacionales los calcula `Libraries/Calendar`.
 *  · `cmp_lgc_hearings`       — audiencias y diligencias con modalidad, lugar o enlace, y si el
 *    cliente debe asistir; pueden compartirse con el cliente.
 *  · Versiones de documentos  — `documents.version / previous_id / is_current`.
 *  · `cmp_lgc_signatures`     — solicitudes de firma electrónica sobre un documento compartido,
 *    con la evidencia (hash del documento, fecha, IP, agente, nombre declarado).
 *  · `cmp_lgc_engagements`    — contrato de prestación de servicios / carta de encargo del asunto.
 *  · Capacidad legal del contacto (`legal_capacity`): menores e incapaces no acceden al portal;
 *    actúa su tutor o curador (papel `guardian`, con documento que lo acredite).
 *  · `settings.calendar_mode` — `common` (lunes a viernes sin festivos) o `judicial` (además, la
 *    vacancia judicial colectiva del 20 de diciembre al 10 de enero).
 */
class AddPracticeTables extends Migration
{
    use Columns;

    private function add(string $table, array $fields): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $new = [];
        foreach ($fields as $name => $def) {
            if (!$this->db->fieldExists($name, $table)) {
                $new[$name] = $def;
            }
        }
        if ($new) {
            $this->forge->addColumn($table, $new);
        }
        $this->db->resetDataCache();
    }

    public function up()
    {
        $this->add('cmp_lgc_settings', ['calendar_mode' => $this->str(10, 'judicial')]);
        $this->add('cmp_lgc_contacts', [
            // full | minor | incapacitated
            'legal_capacity' => $this->str(15, 'full'),
        ]);
        $this->add('cmp_lgc_documents', [
            'version'     => $this->int(1),
            'previous_id' => $this->fk(true),
            'is_current'  => $this->flag(1),
        ]);

        if (!$this->db->tableExists('cmp_lgc_calendar_days')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'day'        => $this->date(),
                // closure | suspension | holiday
                'kind'       => $this->str(15, 'closure'),
                'name'       => $this->str(120),
                'created_by' => $this->fk(true),
                'created_at' => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'day']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_calendar_days');
        }

        if (!$this->db->tableExists('cmp_lgc_hearings')) {
            $this->forge->addField([
                'id'                => $this->id(),
                'company_id'        => $this->fk(),
                'matter_id'         => $this->fk(),
                'proceeding_id'     => $this->fk(true),
                'title'             => $this->str(190),
                'scheduled_at'      => $this->dt(),
                'duration_min'      => $this->int(60),
                // in_person | virtual | hybrid
                'mode'              => $this->str(10, 'in_person'),
                'location'          => $this->str(255),
                'link'              => $this->str(500),
                'client_attendance' => $this->flag(0),
                // Visible para el cliente en el portal (fecha, modalidad, lugar/enlace y preparación).
                'is_shared'         => $this->flag(0),
                'client_notes'      => $this->text(),
                'internal_notes'    => $this->text(),
                // scheduled | held | postponed | cancelled
                'status'            => $this->str(12, 'scheduled'),
                'created_by'        => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status', 'scheduled_at']);
            $this->forge->addKey('matter_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_hearings');
        }

        if (!$this->db->tableExists('cmp_lgc_signatures')) {
            $this->forge->addField([
                'id'            => $this->id(),
                'company_id'    => $this->fk(),
                'matter_id'     => $this->fk(),
                'document_id'   => $this->fk(),
                'account_id'    => $this->fk(),
                // simple = firma electrónica con evidencia propia; otros proveedores, por adaptador
                'provider'      => $this->str(20, 'simple'),
                // pending | signed | declined | cancelled
                'status'        => $this->str(10, 'pending'),
                'purpose'       => $this->str(190),
                'doc_sha256'    => $this->str(64),
                'signer_name'   => $this->str(190),
                'signer_doc'    => $this->str(40),
                'signed_at'     => $this->dt(),
                'ip'            => $this->str(45),
                'user_agent'    => $this->str(255),
                'evidence_hash' => $this->str(64),
                'decline_note'  => $this->str(255),
                'created_by'    => $this->fk(true),
            ] + $this->stamps(false));
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey(['matter_id', 'account_id']);
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_signatures');
        }

        if (!$this->db->tableExists('cmp_lgc_engagements')) {
            $this->forge->addField([
                'id'              => $this->id(),
                'company_id'      => $this->fk(),
                'matter_id'       => $this->fk(),
                // services_contract | engagement_letter | power_of_attorney
                'kind'            => $this->str(25, 'services_contract'),
                // fixed | hourly | contingency | mixed | pro_bono
                'fee_type'        => $this->str(15, 'fixed'),
                'amount'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency'        => $this->str(3, 'COP'),
                'contingency_pct' => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
                'scope'           => $this->text(),
                'exclusions'      => $this->text(),
                'starts_on'       => $this->date(),
                'ends_on'         => $this->date(),
                // draft | sent | signed | terminated
                'status'          => $this->str(12, 'draft'),
                'document_id'     => $this->fk(true),
                'signature_id'    => $this->fk(true),
                'created_by'      => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey('matter_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_engagements');
        }
    }

    public function down()
    {
        foreach (['cmp_lgc_engagements', 'cmp_lgc_signatures', 'cmp_lgc_hearings', 'cmp_lgc_calendar_days'] as $t) {
            $this->forge->dropTable($t, true);
        }
        foreach (['cmp_lgc_settings' => ['calendar_mode'], 'cmp_lgc_contacts' => ['legal_capacity'], 'cmp_lgc_documents' => ['version', 'previous_id', 'is_current']] as $t => $cols) {
            foreach ($cols as $c) {
                if ($this->db->tableExists($t) && $this->db->fieldExists($c, $t)) {
                    $this->forge->dropColumn($t, $c);
                }
            }
        }
    }
}
