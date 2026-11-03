<?php

namespace Components\LegalCase\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LegalCase\Libraries\Access;
use Components\LegalCase\Libraries\ClientView;
use Components\LegalCase\Libraries\Clock;
use Components\LegalCase\Libraries\DeadlineService;
use Components\LegalCase\Libraries\MatterService;
use Components\LegalCase\Libraries\MessageService;
use Components\LegalCase\Libraries\Outbox;
use Components\LegalCase\Libraries\Portal;
use Components\LegalCase\Libraries\Team;
use Components\LegalCase\Libraries\DocumentService;
use Components\LegalCase\Libraries\UpdateService;
use Components\LegalCase\Libraries\ConflictService;
use Components\LegalCase\Libraries\Support;
use Components\LegalCase\Libraries\Calendar;
use Components\LegalCase\Libraries\HearingService;
use Components\LegalCase\Libraries\SignatureService;
use Components\LegalCase\Libraries\EngagementService;
use Components\LegalCase\Libraries\DataSubjectService;
use Components\LegalCase\Models\HearingModel;
use Components\LegalCase\Models\SignatureModel;
use Components\LegalCase\Models\CalendarDayModel;
use Components\LegalCase\Models\ConflictCheckModel;
use Components\LegalCase\Models\MessageModel;
use Components\LegalCase\Models\AuditModel;
use Components\LegalCase\Models\ContactModel;
use Components\LegalCase\Models\DeadlineModel;
use Components\LegalCase\Models\DeliveryModel;
use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\ParticipantModel;
use Components\LegalCase\Models\PortalAccountModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\TaskModel;
use Components\LegalCase\Models\ThreadModel;
use Components\LegalCase\Models\UpdateModel;

/**
 * `php spark lgc:autotest`
 *
 * Recorre los flujos del componente contra la BASE DE DATOS REAL, con dos despachos de
 * prueba (`ZZTEST …`) que se borran SIEMPRE al terminar, pase lo que pase. Comprueba
 * sobre todo lo que el documento maestro marca como criterio de aceptación (§6.13):
 *
 *  1. Aislamiento: un id de otro despacho no se resuelve; un asunto `team` no lo ve
 *     quien no está en el equipo; el portal no ve asuntos ajenos ni campos internos.
 *  2. Aprobación: el borrador no sale hasta aprobarse; con política `reviewer` el autor
 *     no se aprueba a sí mismo; lo publicado no se edita.
 *  3. «Sin novedades» exige fuente verificable.
 *  4. Avisos: se registran destinatario, canal y resultado; publicar dos veces no
 *     duplica; un fallo del proveedor queda `failed` y se reintenta sin duplicar.
 *  5. Portal: solo cliente/representante; enlaces de un solo uso con hash en BD.
 *  6. Plazos: nacen por validar; doble validación con dos personas distintas.
 *  7. Reloj: la tarea «toca informar» es idempotente.
 *
 * Los correos NO salen: se usa un remitente simulado que registra lo que se habría enviado.
 */
class LgcAutotest extends BaseCommand
{
    protected $group       = 'LegalCase';
    protected $name        = 'lgc:autotest';
    protected $description = 'Prueba de extremo a extremo del componente sobre la BD real (datos ZZTEST, se borran siempre).';

    private int $ok = 0;
    private int $ko = 0;
    private array $companies = [];
    private array $users = [];
    /** @var list<array{to:string,subject:string,html:string}> */
    private array $mail = [];
    private bool $failNext = false;

    public function run(array $params)
    {
        CLI::write('Autotest LegalCase', 'yellow');

        try {
            $this->scenario();
        } catch (\Throwable $e) {
            $this->ko++;
            CLI::write('  EXCEPCIÓN ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), 'red');
        } finally {
            $this->cleanup();
        }

        CLI::newLine();
        CLI::write(str_repeat('─', 60), 'dark_gray');
        CLI::write($this->ko === 0 ? "OK: {$this->ok} comprobaciones" : "FALLAS: {$this->ko} de " . ($this->ok + $this->ko), $this->ko === 0 ? 'green' : 'red');

        return $this->ko === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function check(string $what, bool $good, string $detail = ''): void
    {
        $good ? $this->ok++ : $this->ko++;
        CLI::write(($good ? '  OK    ' : '  FALLA ') . $what . ($good || $detail === '' ? '' : '  ->  ' . $detail), $good ? 'green' : 'red');
    }

    private function title(string $t): void
    {
        CLI::newLine();
        CLI::write('=== ' . $t . ' ===', 'yellow');
    }

    /** Remitente simulado. `failNext` hace fallar el siguiente envío. */
    private function sender(): callable
    {
        return function (int $companyId, string $to, string $subject, string $html): array {
            if ($this->failNext) {
                $this->failNext = false;

                return ['ok' => false, 'message' => 'SMTP simulado caído'];
            }
            $this->mail[] = compact('to', 'subject', 'html');

            return ['ok' => true, 'message' => 'ok'];
        };
    }

    // ------------------------------------------------------------------ datos de prueba

    private function user(string $tag): int
    {
        $db = db_connect();
        $db->table('users')->insert([
            'username' => 'zztest_' . $tag . '_' . bin2hex(random_bytes(3)), 'email' => 'zztest_' . $tag . '@example.invalid',
            'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'first_name' => 'ZZTEST', 'last_name' => ucfirst($tag),
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->users[] = (int) $db->insertID();
    }

    /** Rol de prueba con los permisos indicados; se borra al terminar. */
    private array $roles = [];

    private function role(string $tag, array $perms): int
    {
        $db = db_connect();
        $db->table('roles')->insert(['name' => 'ZZTEST ' . $tag . ' ' . bin2hex(random_bytes(3)), 'description' => 'autotest', 'created_at' => date('Y-m-d H:i:s')]);
        $rid = (int) $db->insertID();
        foreach ($db->table('permissions')->whereIn('name', $perms)->get()->getResultArray() as $p) {
            $db->table('role_permissions')->insert(['role_id' => $rid, 'permission_id' => (int) $p['id']]);
        }

        return $this->roles[] = $rid;
    }

    private function assign(int $userId, int $roleId): void
    {
        db_connect()->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
    }

    private function company(string $name, int $ownerId, array $members): int
    {
        $db = db_connect();
        $db->table('companies')->insert(['name' => 'ZZTEST ' . $name, 'owner_id' => $ownerId, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $id = (int) $db->insertID();
        foreach ($members as $uid) {
            $db->table('company_users')->insert(['company_id' => $id, 'user_id' => $uid, 'is_admin' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        }

        return $this->companies[] = $id;
    }

    private function cleanup(): void
    {
        $db = db_connect();
        foreach ($this->companies as $cid) {
            // Las tablas del componente caen en cascada desde companies.
            $db->table('company_users')->where('company_id', $cid)->delete();
            $db->table('companies')->where('id', $cid)->delete();
            $dir = WRITEPATH . 'uploads/lgc/' . $cid;
            if (is_dir($dir)) {
                array_map('unlink', glob($dir . '/*') ?: []);
                @rmdir($dir);
            }
        }
        foreach ($this->users as $uid) {
            $db->table('user_roles')->where('user_id', $uid)->delete();
            $db->table('users')->where('id', $uid)->delete();
        }
        foreach ($this->roles as $rid) {
            $db->table('role_permissions')->where('role_id', $rid)->delete();
            $db->table('roles')->where('id', $rid)->delete();
        }
        $left = $this->companies ? $db->table('cmp_lgc_matters')->whereIn('company_id', $this->companies)->countAllResults() : 0;
        CLI::write('  Limpieza: ' . ($left === 0 ? 'sin residuos' : "QUEDAN {$left} asuntos"), $left === 0 ? 'dark_gray' : 'red');
    }

    // ------------------------------------------------------------------ escenario

    private function scenario(): void
    {
        $owner    = $this->user('owner');
        $lawyer2  = $this->user('lawyer2');
        $outsider = $this->user('outsider');
        $cidA     = $this->company('Despacho A', $owner, [$lawyer2]);
        $para     = $this->user('paralegal');
        $auditor  = $this->user('auditor');
        $platform = $this->user('platform');
        $cidB     = $this->company('Despacho B', $outsider, []);
        $lawyerRole = $this->role('abogado', ['lgc.view', 'lgc.create', 'lgc.edit', 'lgc.approve', 'lgc.share', 'lgc.delete', 'lgc.configure']);
        $paraRole   = $this->role('paralegal', ['lgc.view', 'lgc.create', 'lgc.edit']);
        $audRole    = $this->role('auditor', ['lgc.view']);
        foreach ([$owner, $lawyer2, $outsider] as $u) {
            $this->assign($u, $lawyerRole);
        }
        $this->assign($para, $paraRole);
        $this->assign($auditor, $audRole);
        $this->assign($platform, $lawyerRole);
        foreach ([$para, $auditor] as $u) {
            db_connect()->table('company_users')->insert(['company_id' => $cidA, 'user_id' => $u, 'is_admin' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        }
        Team::flush();

        $outbox  = new Outbox($this->sender());
        $accA    = Access::system($cidA);
        $accOwn  = new Access($cidA, $owner);
        $accL2   = new Access($cidA, $lawyer2);
        $accB    = Access::system($cidB);
        $matters = new MatterService();

        // ── 1. Asuntos, numeración y plan ─────────────────────────────────────
        $this->title('1. Asuntos, consecutivo y plan de comunicación');
        $contact = (new ContactModel());
        $contact->insert(['company_id' => $cidA, 'kind' => 'person', 'name' => 'ZZTEST Cliente', 'email' => 'zztest.cliente@example.invalid', 'locale' => 'es']);
        $clientId = (int) $contact->getInsertID();
        $contact->insert(['company_id' => $cidA, 'kind' => 'org', 'name' => 'ZZTEST Contraparte', 'email' => 'zztest.contra@example.invalid']);
        $counterId = (int) $contact->getInsertID();

        $r1 = $matters->create(['title' => 'Interno: demanda laboral', 'public_title' => 'Su reclamación', 'client_contact_id' => $clientId,
            'lead_lawyer_id' => $owner, 'strategy' => 'SECRETO-ESTRATEGIA', 'public_stage' => 'Estudio'], $accOwn);
        $r2 = $matters->create(['title' => 'Segundo asunto', 'lead_lawyer_id' => $owner, 'visibility' => 'team'], $accOwn);
        $this->check('crea el asunto', $r1->ok && $r2->ok, $r1->message . ' / ' . $r2->message);
        $m1 = (new MatterModel())->find($r1->id);
        $m2 = (new MatterModel())->find($r2->id);
        $this->check('consecutivo LC-000001 y LC-000002', $m1['number'] === 'LC-000001' && $m2['number'] === 'LC-000002', $m1['number'] . ' ' . $m2['number']);
        $plan = db_connect()->table('cmp_lgc_comm_plans')->where('matter_id', $r1->id)->get()->getRowArray();
        $this->check('nace con plan de comunicación activo', $plan && (int) $plan['is_active'] === 1 && $plan['next_update_due_at'] !== null);
        $this->check('el cliente queda como parte sin acceso al portal', (new ParticipantModel())->where('matter_id', $r1->id)->where('role', 'client')->where('portal_access', 0)->countAllResults() === 1);
        $bad = $matters->create(['title' => 'X', 'client_contact_id' => $clientId], $accB);
        $mB  = $bad->ok ? (new MatterModel())->find($bad->id) : null;
        $this->check('un contacto de OTRO despacho se descarta al crear', $mB && $mB['client_contact_id'] === null);

        // ── 2. Aislamiento y visibilidad ──────────────────────────────────────
        $this->title('2. Aislamiento entre despachos y visibilidad por equipo');
        $this->check('own() no resuelve un asunto de otra empresa (IDOR)', (new MatterModel())->own($cidB, $r1->id) === null);
        $this->check('el miembro ve el asunto «firm»', $accL2->canSeeMatter($m1));
        $this->check('el miembro NO ve el asunto «team» ajeno', !$accL2->canSeeMatter($m2));
        $this->check('el dueño (administrador) sí lo ve', $accOwn->canSeeMatter($m2));
        $this->check('el usuario de otro despacho no ve nada', !(new Access($cidB, $outsider))->canSeeMatter($m1));
        $scoped = new MatterModel();
        $accL2->scopeMatters($scoped);
        $ids = array_map('intval', array_column($scoped->findAll(), 'id'));
        $this->check('scopeMatters() filtra por empresa y equipo', in_array($r1->id, $ids, true) && !in_array($r2->id, $ids, true) && !in_array($bad->id, $ids, true));
        $matters->addTeam($m2, $lawyer2, 'collaborator', $accOwn);
        $this->check('al entrar al equipo pasa a verlo', (new Access($cidA, $lawyer2))->canSeeMatter($m2));
        $this->check('no se puede añadir al equipo a alguien de otro despacho', !$matters->addTeam($m1, $outsider, 'collaborator', $accOwn)->ok);

        // ── 3. Portal: acceso por asunto ──────────────────────────────────────
        $this->title('3. Acceso al portal');
        $portal = new Portal();
        $matters->addParticipant($m1, $counterId, 'counterparty', $accOwn);
        $pCounter = (new ParticipantModel())->where('matter_id', $r1->id)->where('role', 'counterparty')->first();
        $this->check('la contraparte NO puede recibir acceso', !$portal->grant($m1, $pCounter, $accOwn, $this->sender())->ok);
        $pClient = (new ParticipantModel())->where('matter_id', $r1->id)->where('role', 'client')->first();
        $this->mail = [];
        $g = $portal->grant($m1, $pClient, $accOwn, $this->sender());
        $this->check('el cliente recibe acceso e invitación', $g->ok && count($this->mail) === 1, $g->message);
        $account = (new PortalAccountModel())->find((int) $g->id);
        preg_match('#entrar/([a-f0-9]{32}\.[a-f0-9]{64})#', html_entity_decode($this->mail[0]['html'] ?? '', ENT_QUOTES | ENT_HTML5), $mm);
        $token = $mm[1] ?? '';
        $this->check('la invitación lleva un enlace selector.verificador', $token !== '');
        $row = db_connect()->table('cmp_lgc_portal_tokens')->where('account_id', (int) $account['id'])->get()->getRowArray();
        $this->check('en BD se guarda el hash, no el verificador', $row && !str_contains($token, (string) $row['verifier_hash']) && strlen((string) $row['verifier_hash']) === 64);
        $this->check('el enlace se consume UNA sola vez', $portal->consume($token) !== null && $portal->consume($token) === null);
        $this->check('un enlace manipulado no entra', $portal->consume(str_repeat('a', 32) . '.' . str_repeat('b', 64)) === null);
        $this->mail = [];
        $portal->requestLink('zztest.cliente@example.invalid', $this->sender());
        $portal->requestLink('nadie@example.invalid', $this->sender());
        $this->check('pedir enlace solo envía a cuentas existentes', count($this->mail) === 1);

        // ── 4. Actualizaciones y aprobación ───────────────────────────────────
        $this->title('4. Actualizaciones al cliente (borrador → publicación)');
        $upd = new UpdateService($outbox);
        $d1  = $upd->createDraft($m1, 'regular', ['title' => 'Novedad', 'what_happened' => 'Se radicó la demanda.'], $accL2);
        $u1  = (new UpdateModel())->find($d1->id);
        $view = new ClientView();
        $this->check('el borrador NO aparece en el portal', $view->detail($account, $view->matter($account, $r1->id))['updates'] === []);
        $this->check('avisa de expresiones de promesa', $upd->warnings(['title' => 'Le garantizamos el resultado'] + array_fill_keys(['what_happened', 'what_it_means', 'what_we_do', 'client_action', 'stage_label', 'verified_source'], '')) !== []);

        (new SettingsModel())->where('company_id', $cidA)->set(['approval_policy' => 'reviewer'])->update();
        $this->check('política reviewer: no se publica sin aprobar', !$upd->publish($u1, $m1, $accL2)->ok);
        $upd->submit($u1, $accL2);
        $u1 = (new UpdateModel())->find($d1->id);
        $this->check('política reviewer: el autor no se aprueba a sí mismo', !$upd->approve($u1, $accL2)->ok);
        $this->check('otra persona sí aprueba', $upd->approve($u1, $accOwn)->ok);
        $u1 = (new UpdateModel())->find($d1->id);
        $this->mail = [];
        $pub = $upd->publish($u1, $m1, $accOwn);
        $this->check('publica y avisa al cliente', $pub->ok && count($this->mail) === 1, $pub->message);
        $this->check('el aviso NO lleva el contenido de la actualización', isset($this->mail[0]) && !str_contains($this->mail[0]['html'], 'radicó'));
        $u1 = (new UpdateModel())->find($d1->id);
        $this->mail = [];
        $upd->publish($u1, $m1, $accOwn);
        $this->check('publicar dos veces no duplica el aviso', count($this->mail) === 0 && (new DeliveryModel())->where('ref_type', 'update')->where('ref_id', $d1->id)->countAllResults() === 1);
        $dl = (new DeliveryModel())->where('ref_id', $d1->id)->where('ref_type', 'update')->first();
        $this->check('el envío registra destinatario, canal y resultado', $dl && $dl['recipient'] === 'zztest.cliente@example.invalid' && $dl['channel'] === 'email' && $dl['status'] === 'sent');
        $this->check('lo publicado no se edita', !$upd->edit($u1, ['title' => 'Otro'], $accOwn)->ok);
        $pubView = $view->detail($account, $view->matter($account, $r1->id))['updates'];
        $this->check('la publicada aparece en el portal', count($pubView) === 1);
        $mPub = $view->matter($account, $r1->id);
        $this->check('el portal no recibe estrategia, hechos ni título interno', !array_key_exists('strategy', $mPub) && !array_key_exists('description', $mPub) && !array_key_exists('title', $mPub));

        $rev = $upd->revise($u1, $accL2);
        $u2  = (new UpdateModel())->find($rev->id);
        $upd->submit($u2, $accL2);
        $upd->approve((new UpdateModel())->find($rev->id), $accOwn);
        $upd->publish((new UpdateModel())->find($rev->id), $m1, $accOwn);
        $this->check('la nueva versión reemplaza a la anterior', (new UpdateModel())->find($d1->id)['status'] === 'superseded' && (new UpdateModel())->find($rev->id)['status'] === 'published');
        $this->check('revocar exige motivo', !$upd->revoke((new UpdateModel())->find($rev->id), '', $accOwn)->ok);
        $upd->revoke((new UpdateModel())->find($rev->id), 'Error de redacción', $accOwn);
        $this->check('lo revocado desaparece del portal', $view->detail($account, $view->matter($account, $r1->id))['updates'] === []);

        // Política self + sin novedades
        (new SettingsModel())->where('company_id', $cidA)->set(['approval_policy' => 'self'])->update();
        $nn = $upd->createDraft($m1, 'no_news', ['title' => 'Sin novedades', 'what_happened' => 'No hubo actuaciones.'], $accOwn);
        $this->check('«sin novedades» sin fuente no se publica', !$upd->publish((new UpdateModel())->find($nn->id), $m1, $accOwn)->ok);
        $upd->edit((new UpdateModel())->find($nn->id), ['verified_source' => 'Consulta de procesos', 'verified_at' => date('Y-m-d H:i')], $accOwn);
        $this->check('con fuente verificada y política self, el autor publica', $upd->publish((new UpdateModel())->find($nn->id), $m1, $accOwn)->ok);
        $self = (new UpdateModel())->find($nn->id);
        $this->check('la auto-aprobación queda registrada', (int) $self['reviewed_by'] === $owner && $self['reviewed_at'] !== null);

        // Fallo del proveedor y reintento
        $this->failNext = true;
        $d3 = $upd->createDraft($m1, 'regular', ['title' => 'Otra', 'what_happened' => 'Audiencia fijada.'], $accOwn);
        $upd->publish((new UpdateModel())->find($d3->id), $m1, $accOwn);
        $f = (new DeliveryModel())->where('ref_type', 'update')->where('ref_id', $d3->id)->first();
        $this->check('un fallo del proveedor queda «failed» con su error', $f && $f['status'] === 'failed' && $f['last_error'] !== '');
        $this->mail = [];
        $outbox->dispatch(50, $cidA);
        $f = (new DeliveryModel())->find((int) $f['id']);
        $this->check('el reintento envía sin duplicar', $f['status'] === 'sent' && (int) $f['attempts'] === 2 && count($this->mail) === 1);

        // ── 5. Mensajes y documentos ──────────────────────────────────────────
        $this->title('5. Mensajería y documentos');
        $msg = new MessageService($outbox);
        $t   = $msg->clientWrites($account, $view->matter($account, $r1->id), null, 'Pregunta', '¿Cómo va mi caso?');
        $thr = (new ThreadModel())->find((int) $t->id);
        $this->check('la consulta crea hilo con responsable y hora objetivo', $t->ok && (int) $thr['assigned_to'] === $owner && $thr['first_response_due_at'] !== null);
        $auto = db_connect()->table('cmp_lgc_messages')->where('thread_id', $t->id)->where('is_auto', 1)->countAllResults();
        $this->check('se genera un acuse automático administrativo', $auto === 1);
        $this->mail = [];
        $msg->staffReplies($m1, $thr, 'Va bien.', 'answered', $accOwn);
        $thr = (new ThreadModel())->find((int) $t->id);
        $this->check('la respuesta marca primera respuesta y avisa', $thr['first_response_at'] !== null && $thr['status'] === 'answered' && count($this->mail) === 1);

        $docs = new DocumentModel();
        $docs->insert(['company_id' => $cidA, 'matter_id' => $r1->id, 'name' => 'interno.pdf', 'path' => 'x.bin', 'mime' => 'application/pdf', 'sha256' => str_repeat('1', 64), 'classification' => 'internal']);
        $internalId = (int) $docs->getInsertID();
        $docs->insert(['company_id' => $cidA, 'matter_id' => $r1->id, 'name' => 'compartido.pdf', 'path' => 'y.bin', 'mime' => 'application/pdf', 'sha256' => str_repeat('2', 64), 'classification' => 'shared']);
        $sharedId = (int) $docs->getInsertID();
        $this->check('el portal solo lista documentos compartidos', array_column($view->detail($account, $view->matter($account, $r1->id))['docs'], 'id') == [$sharedId]);
        $this->check('el portal no descarga un documento interno', $view->document($account, $internalId) === null && $view->document($account, $sharedId) !== null);

        $req = $msg->requestDocument($m1, ['title' => 'Contrato'], $accOwn);
        $this->check('solicitud de documento creada y avisada', $req->ok);
        $reqRow = db_connect()->table('cmp_lgc_doc_requests')->where('id', $req->id)->get()->getRowArray();
        $msg->markSubmitted($reqRow, $m1, (int) $account['id']);
        $this->check('al entregar, el responsable recibe una tarea de revisión', (new TaskModel())->where('ref', 'doc_review:' . $req->id)->where('status', 'open')->countAllResults() === 1);
        $this->check('rechazar exige observación', !$msg->reviewRequest($reqRow, $m1, 'reject', '', $accOwn)->ok);

        // El portal no ve el asunto 2 (sin acceso) ni el del otro despacho.
        $this->check('el portal no resuelve un asunto sin acceso', $view->matter($account, $r2->id) === null && $view->matter($account, $bad->id) === null);
        $portal->revoke($m1, $pClient, $accOwn);
        $this->check('retirado el acceso, el asunto deja de verse', $view->matter($account, $r1->id) === null);

        // ── 6. Plazos ─────────────────────────────────────────────────────────
        $this->title('6. Plazos');
        $dls = new DeadlineService();
        (new SettingsModel())->where('company_id', $cidA)->set(['deadline_policy' => 'dual'])->update();
        $dc  = $dls->createDeadline($m1, ['title' => 'Contestar', 'due_at' => date('Y-m-d H:i', strtotime('+5 days')), 'is_critical' => 1], $accOwn);
        $row = (new DeadlineModel())->find($dc->id);
        $this->check('el plazo nace «por validar»', $row['status'] === 'to_validate');
        $dls->validate($row, $accOwn);
        $row = (new DeadlineModel())->find($dc->id);
        $this->check('doble validación: la primera no lo confirma', $row['status'] === 'to_validate' && (int) $row['validated_by'] === $owner);
        $this->check('doble validación: la misma persona no valida dos veces', !$dls->validate($row, $accOwn)->ok);
        $dls->validate($row, $accL2);
        $this->check('doble validación: otra persona lo confirma', (new DeadlineModel())->find($dc->id)['status'] === 'validated');
        $this->check('un plazo sin fecha no se guarda', !$dls->createDeadline($m1, ['title' => 'Sin fecha'], $accOwn)->ok);

        // ── 7. Reloj ──────────────────────────────────────────────────────────
        $this->title('7. Reloj (toca informar)');
        db_connect()->table('cmp_lgc_comm_plans')->where('matter_id', $r1->id)->update(['next_update_due_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mode' => 'periodic', 'is_active' => 1]);
        $clock = new Clock($outbox);
        $n1 = $clock->dueUpdates($cidA);
        $n2 = $clock->dueUpdates($cidA);
        $this->check('crea una tarea «toca informar» y no la repite', $n1 === 1 && $n2 === 0, "$n1 / $n2");
        $over = Clock::overdueQuery($cidA)->get()->getResultArray();
        $this->check('el asunto aparece en «comunicación vencida»', in_array($r1->id, array_map('intval', array_column($over, 'matter_id')), true));
        $this->check('el panel de otro despacho no lo ve', Clock::overdueQuery($cidB)->get()->getResultArray() === []);

        // ── 8. Bitácora y cierre ──────────────────────────────────────────────
        $this->title('8. Bitácora y cierre');
        $audit = new AuditModel();
        $this->check('la bitácora registró publicaciones y accesos', $audit->where('company_id', $cidA)->where('action', 'update.published')->countAllResults() >= 3
            && (new AuditModel())->where('company_id', $cidA)->where('action', 'portal.granted')->countAllResults() === 1);
        $threw = false;
        try {
            $first = (new AuditModel())->where('company_id', $cidA)->first();
            (new AuditModel())->update((int) $first['id'], ['action' => 'x']);
        } catch (\RuntimeException $e) {
            $threw = true;
        }
        $this->check('la bitácora es de solo inserción', $threw);
        $this->check('cerrar exige un motivo válido', !$matters->close($m1, 'inventado', $accOwn)->ok);
        $cl = $matters->close($m1, 'completed', $accOwn);
        $this->check('cerrar con pendientes exige confirmarlos', !$cl->ok && !empty($cl->data['pending']['deadlines']));
        $matters->close($m1, 'completed', $accOwn, true);
        $this->check('al cerrar se cancelan tareas y se cierran hilos (los plazos siguen)', (new TaskModel())->where('matter_id', $r1->id)->where('status', 'open')->countAllResults() === 0
            && (new ThreadModel())->where('matter_id', $r1->id)->whereIn('status', ThreadModel::OPEN)->countAllResults() === 0
            && (new DeadlineModel())->where('matter_id', $r1->id)->where('status', 'validated')->countAllResults() === 1);
        $this->check('cerrar desactiva el plan de comunicación', (int) db_connect()->table('cmp_lgc_comm_plans')->where('matter_id', $r1->id)->get()->getRowArray()['is_active'] === 0);
        $this->check('un asunto cerrado no recibe borradores normales', !$upd->createDraft((new MatterModel())->find($r1->id), 'regular', ['what_happened' => 'x'], $accOwn)->ok);

        $this->legalControls($cidA, $cidB, $owner, $lawyer2, $para, $auditor, $platform, $outbox);
        $this->practice($cidA, $owner, $para, $outbox);
    }

    // ------------------------------------------------------------------ 1.1 controles jurídicos

    private function legalControls(int $cidA, int $cidB, int $owner, int $lawyer2, int $para, int $auditor, int $platform, Outbox $outbox): void
    {
        $accOwn  = new Access($cidA, $owner);
        $accPara = new Access($cidA, $para);
        $accAud  = new Access($cidA, $auditor);
        $matters = new MatterService();
        $contacts = new ContactModel();
        $mk = function (string $name, string $email = '', string $doc = '') use ($contacts, $cidA): int {
            $contacts->insert(['company_id' => $cidA, 'kind' => 'person', 'name' => $name, 'email' => $email, 'doc_number' => $doc, 'locale' => 'es']);

            return (int) $contacts->getInsertID();
        };

        // ── 9. Conflicto de interés ──────────────────────────────────────────
        $this->title('9. Conflicto de interés');
        $cliente = $mk('ZZTEST Lucía Pérez', 'zz.lucia@example.invalid', '111');
        $empresa = $mk('ZZTEST Industrias Ñandú S.A.S.', 'zz.nandu@example.invalid', '900');
        $a = $matters->create(['title' => 'ZZTEST demanda', 'client_contact_id' => $cliente, 'lead_lawyer_id' => $owner], $accOwn);
        $ma = (new MatterModel())->find($a->id);
        $matters->addParticipant($ma, $empresa, 'counterparty', $accOwn);
        $gemela = $mk('zztest industrias ÑANDU sas', '', '');
        $b = $matters->create(['title' => 'ZZTEST asesoría a la contraparte', 'client_contact_id' => $gemela, 'lead_lawyer_id' => $owner], $accOwn);
        $this->check('abrir un asunto para quien es contraparte en otro se detiene', !$b->ok && !empty($b->data['conflicts']), $b->message);
        $this->check('detecta la coincidencia por nombre normalizado (tildes, mayúsculas, S.A.S.)', ($b->data['conflicts'][0]['match'] ?? '') === 'same_name');
        $c = $matters->create(['title' => 'ZZTEST asesoría a la contraparte', 'client_contact_id' => $gemela, 'lead_lawyer_id' => $owner,
            'conflict_ack' => 1, 'conflict_note' => 'Persona distinta, verificado con el certificado de existencia.'], $accOwn);
        $this->check('con revisión y justificación se puede continuar', $c->ok);
        $chk = (new ConflictCheckModel())->where('company_id', $cidA)->where('matter_id', $c->id)->where('decision', 'proceed')->first();
        $this->check('la decisión queda como evidencia asociada al asunto', $chk !== null && str_contains((string) $chk['justification'], 'certificado'));
        $doc = $mk('ZZTEST Otro Nombre', '', '111');
        $this->check('detecta la misma persona por número de documento', ((new ConflictService())->check($cidA, $doc, 'counterparty', null)[0]['match'] ?? '') === 'same_document');
        $this->check('un contacto no puede estar en los dos lados del mismo asunto', !$matters->addParticipant($ma, $cliente, 'counterparty', $accOwn)->ok);

        // ── 10. Acceso: equipo, privilegio, soporte de plataforma ─────────────
        $this->title('10. Acceso por equipo, privilegio y soporte');
        $this->check('el auditor (miembro, fuera del equipo) ve el asunto', $accAud->canSeeMatter($ma));
        $this->check('pero no lo privilegiado ni puede actuar', !$accAud->isInsider($ma) && !$accAud->canWorkOn($ma));
        $this->check('quien lo abre sin ser responsable queda en el equipo', (new Access($cidA, $para))->canWorkOn((new MatterModel())->find(
            $matters->create(['title' => 'ZZTEST abierto por paralegal', 'lead_lawyer_id' => $owner], $accPara)->id)));
        $matters->addTeam($ma, $auditor, 'observer', $accOwn);
        $accObs = new Access($cidA, $auditor);
        $this->check('el «observador» del equipo ve lo privilegiado pero no actúa', $accObs->isInsider($ma) && !$accObs->canWorkOn($ma));
        $accPlat = new Access($cidA, $platform);
        $this->check('la plataforma (no miembro) NO accede sin autorización', !$accPlat->hasCompanyAccess() && !$accPlat->canSeeMatter($ma));
        $g = (new Support())->grant('zztest_platform@example.invalid', 2, 'Revisión de una incidencia reportada', $accOwn);
        $this->check('el despacho concede acceso de soporte temporal', $g->ok, $g->message);
        $accPlat = new Access($cidA, $platform);
        $this->check('con soporte: ve, pero no actúa ni ve lo privilegiado', $accPlat->canSeeMatter($ma) && !$accPlat->canWorkOn($ma) && !$accPlat->isInsider($ma));
        $grant = db_connect()->table('cmp_lgc_support_grants')->where('company_id', $cidA)->where('user_id', $platform)->get()->getRowArray();
        (new Support())->revoke((int) $grant['id'], $accOwn);
        $this->check('revocado el soporte, deja de ver', !(new Access($cidA, $platform))->canSeeMatter($ma));
        $this->check('no se concede soporte a un miembro del despacho', !(new Support())->grant('zztest_auditor@example.invalid', 2, 'Motivo suficientemente largo', $accOwn)->ok);

        // ── 11. Portal: coclientes, poder y privacidad ────────────────────────
        $this->title('11. Coclientes, poderes y privacidad');
        $portal = new Portal();
        $co   = $mk('ZZTEST Mario Cocliente', 'zz.mario@example.invalid', '222');
        $rep  = $mk('ZZTEST Rosa Apoderada', 'zz.rosa@example.invalid', '333');
        $matters->addParticipant($ma, $co, 'client', $accOwn);
        $matters->addParticipant($ma, $rep, 'representative', $accOwn);
        $parts = [];
        foreach ((new ParticipantModel())->where('matter_id', $a->id)->findAll() as $p) {
            $parts[(int) $p['contact_id']] = $p;
        }
        $g1 = $portal->grant($ma, $parts[$cliente], $accOwn, $this->sender());
        $g2 = $portal->grant($ma, $parts[$co], $accOwn, $this->sender());
        $acc1 = (new PortalAccountModel())->find((int) $g1->id);
        $acc2 = (new PortalAccountModel())->find((int) $g2->id);
        $this->check('el representante sin poder acreditado no recibe acceso', !$portal->grant($ma, $parts[$rep], $accOwn, $this->sender())->ok);
        $docs = new DocumentModel();
        $docs->insert(['company_id' => $cidA, 'matter_id' => $a->id, 'name' => 'poder.pdf', 'path' => 'p.bin', 'mime' => 'application/pdf', 'sha256' => str_repeat('9', 64), 'classification' => 'internal']);
        $matters->setAuthority($ma, (int) $parts[$rep]['id'], ['authority_document_id' => (int) $docs->getInsertID(), 'authority_until' => date('Y-m-d', strtotime('+30 days'))], $accOwn);
        $g3 = $portal->grant($ma, (new ParticipantModel())->find((int) $parts[$rep]['id']), $accOwn, $this->sender());
        $this->check('con poder vigente sí recibe acceso', $g3->ok, $g3->message);
        $acc3 = (new PortalAccountModel())->find((int) $g3->id);
        $view = new ClientView();
        $this->check('con el poder vencido deja de ver el asunto', (function () use ($view, $acc3, $a, $parts, $rep) {
            db_connect()->table('cmp_lgc_participants')->where('id', (int) $parts[$rep]['id'])->update(['authority_until' => date('Y-m-d', strtotime('-1 day'))]);

            return $view->matter($acc3, $a->id) === null;
        })());

        $this->check('el portal exige aceptar el aviso de privacidad', $portal->needsPrivacy($acc1));
        $portal->acceptPrivacy($acc1);
        $acc1 = (new PortalAccountModel())->find((int) $acc1['id']);
        $ct   = (new ContactModel())->find($cliente);
        $this->check('al aceptar queda la autorización en la cuenta y en el contacto', !$portal->needsPrivacy($acc1) && $ct['consent_status'] === 'granted' && $ct['consent_method'] === 'portal');
        (new SettingsModel())->where('company_id', $cidA)->set(['privacy_version' => '2'])->update();
        $this->check('una versión nueva del aviso exige aceptarlo otra vez', $portal->needsPrivacy($acc1));

        $msg = new MessageService($outbox);
        $mpub = $view->matter($acc1, $a->id);
        $t1 = $msg->clientWrites($acc1, $mpub, null, 'Privado', 'SECRETO-DE-LUCIA');
        $det2 = $view->detail($acc2, $view->matter($acc2, $a->id));
        $this->check('el cocliente NO lee el hilo del otro cliente', !str_contains(json_encode($det2['threads']), 'SECRETO-DE-LUCIA'));
        $this->check('el cocliente no puede responder en ese hilo', $view->thread($acc2, $a->id, (int) $t1->id) === null);
        $sh = $msg->staffStarts($ma, 'Para ambos', 'MENSAJE-COMPARTIDO', $accOwn, 'shared');
        $this->check('un hilo compartido por el despacho lo ven ambos', $sh->ok
            && str_contains(json_encode($view->detail($acc2, $view->matter($acc2, $a->id))['threads']), 'MENSAJE-COMPARTIDO')
            && str_contains(json_encode($view->detail($acc1, $view->matter($acc1, $a->id))['threads']), 'MENSAJE-COMPARTIDO'));
        $rq = $msg->requestDocument($ma, ['title' => 'Cédula de Lucía', 'account_id' => (int) $acc1['id']], $accOwn);
        $this->check('una solicitud dirigida a un cliente no la ve ni la entrega el otro', $view->request($acc2, (int) $rq->id) === null && $view->request($acc1, (int) $rq->id) !== null);

        // ── 12. Supervisión del personal no abogado ───────────────────────────
        $this->title('12. Supervisión del personal no abogado');
        $matters->addTeam($ma, $para, 'assistant', $accOwn);
        $thr = (new ThreadModel())->find((int) $t1->id);
        $r = $msg->staffReplies($ma, $thr, 'BORRADOR-DEL-PARALEGAL', 'answered', $accPara);
        $this->check('la respuesta del paralegal queda en revisión', $r->ok && (new ThreadModel())->find((int) $t1->id)['status'] === 'review');
        $this->check('el cliente no ve el borrador', !str_contains(json_encode($view->detail($acc1, $view->matter($acc1, $a->id))['threads']), 'BORRADOR-DEL-PARALEGAL'));
        $pm = (new MessageModel())->where('thread_id', (int) $t1->id)->where('status', 'pending_review')->first();
        $this->check('el autor no aprueba su propio mensaje', !$msg->review($ma, $pm, true, $accPara)->ok);
        $this->check('un abogado lo aprueba y entonces llega al cliente', $msg->review($ma, $pm, true, $accOwn)->ok
            && str_contains(json_encode($view->detail($acc1, $view->matter($acc1, $a->id))['threads']), 'BORRADOR-DEL-PARALEGAL'));
        $docs->insert(['company_id' => $cidA, 'matter_id' => $a->id, 'name' => 'memo.pdf', 'path' => 'm.bin', 'mime' => 'application/pdf', 'sha256' => str_repeat('8', 64), 'classification' => 'privileged']);
        $memo = (new DocumentModel())->find((int) $docs->getInsertID());
        $this->check('el paralegal no comparte documentos con el cliente', !(new DocumentService())->classify($memo, 'shared', $accPara)->ok);
        $this->check('compartir un privilegiado exige confirmación expresa', !(new DocumentService())->classify($memo, 'shared', $accOwn)->ok
            && (new DocumentService())->classify($memo, 'shared', $accOwn, true)->ok);
        $dr = $msg->dataRightsRequest($acc1, $mpub, 'supresion', 'Quiero que eliminen mis datos.');
        $drt = (new ThreadModel())->find((int) $dr->id);
        $this->check('la solicitud de datos personales se registra con plazo legal (≥ 15 días hábiles)', $drt['kind'] === 'data_rights'
            && strtotime((string) $drt['first_response_due_at']) >= strtotime('+18 days'));

        // ── 13. Alertas, retención, reasignación ─────────────────────────────
        $this->title('13. Alertas, conservación y reasignación');
        (new DeadlineService())->createDeadline($ma, ['title' => 'Vence mañana', 'due_at' => date('Y-m-d H:i', strtotime('+20 hours')), 'assigned_to' => $owner], $accOwn);
        $clock = new Clock($outbox);
        $this->mail = [];
        $n1 = $clock->deadlineAlerts($cidA);
        $n2 = $clock->deadlineAlerts($cidA);
        $this->check('alerta interna de plazo próximo, una sola vez por ventana', $n1 >= 1 && $n2 === 0 && $this->mail !== [], "$n1/$n2");
        $this->check('el aviso interno no lleva el contenido del plazo', !str_contains($this->mail[0]['html'] ?? '', 'Vence mañana'));
        $this->check('un asunto con documentos no se elimina (conservación)', !$matters->delete($ma, $accOwn)->ok);
        $vacio = $matters->create(['title' => 'ZZTEST creado por error', 'lead_lawyer_id' => $owner], $accOwn);
        $mv = (new MatterModel())->find($vacio->id);
        $matters->setLegalHold($mv, true, $accOwn);
        $this->check('el bloqueo legal impide eliminar', !$matters->delete((new MatterModel())->find($vacio->id), $accOwn)->ok);
        $matters->setLegalHold($mv, false, $accOwn);
        $this->check('un asunto vacío creado por error sí se elimina', $matters->delete((new MatterModel())->find($vacio->id), $accOwn)->ok);
        $re = $matters->reassign($owner, $lawyer2, $accOwn);
        $this->check('reasignar traslada asuntos, plazos y conversaciones', $re->ok && ($re->data['matters'] ?? 0) > 0);
        $this->check('no se reasigna a alguien de fuera del despacho', !$matters->reassign($lawyer2, $platform, $accOwn)->ok);
    }

    // ------------------------------------------------------------------ 1.2 gestión de la práctica

    private function practice(int $cidA, int $owner, int $para, Outbox $outbox): void
    {
        $accOwn   = new Access($cidA, $owner);
        $accPara  = new Access($cidA, $para);
        $matters  = new MatterService();
        $contacts = new ContactModel();
        $mk = function (string $name, string $email, string $cap = 'full') use ($contacts, $cidA): int {
            $contacts->insert(['company_id' => $cidA, 'kind' => 'person', 'name' => $name, 'email' => $email, 'doc_number' => (string) random_int(10000, 99999999), 'locale' => 'es', 'legal_capacity' => $cap]);

            return (int) $contacts->getInsertID();
        };

        // ── 14. Calendario y cálculo de plazos ────────────────────────────────
        $this->title('14. Calendario de términos');
        $fest = Calendar::colombia(2026);
        $this->check('festivos 2026 (Ley Emiliani y Pascua) correctos', isset($fest['2026-01-12'], $fest['2026-03-23'], $fest['2026-04-02'], $fest['2026-04-03'], $fest['2026-05-18'], $fest['2026-06-08'], $fest['2026-06-15'], $fest['2026-11-16']));
        (new SettingsModel())->where('company_id', $cidA)->set(['calendar_mode' => 'judicial'])->update();
        $cal = new Calendar($cidA);
        $this->check('3 días hábiles desde el 1-abr-2026 saltan Semana Santa → 8-abr', $cal->addBusinessDays('2026-04-01', 3) === '2026-04-08', $cal->addBusinessDays('2026-04-01', 3));
        $this->check('modo judicial: salta la vacancia y el festivo de Reyes 2027 → 12-ene-2027', $cal->addBusinessDays('2026-12-18', 1) === '2027-01-12', $cal->addBusinessDays('2026-12-18', 1));
        (new SettingsModel())->where('company_id', $cidA)->set(['calendar_mode' => 'common'])->update();
        $this->check('modo común: la vacancia no cuenta → 21-dic-2026', (new Calendar($cidA))->addBusinessDays('2026-12-18', 1) === '2026-12-21');
        (new CalendarDayModel())->insert(['company_id' => $cidA, 'day' => '2026-12-21', 'kind' => 'suspension', 'name' => 'ZZTEST suspensión', 'created_at' => date('Y-m-d H:i:s')]);
        $this->check('un día de suspensión del despacho también se salta → 22-dic', (new Calendar($cidA))->addBusinessDays('2026-12-18', 1) === '2026-12-22');
        (new SettingsModel())->where('company_id', $cidA)->set(['calendar_mode' => 'judicial'])->update();

        $cli = $mk('ZZTEST Pilar Práctica', 'zz.pilar@example.invalid');
        $m   = $matters->create(['title' => 'ZZTEST práctica', 'client_contact_id' => $cli, 'lead_lawyer_id' => $owner], $accOwn);
        $mx  = (new MatterModel())->find($m->id);
        $dl  = (new DeadlineService())->createDeadline($mx, ['title' => 'Contestar', 'starts_on' => '2026-04-01', 'term_days' => 3, 'term_unit' => 'business'], $accOwn);
        $row = (new DeadlineModel())->find((int) $dl->id);
        $this->check('el plazo calculado propone la fecha y sigue «por validar»', $dl->ok && str_starts_with((string) $row['due_at'], '2026-04-08') && $row['status'] === 'to_validate');
        $this->check('el fundamento deja constancia del cómputo y de los días saltados', str_contains((string) $row['basis'], '2026-04-08') && str_contains((string) $row['basis'], '2026-04-02'));

        // ── 15. Audiencias ────────────────────────────────────────────────────
        $this->title('15. Audiencias');
        $hs = new HearingService($outbox);
        $this->check('audiencia virtual sin enlace ni lugar se rechaza', !$hs->save($mx, ['title' => 'Inicial', 'scheduled_at' => date('Y-m-d H:i', strtotime('+3 days')), 'mode' => 'virtual'], $accOwn)->ok);
        $this->check('un enlace que no es https se rechaza', !$hs->save($mx, ['title' => 'Inicial', 'scheduled_at' => date('Y-m-d H:i', strtotime('+3 days')), 'mode' => 'virtual', 'link' => 'http://x.example'], $accOwn)->ok);
        $this->check('el paralegal no la comparte con el cliente', !$hs->save($mx, ['title' => 'Inicial', 'scheduled_at' => date('Y-m-d H:i', strtotime('+3 days')), 'mode' => 'in_person', 'is_shared' => 1], $accPara)->ok);
        $h = $hs->save($mx, ['title' => 'Audiencia inicial', 'scheduled_at' => date('Y-m-d H:i', strtotime('+20 hours')), 'mode' => 'virtual',
            'link' => 'https://meet.example/abc', 'internal_notes' => 'NOTA-INTERNA-AUDIENCIA', 'client_notes' => 'Traiga su cédula', 'is_shared' => 1, 'client_attendance' => 1], $accOwn);
        $this->check('se programa y crea la tarea de preparación', $h->ok && (new TaskModel())->where('ref', 'hearing_prep:' . $h->id)->where('status', 'open')->countAllResults() === 1);

        $parts = [];
        foreach ((new ParticipantModel())->where('matter_id', $m->id)->findAll() as $p) {
            $parts[(int) $p['contact_id']] = $p;
        }
        $portal = new Portal();
        $g   = $portal->grant($mx, $parts[$cli], $accOwn, $this->sender());
        $acc = (new PortalAccountModel())->find((int) $g->id);
        $view = new ClientView();
        $det  = $view->detail($acc, $view->matter($acc, $m->id));
        $this->check('el cliente ve la audiencia compartida, sin las notas internas', count($det['hearings']) === 1 && !str_contains(json_encode($det['hearings']), 'NOTA-INTERNA'));
        $before = (new DeliveryModel())->where('matter_id', $m->id)->where('kind', 'hearing')->countAllResults();
        $hrow   = (new HearingModel())->find((int) $h->id);
        $hs->save($mx, ['title' => 'Audiencia inicial', 'scheduled_at' => date('Y-m-d H:i', strtotime('+2 days')), 'mode' => 'virtual', 'link' => 'https://meet.example/abc', 'is_shared' => 1], $accOwn, $hrow);
        $hs->save($mx, ['title' => 'Audiencia inicial', 'scheduled_at' => date('Y-m-d H:i', strtotime('+2 days')), 'mode' => 'virtual', 'link' => 'https://meet.example/abc', 'is_shared' => 1], $accOwn, (new HearingModel())->find((int) $h->id));
        $after = (new DeliveryModel())->where('matter_id', $m->id)->where('kind', 'hearing')->countAllResults();
        $this->check('reprogramar avisa otra vez; guardar sin cambios no', $after === $before + 1, "$before → $after");
        $cur  = static fn () => (new HearingModel())->find((int) $h->id);
        $hin  = static fn (string $at, array $x = []) => $x + ['title' => 'Audiencia inicial', 'scheduled_at' => $at, 'mode' => 'virtual', 'link' => 'https://meet.example/abc', 'is_shared' => 1];
        $prep = static fn () => (new TaskModel())->where('ref', 'hearing_prep:' . $h->id)->orderBy('id', 'DESC')->first();
        $hs->save($mx, $hin(substr((string) $cur()['scheduled_at'], 0, 16), ['is_shared' => 0, 'internal_notes' => 'Editada por paralegal']), $accPara, $cur());
        $this->check('quien no tiene lgc.share la edita sin dejar de compartirla', (int) $cur()['is_shared'] === 1 && $cur()['internal_notes'] === 'Editada por paralegal');
        $this->check('una audiencia compartida y vigente no se elimina (se cancela)', !$hs->delete($mx, $cur(), $accOwn)->ok && $cur() !== null);
        $hs->setStatus($mx, $cur(), 'postponed', $accOwn);
        $this->check('aplazarla avisa al cliente y suspende la preparación',
            (new DeliveryModel())->where('matter_id', $m->id)->where('ref_type', 'hearing_postponed')->countAllResults() === 1 && $prep()['status'] === 'cancelled');
        $hs->save($mx, $hin(date('Y-m-d H:i', strtotime('+5 days'))), $accOwn, $cur());
        $this->check('reprogramar una aplazada la devuelve a programada y mueve la preparación',
            $cur()['status'] === 'scheduled' && $prep()['status'] === 'open' && str_starts_with((string) $prep()['due_at'], date('Y-m-d', strtotime('+3 days'))), json_encode([$cur()['status'], $prep()['status'], $prep()['due_at']]));
        $hs->setStatus($mx, (new HearingModel())->find((int) $h->id), 'held', $accOwn);
        $this->check('marcada como realizada, la preparación se cierra', (new TaskModel())->where('ref', 'hearing_prep:' . $h->id)->where('status', 'done')->countAllResults() === 1);
        $hc = $hs->save($mx, $hin(date('Y-m-d H:i', strtotime('+4 days')), ['title' => 'Diligencia ZZ']), $accOwn);
        $hs->setStatus($mx, (new HearingModel())->find((int) $hc->id), 'cancelled', $accOwn);
        $det = $view->detail($acc, $view->matter($acc, $m->id));
        $seen = array_values(array_filter($det['hearings'], static fn ($x) => (int) $x['id'] === (int) $hc->id));
        $this->check('el portal muestra la cancelación y el cliente recibe aviso',
            ($seen[0]['status'] ?? '') === 'cancelled' && (new DeliveryModel())->where('ref_type', 'hearing_cancelled')->where('ref_id', (int) $hc->id)->countAllResults() === 1);
        $this->check('una audiencia ya cerrada sí se puede eliminar, y deja constancia',
            $hs->delete($mx, (new HearingModel())->find((int) $hc->id), $accOwn)->ok && (new HearingModel())->find((int) $hc->id) === null
            && (new AuditModel())->where('action', 'hearing.deleted')->where('entity_id', (int) $hc->id)->countAllResults() === 1);

        // ── 16. Versiones y firma electrónica ─────────────────────────────────
        $this->title('16. Versiones de documentos y firma electrónica');
        $dir = WRITEPATH . 'uploads/lgc/' . $cidA;
        @mkdir($dir, 0750, true);
        $put = function (string $content, array $extra = []) use ($dir, $cidA, $m): array {
            $name = bin2hex(random_bytes(8)) . '.bin';
            file_put_contents($dir . '/' . $name, $content);
            $model = new DocumentModel();
            $model->insert($extra + ['company_id' => $cidA, 'matter_id' => $m->id, 'name' => 'contrato.pdf', 'path' => $name, 'mime' => 'application/pdf',
                'size' => strlen($content), 'sha256' => hash('sha256', $content), 'classification' => 'shared', 'is_current' => 1, 'version' => 1]);

            return (new DocumentModel())->find((int) $model->getInsertID());
        };
        $v1 = $put('%PDF version 1');
        $v2 = $put('%PDF version 2', ['version' => 2, 'previous_id' => (int) $v1['id']]);
        (new DocumentModel())->update((int) $v1['id'], ['is_current' => 0]);
        $docsIds = array_column($view->detail($acc, $view->matter($acc, $m->id))['docs'], 'id');
        $this->check('el cliente solo ve la versión vigente', in_array((string) $v2['id'], array_map('strval', $docsIds), true) && !in_array((string) $v1['id'], array_map('strval', $docsIds), true));
        $this->check('la historia de versiones se conserva', count((new DocumentService())->history((new DocumentModel())->find((int) $v2['id']))) === 2);

        $sig = new SignatureService($outbox);
        $this->check('no se pide firma sobre una versión anterior', !$sig->request($mx, (new DocumentModel())->find((int) $v1['id']), (int) $acc['id'], 'x', $accOwn)->ok);
        $this->check('el paralegal no pide firmas', !$sig->request($mx, $v2, (int) $acc['id'], 'x', $accPara)->ok);
        $es = new EngagementService();
        $this->check('el contrato exige describir el alcance', !$es->save($mx, ['kind' => 'services_contract', 'fee_type' => 'fixed'], $accOwn)->ok);
        $es->save($mx, ['kind' => 'services_contract', 'fee_type' => 'contingency', 'contingency_pct' => '20', 'scope' => 'Primera instancia', 'document_id' => (int) $v2['id']], $accOwn);
        $send = $es->sendForSignature($mx, (int) $acc['id'], $accOwn);
        $this->check('el contrato se envía a firma', $send->ok && $es->current($m->id)['status'] === 'sent', $send->message);
        $this->check('no se duplica una firma pendiente', !$sig->request($mx, $v2, (int) $acc['id'], 'otra', $accOwn)->ok);
        $srow = $view->signature($acc, (int) $send->id);
        $this->check('firmar exige nombre completo y aceptación', !$sig->sign($acc, $srow, 'Pi', '', true)->ok && !$sig->sign($acc, $srow, 'Pilar Práctica', '', false)->ok);
        $ok = $sig->sign($acc, $srow, 'Pilar Práctica Pérez', '12345678', true);
        $signed = (new SignatureModel())->find((int) $send->id);
        $this->check('firmado, con evidencia íntegra y el contrato queda firmado', $ok->ok && SignatureService::verify($signed) && $es->current($m->id)['status'] === 'signed');
        db_connect()->table('cmp_lgc_signatures')->where('id', (int) $send->id)->update(['signer_name' => 'Otra persona']);
        $this->check('si alguien altera la evidencia, se detecta', !SignatureService::verify((new SignatureModel())->find((int) $send->id)));
        $this->check('un contrato firmado no se edita', !$es->save($mx, ['kind' => 'services_contract', 'fee_type' => 'fixed', 'scope' => 'Cambio'], $accOwn)->ok);
        $v3  = $put('%PDF otro documento');
        $req = $sig->request($mx, $v3, (int) $acc['id'], 'Anexo', $accOwn);
        file_put_contents($dir . '/' . $v3['path'], '%PDF ALTERADO');
        $this->check('si el archivo cambió después de pedir la firma, no se firma', !$sig->sign($acc, $view->signature($acc, (int) $req->id), 'Pilar Práctica Pérez', '', true)->ok);

        // ── 17. Menores e incapaces ───────────────────────────────────────────
        $this->title('17. Menores e incapaces');
        $menor = $mk('ZZTEST Tomás Menor', 'zz.tomas@example.invalid', 'minor');
        $tutor = $mk('ZZTEST Teresa Tutora', 'zz.teresa@example.invalid');
        $m2 = $matters->create(['title' => 'ZZTEST alimentos', 'lead_lawyer_id' => $owner], $accOwn);
        $mm = (new MatterModel())->find($m2->id);
        $matters->addParticipant($mm, $menor, 'client', $accOwn);
        $matters->addParticipant($mm, $tutor, 'guardian', $accOwn);
        $mm = (new MatterModel())->find($m2->id);
        $this->check('con un menor, el asunto pasa a sensible y restringido al equipo', $mm['sensitivity'] === 'high' && $mm['visibility'] === 'team');
        $pp = [];
        foreach ((new ParticipantModel())->where('matter_id', $m2->id)->findAll() as $p) {
            $pp[(int) $p['contact_id']] = $p;
        }
        $this->check('el menor no recibe acceso al portal', !$portal->grant($mm, $pp[$menor], $accOwn, $this->sender())->ok);
        $this->check('el tutor sin documento que lo acredite tampoco', !$portal->grant($mm, $pp[$tutor], $accOwn, $this->sender())->ok);
        $doc = $put('%PDF discernimiento', ['matter_id' => $m2->id, 'classification' => 'internal']);
        $matters->setAuthority($mm, (int) $pp[$tutor]['id'], ['authority_document_id' => (int) $doc['id']], $accOwn);
        $this->check('el tutor acreditado sí accede', $portal->grant($mm, (new ParticipantModel())->find((int) $pp[$tutor]['id']), $accOwn, $this->sender())->ok);

        // ── 18. Derechos del titular ──────────────────────────────────────────
        $this->title('18. Exportación y supresión de datos');
        $ds  = new DataSubjectService();
        $exp = $ds->export($cidA, $cli);
        $this->check('la exportación incluye datos, autorización y asuntos (sin contenido privilegiado)', $exp && $exp['contact']['name'] === 'ZZTEST Pilar Práctica' && count($exp['matters']) >= 1 && !str_contains(json_encode($exp), 'strategy'));
        $this->check('no se suprime a quien tiene un asunto con deber de conservación', !$ds->anonymize($cidA, $cli, $accOwn)->ok);
        $solo = $mk('ZZTEST Sin Asuntos', 'zz.solo@example.invalid');
        $an   = $ds->anonymize($cidA, $solo, $accOwn);
        $c    = (new ContactModel())->find($solo);
        $this->check('sin asuntos, se suprimen sus datos identificativos', $an->ok && !str_contains((string) $c['name'], 'Sin Asuntos') && $c['email'] === '');
        $this->check('la exportación de otro despacho no se resuelve', $ds->export($cidA + 999999, $cli) === null);
    }
}
