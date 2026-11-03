<?php

namespace Components\LegalCase\Database\Migrations;

use CodeIgniter\Database\Migration;
use Components\LegalCase\Database\Columns;

/**
 * Portal del cliente, cola de avisos y bitácora del componente.
 *
 * · `cmp_lgc_portal_accounts` — cuentas PROPIAS del componente, no `users` del
 *   núcleo: un cliente nunca puede heredar un rol global ni el contexto de empresa
 *   del personal. Una cuenta por (empresa, correo).
 * · `cmp_lgc_portal_tokens` — enlaces de acceso de un solo uso. Se guarda el
 *   **hash** del verificador, nunca el valor: quien lea la tabla no puede entrar.
 * · `cmp_lgc_deliveries` — cola de salida (outbox) con clave de idempotencia
 *   única: un reintento no duplica el aviso (§6.13-5).
 * · `cmp_lgc_audit` — bitácora de solo inserción: publicaciones, revocaciones,
 *   invitaciones, accesos al portal, descargas y validaciones de plazos.
 */
class CreateLgcPortalTables extends Migration
{
    use Columns;

    public function up()
    {
        if (!$this->db->tableExists('cmp_lgc_portal_accounts')) {
            $this->forge->addField([
                'id'            => $this->id(),
                'company_id'    => $this->fk(),
                'contact_id'    => $this->fk(),
                'email'         => $this->str(190),
                'locale'        => $this->str(5, 'es'),
                'status'        => $this->str(10, 'active'),
                'notify_email'  => $this->flag(1),
                'invited_by'    => $this->fk(true),
                'invited_at'    => $this->dt(),
                'last_login_at' => $this->dt(),
            ] + $this->stamps(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'email']);
            $this->forge->addKey('contact_id');
            $this->forge->addForeignKey('contact_id', 'cmp_lgc_contacts', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_portal_accounts');
        }

        if (!$this->db->tableExists('cmp_lgc_portal_tokens')) {
            $this->forge->addField([
                'id'            => $this->id(),
                'company_id'    => $this->fk(),
                'account_id'    => $this->fk(),
                'selector'      => $this->str(32),
                'verifier_hash' => $this->str(64),
                'purpose'       => $this->str(10, 'login'),
                'expires_at'    => $this->dt(),
                'used_at'       => $this->dt(),
                'ip'            => $this->str(45),
                'created_at'    => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('selector');
            $this->forge->addKey(['account_id', 'created_at']);
            $this->forge->addForeignKey('account_id', 'cmp_lgc_portal_accounts', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_portal_tokens');
        }

        if (!$this->db->tableExists('cmp_lgc_deliveries')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'matter_id'  => $this->fk(true),
                'account_id' => $this->fk(true),
                'channel'    => $this->str(10, 'email'),
                'kind'       => $this->str(30),
                'ref_type'   => $this->str(20),
                'ref_id'     => $this->fk(true),
                'idem_key'   => $this->str(120),
                'recipient'  => $this->str(190),
                // pending | sent | failed | skipped
                'status'     => $this->str(10, 'pending'),
                'attempts'   => $this->int(0),
                'last_error' => $this->str(255),
                'sent_at'    => $this->dt(),
            ] + $this->stamps(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('idem_key');
            $this->forge->addKey(['status', 'attempts']);
            $this->forge->addKey(['company_id', 'matter_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_deliveries');
        }

        if (!$this->db->tableExists('cmp_lgc_audit')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'matter_id'  => $this->fk(true),
                // user | portal | system
                'actor_type' => $this->str(10, 'user'),
                'actor_id'   => $this->fk(true),
                'action'     => $this->str(60),
                'entity'     => $this->str(30),
                'entity_id'  => $this->fk(true),
                'detail'     => $this->text(),
                'ip'         => $this->str(45),
                'created_at' => $this->dt(),
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'matter_id', 'created_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_audit');
        }
    }

    public function down()
    {
        foreach (['cmp_lgc_audit', 'cmp_lgc_deliveries', 'cmp_lgc_portal_tokens', 'cmp_lgc_portal_accounts'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
