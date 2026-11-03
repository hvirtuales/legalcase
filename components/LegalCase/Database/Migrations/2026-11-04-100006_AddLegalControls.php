<?php

namespace Components\LegalCase\Database\Migrations;

use CodeIgniter\Database\Migration;
use Components\LegalCase\Database\Columns;

/**
 * Versión 1.1 — controles que salieron de la prueba por roles con criterio jurídico.
 *
 *  · Conflicto de interés (`cmp_lgc_conflict_checks`): cada verificación, sus coincidencias
 *    y la decisión justificada quedan como evidencia.
 *  · Autorización de tratamiento de datos (Ley 1581 de 2012, art. 9): estado, fecha,
 *    medio y prueba en el contacto; aceptación del aviso de privacidad en la cuenta del portal.
 *  · Secreto entre coclientes: hilos y solicitudes con destinatario (`account_id`).
 *  · Supervisión del personal no abogado: mensajes que esperan revisión antes de salir.
 *  · Representación: poder (documento, alcance y vigencia) en la parte.
 *  · Acceso de soporte de la plataforma: autorizado por el despacho, con caducidad.
 *  · Conservación: bloqueo legal del asunto y años de retención del despacho.
 *
 * Idempotente: cada columna se añade solo si no existe.
 */
class AddLegalControls extends Migration
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
        $this->add('cmp_lgc_settings', [
            'privacy_version' => $this->str(20, '1'),
            'privacy_text'    => $this->text(),
            'retention_years' => $this->int(10),
        ]);
        $this->add('cmp_lgc_contacts', [
            // none | granted | revoked
            'consent_status'   => $this->str(10, 'none'),
            'consent_at'       => $this->dt(),
            // written | portal | verbal | contract
            'consent_method'   => $this->str(20),
            'consent_evidence' => $this->str(255),
            'consent_version'  => $this->str(20),
        ]);
        $this->add('cmp_lgc_portal_accounts', [
            'privacy_version'     => $this->str(20),
            'privacy_accepted_at' => $this->dt(),
            'privacy_ip'          => $this->str(45),
        ]);
        $this->add('cmp_lgc_threads', [
            // Destinatario: la cuenta del portal con la que se conversa. NULL + is_shared = hilo compartido.
            'account_id' => $this->fk(true),
            'is_shared'  => $this->flag(0),
            // general | data_rights (consulta o reclamo de datos personales)
            'kind'       => $this->str(20, 'general'),
        ]);
        $this->add('cmp_lgc_messages', [
            // sent | pending_review | discarded
            'status'      => $this->str(15, 'sent'),
            'reviewed_by' => $this->fk(true),
            'reviewed_at' => $this->dt(),
        ]);
        $this->add('cmp_lgc_doc_requests', [
            'account_id' => $this->fk(true),
        ]);
        $this->add('cmp_lgc_participants', [
            'authority_document_id' => $this->fk(true),
            'authority_scope'       => $this->str(255),
            'authority_until'       => $this->date(),
        ]);
        $this->add('cmp_lgc_matters', [
            'legal_hold' => $this->flag(0),
        ]);

        if (!$this->db->tableExists('cmp_lgc_conflict_checks')) {
            $this->forge->addField([
                'id'            => $this->id(),
                'company_id'    => $this->fk(),
                'matter_id'     => $this->fk(true),
                'contact_id'    => $this->fk(true),
                'role'          => $this->str(20),
                // clear | potential
                'result'        => $this->str(10, 'clear'),
                'hits'          => $this->text(),
                // none | proceed | rejected
                'decision'      => $this->str(10, 'none'),
                'justification' => $this->text(),
                'decided_by'    => $this->fk(true),
                'created_at'    => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'contact_id']);
            $this->forge->addKey(['company_id', 'matter_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_conflict_checks');
        }

        if (!$this->db->tableExists('cmp_lgc_support_grants')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'user_id'    => $this->fk(),
                'reason'     => $this->str(255),
                'granted_by' => $this->fk(true),
                'expires_at' => $this->dt(),
                'revoked_at' => $this->dt(),
                'created_at' => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'user_id', 'expires_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_support_grants');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lgc_support_grants', true);
        $this->forge->dropTable('cmp_lgc_conflict_checks', true);
        $drop = [
            'cmp_lgc_settings'        => ['privacy_version', 'privacy_text', 'retention_years'],
            'cmp_lgc_contacts'        => ['consent_status', 'consent_at', 'consent_method', 'consent_evidence', 'consent_version'],
            'cmp_lgc_portal_accounts' => ['privacy_version', 'privacy_accepted_at', 'privacy_ip'],
            'cmp_lgc_threads'         => ['account_id', 'is_shared', 'kind'],
            'cmp_lgc_messages'        => ['status', 'reviewed_by', 'reviewed_at'],
            'cmp_lgc_doc_requests'    => ['account_id'],
            'cmp_lgc_participants'    => ['authority_document_id', 'authority_scope', 'authority_until'],
            'cmp_lgc_matters'         => ['legal_hold'],
        ];
        foreach ($drop as $table => $cols) {
            foreach ($cols as $c) {
                if ($this->db->tableExists($table) && $this->db->fieldExists($c, $table)) {
                    $this->forge->dropColumn($table, $c);
                }
            }
        }
    }
}
