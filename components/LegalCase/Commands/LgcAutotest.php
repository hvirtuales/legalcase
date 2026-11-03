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
use Components\LegalCase\Libraries\UpdateService;
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
            $db->table('users')->where('id', $uid)->delete();
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
        $cidB     = $this->company('Despacho B', $outsider, []);
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
        $matters->close($m1, 'completed', $accOwn);
        $this->check('cerrar desactiva el plan de comunicación', (int) db_connect()->table('cmp_lgc_comm_plans')->where('matter_id', $r1->id)->get()->getRowArray()['is_active'] === 0);
        $this->check('un asunto cerrado no recibe borradores normales', !$upd->createDraft((new MatterModel())->find($r1->id), 'regular', ['what_happened' => 'x'], $accOwn)->ok);
    }
}
