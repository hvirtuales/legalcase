<?php

namespace Components\Mailer\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use App\Models\AppSettingModel;
use Components\Mailer\Libraries\MailerService;
use Components\Mailer\Libraries\PlanGuard;
use Components\Mailer\Libraries\ReputationGuard;
use Components\Mailer\Models\CampaignModel;
use Components\Mailer\Models\ContactModel;
use Components\Mailer\Models\ListModel;
use Components\Mailer\Models\RecipientModel;
use Components\Mailer\Models\TemplateModel;

/** Campañas de envío masivo por Amazon SES. Aisladas por empresa activa. */
class Campaigns extends BaseController
{
    private CampaignModel $model;

    public function __construct()
    {
        $this->model = new CampaignModel();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Mailer\Views\campaigns\index', ['title' => lang('Mailer.campaigns'), 'dt' => null]);
        }
        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'subject'])
            ->sortable(['name', 'status', 'created_at', 'sent_count'])
            ->defaultSort('created_at', 'desc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        return view('Components\Mailer\Views\campaigns\index', [
            'title'          => lang('Mailer.campaigns'),
            'dt'             => $dt,
            'serviceEnabled' => (new PlanGuard())->serviceEnabled($companyId),
            'quota'          => (new PlanGuard())->emailsLeft($companyId),
            'reputation'     => (new ReputationGuard())->status($companyId),
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mNoCompany'));
        }
        if (!(new PlanGuard())->serviceEnabled($companyId)) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mNoPlan'));
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save(null, $companyId);
        }

        // El alta entra por el PASO 1 del asistente. Antes era un formulario
        // único con todo a la vez —incluido el cuerpo del correo en un cuadro de
        // HTML crudo—, que es justo lo que no se puede pedir de golpe.
        return view('Components\Mailer\Views\campaigns\wizard',
            $this->wizardData($companyId, [], 1));
    }

    /**
     * El asistente sobre una campaña ya creada.
     *
     * Tres pasos, y cada uno guarda lo suyo: destinatarios, contenido, y
     * revisión antes de enviar. Se sirve por GET con `?paso=`, y cada paso envía
     * su propio POST: sin JavaScript se sigue pudiendo recorrer entero.
     */
    public function wizard($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }

        $companyId = (int) $c['company_id'];
        $step      = (int) ($this->request->getGet('paso') ?? 1);
        $step      = $step >= 1 && $step <= 3 ? $step : 1;

        if ($this->request->getMethod() === 'POST') {
            return $this->saveStep((int) $id, $companyId, (int) $this->request->getPost('paso'));
        }

        // Una campaña ya enviada no se edita: se mira.
        if (!in_array($c['status'], ['draft', 'failed'], true)) {
            return redirect()->to('mailer/campanas/' . $id)
                ->with('error', lang('Mailer.mCampDraftOnly'));
        }

        return view('Components\Mailer\Views\campaigns\wizard',
            $this->wizardData($companyId, $c, $step));
    }

    /** Bloqueo por reputación: si la empresa excede rebotes/quejas, no dejar enviar. */
    private function reputationBlock(int $companyId): ?string
    {
        $st = (new ReputationGuard())->status($companyId);

        return $st['level'] === 'block'
            ? lang('Mailer.mRepBlocked', [$st['reason']])
            : null;
    }

    /**
     * «Editar» es ahora el asistente. Se conserva la ruta porque hay enlaces
     * guardados apuntando aquí, y porque un marcador de alguien no tiene por qué
     * romperse cuando cambia la pantalla.
     */
    public function edit($id)
    {
        return redirect()->to('mailer/campanas/asistente/' . (int) $id . '?paso=2');
    }

    private function formData(int $companyId, array $campaign): array
    {
        $settings = (new AppSettingModel())->getAllSettings();

        return [
            'title'     => $campaign ? lang('Mailer.editCampaign') : lang('Mailer.wizNew'),
            'campaign'  => $campaign,
            'lists'     => (new ListModel())->where('company_id', $companyId)->orderBy('name', 'asc')->findAll(),
            'templates' => (new TemplateModel())->where('company_id', $companyId)->orderBy('name', 'asc')->findAll(),
            'sesFrom'   => (string) ($settings['ses_from_email'] ?? ''),
        ];
    }

    /** Lo que necesita el asistente, sea cual sea el paso. */
    private function wizardData(int $companyId, array $campaign, int $step): array
    {
        $settings = (new AppSettingModel())->getAllSettings();
        $company  = (new \App\Models\CompanyModel())->find($companyId) ?? [];

        $data = [
            'title'     => $campaign ? lang('Mailer.campaignIs', [$campaign['name']]) : lang('Mailer.wizNew'),
            'campaign'  => $campaign,
            'step'      => $step,
            'lists'     => (new ListModel())->where('company_id', $companyId)->orderBy('name', 'asc')->findAll(),
            'templates' => (new TemplateModel())->where('company_id', $companyId)->orderBy('name', 'asc')->findAll(),
            'sesFrom'   => (string) ($settings['ses_from_email'] ?? ''),
            'company'   => $company,
            'registry'  => \Components\Mailer\Libraries\EmailRegistry::forJs(),
            'groups'    => \Components\Mailer\Libraries\EmailRegistry::groupLabels(),

        ];

        // El paso 3 mira si de verdad se puede enviar: cuántos contactos hay,
        // qué dice el plan y qué dice la reputación.
        if ($campaign) {
            $sendable = (new ContactModel())
                ->where('company_id', $companyId)
                ->where('list_id', $campaign['list_id'])
                ->where('status', ContactModel::SENDABLE)
                ->countAllResults();

            $data['sendable']       = $sendable;
            $data['quota']          = (new PlanGuard())->emailsLeft($companyId);
            $data['reputation']     = (new ReputationGuard())->status($companyId);
            $data['missingAddress'] = trim((string) ($company['address'] ?? '')) === '';
        }

        return $data;
    }

    /**
     * Guarda un paso del asistente y avanza al siguiente.
     *
     * Cada paso escribe SOLO sus campos: así volver atrás a cambiar la lista no
     * puede llevarse por delante el contenido ya montado.
     */
    private function saveStep(int $id, int $companyId, int $step)
    {
        $data = [];

        if ($step === 1) {
            $data = [
                'name'    => trim((string) $this->request->getPost('name')),
                'list_id' => (int) $this->request->getPost('list_id'),
            ];
        } elseif ($step === 2) {
            $design = (new \Components\Mailer\Libraries\DesignSanitizer())
                ->clean((string) $this->request->getPost('design_json'));

            // El HTML lo produce el SERVIDOR a partir del árbol saneado. El
            // navegador solo edita el diseño: si su JavaScript falla, el correo
            // no se queda vacío ni sale sin revisar.
            $html = (new \Components\Mailer\Libraries\EmailRenderer(
                (new \App\Models\CompanyModel())->find($companyId) ?? []
            ))->render($design);

            $data = [
                'subject'     => trim((string) $this->request->getPost('subject')),
                'from_name'   => trim((string) $this->request->getPost('from_name')) ?: null,
                'from_email'  => trim((string) $this->request->getPost('from_email')) ?: null,
                'reply_to'    => trim((string) $this->request->getPost('reply_to')) ?: null,
                'design_json' => json_encode($design, JSON_UNESCAPED_UNICODE),
                'body_html'   => $html,
            ];
        }

        if ($data) {
            // El gotcha de CI4 al actualizar: con `is_unique` u otras reglas que
            // miran el propio registro, guardar un subconjunto de campos falla.
            // Aquí se validan a mano los dos obligatorios del paso.
            if ($step === 1 && ($data['name'] === '' || $data['list_id'] <= 0)) {
                return redirect()->back()->withInput()
                    ->with('errors', [lang('Mailer.mStep1Missing')]);
            }
            if ($step === 2 && $data['subject'] === '') {
                return redirect()->back()->withInput()->with('errors', [lang('Mailer.vrSubject')]);
            }

            // El remitente tiene que estar verificado en SES o el envío entero
            // se rechaza. Mejor decirlo aquí que descubrirlo con la campaña ya
            // lanzada. Si NO se puede comprobar —SES apagado, permiso que
            // falta— se deja pasar: una comprobación que no responde no puede
            // bloquear el trabajo de nadie.
            if ($step === 2 && !empty($data['from_email'])) {
                $ses = new \Components\Mailer\Libraries\SesMailer();
                if ($ses->isEnabled() && $ses->isVerified($data['from_email']) === false) {
                    return redirect()->back()->withInput()
                        ->with('errors', [lang('Mailer.senderNotVerified', [$data['from_email']])]);
                }
            }

            $this->model->skipValidation(true);
            $this->model->update($id, $data);
            $this->model->skipValidation(false);
        }

        $next = min(3, max(1, $step + 1));

        return redirect()->to('mailer/campanas/asistente/' . $id . '?paso=' . $next)
            ->with('success', lang('Mailer.mSaved'));
    }

    /**
     * Alta desde el PASO 1 del asistente: nombre, lista y, si se eligió, la
     * plantilla de la que parte el contenido.
     *
     * El asunto nace con el nombre de la campaña porque el modelo lo exige y en
     * el paso 1 todavía no se ha escrito: se afina en el paso 2, que es donde
     * toca. Poner ahí un texto provisional es más honesto que relajar la regla.
     */
    private function save(?int $id, int $companyId)
    {
        $name   = trim((string) $this->request->getPost('name'));
        $listId = (int) $this->request->getPost('list_id');
        $tplId  = $this->request->getPost('template_id') ? (int) $this->request->getPost('template_id') : null;

        $data = [
            'company_id'  => $companyId,
            'list_id'     => $listId,
            'template_id' => $tplId,
            'name'        => $name,
            'subject'     => $name,
            'status'      => 'draft',
        ];

        // Partir de una plantilla es copiar SU DISEÑO, no enlazarlo: a partir de
        // ahí la campaña es suya y editarla no toca la plantilla.
        if ($tplId) {
            $tpl = (new TemplateModel())->where('company_id', $companyId)->find($tplId);
            if ($tpl) {
                $design = (new \Components\Mailer\Libraries\DesignSanitizer())->clean($tpl['design_json'] ?? '');
                $data['design_json'] = json_encode($design, JSON_UNESCAPED_UNICODE);
                $data['body_html']   = (new \Components\Mailer\Libraries\EmailRenderer(
                    (new \App\Models\CompanyModel())->find($companyId) ?? []
                ))->render($design);
                if (trim((string) ($tpl['subject'] ?? '')) !== '') {
                    $data['subject'] = $tpl['subject'];
                }
            }
        }

        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('mailer/campanas/asistente/' . $this->model->getInsertID() . '?paso=2')
            ->with('success', lang('Mailer.mCampCreated'));
    }

    public function show($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }
        $companyId = (int) $c['company_id'];
        $list      = (new ListModel())->find($c['list_id']);
        $sendable  = (new ContactModel())
            ->where('company_id', $companyId)->where('list_id', $c['list_id'])
            ->where('status', ContactModel::SENDABLE)->countAllResults();

        $svc = new MailerService();

        return view('Components\Mailer\Views\campaigns\show', [
            'title'      => lang('Mailer.campaignIs', [$c['name']]),
            'campaign'   => $c,
            'list'       => $list,
            'sendable'   => $sendable,
            'counts'     => $svc->recount((int) $id),
            'quota'      => (new PlanGuard())->emailsLeft($companyId),
            'engagement' => $svc->engagement((int) $id),
            'topLinks'   => $svc->topLinks((int) $id),
            'reputation' => (new ReputationGuard())->status($companyId),
            'missingAddress' => trim((string) ((new \App\Models\CompanyModel())->find($companyId)['address'] ?? '')) === '',
        ]);
    }

    /** Reporte de envío: resumen + interacción + detalle por destinatario (imprimible). */
    public function report($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }
        $svc = new MailerService();

        $dt = (new DataTable(new RecipientModel(), $this->request))
            ->searchable(['email', 'name'])
            ->sortable(['email', 'status', 'sent_at', 'open_count', 'click_count'])
            ->defaultSort('id', 'asc')
            ->query(fn ($m) => $m->where('campaign_id', $id))
            ->paginate(25);

        return view('Components\Mailer\Views\campaigns\report', [
            'title'      => 'Reporte: ' . $c['name'],
            'campaign'   => $c,
            'list'       => (new ListModel())->find($c['list_id']),
            'counts'     => $svc->recount((int) $id),
            'engagement' => $svc->engagement((int) $id),
            'topLinks'   => $svc->topLinks((int) $id, 10),
            'dt'         => $dt,
        ]);
    }

    /** Exporta el detalle por destinatario del reporte a CSV (BOM UTF-8). */
    public function reportCsv($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }
        $labels = [
            'pending' => 'Pendiente', 'sent' => 'Enviado', 'failed' => 'Fallido',
            'bounced' => 'Rebote', 'complained' => 'Queja', 'skipped' => 'Omitido',
        ];
        $rows = (new RecipientModel())->where('campaign_id', $id)->orderBy('id', 'asc')->findAll();

        $out = "\xEF\xBB\xBF" . lang('Mailer.csvHead') . "\r\n";
        $cell = static fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"';
        foreach ($rows as $r) {
            $out .= implode(';', [
                $cell($r['email']),
                $cell($r['name']),
                $cell($r['entity'] ?? ''),
                $cell($labels[$r['status']] ?? $r['status']),
                $cell($r['error']),
                $cell($r['sent_at']),
                $cell((int) $r['open_count']),
                $cell($r['opened_at']),
                $cell((int) $r['click_count']),
                $cell($r['clicked_at']),
            ]) . "\r\n";
        }

        $file = 'reporte_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $c['name']) . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file . '"')
            ->setBody($out);
    }

    /** Vista previa del correo (HTML del email con datos de muestra) — se muestra en un iframe. */
    public function preview($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return $this->response->setStatusCode(404)->setBody(lang('Mailer.mCampNotFound'));
        }
        $body = (new MailerService())->previewHtml($c);
        $html = '<!doctype html><html lang="' . esc(lang('Mailer.htmlLang'), 'attr') . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . esc(lang('Mailer.previewPage')) . '</title></head><body style="margin:0;padding:0">' . $body . '</body></html>';

        return $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8')->setBody($html);
    }

    /** Envía un correo de prueba a una dirección (no afecta la cola ni los contadores). */
    public function test($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }
        $to = strtolower(trim((string) $this->request->getPost('test_email')));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mTestBadEmail'));
        }
        if (trim((string) $c['body_html']) === '') {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mCampNoBodySend'));
        }

        $res = (new MailerService())->sendTest($c, $to);
        if ($res['ok']) {
            return redirect()->to('mailer/campanas/' . $id)->with('success', lang('Mailer.mTestSent', [$to]));
        }

        return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mTestFail', [$res['message']]));
    }

    /** Envía (o continúa) la campaña: encola y procesa un lote. */
    public function send($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }
        $companyId = (int) $c['company_id'];
        $guard     = new PlanGuard();

        if (!$guard->serviceEnabled($companyId)) {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mNoPlanShort'));
        }
        if (in_array($c['status'], ['sent'], true)) {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mCampAlreadySent'));
        }
        if (trim((string) $c['body_html']) === '') {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mCampNoBody'));
        }
        if ($block = $this->reputationBlock($companyId)) {
            return redirect()->to('mailer/campanas/' . $id)->with('error', $block);
        }

        $svc = new MailerService();
        $svc->buildQueue((int) $id);

        // Cupo del plan: no exceder el restante mensual en este lote.
        $left  = $guard->emailsLeft($companyId);
        if ($left !== null && $left <= 0) {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mNoQuota'));
        }
        $batch = MailerService::BATCH;
        if ($left !== null) {
            $batch = min($batch, $left);
        }

        $res = $svc->processBatch((int) $id, $batch);

        if ($res['done']) {
            $msg = lang('Mailer.mSendDone', [$res['sent'], $res['failed']]);
        } else {
            $msg = lang('Mailer.mSendBatch', [$res['sent'], $res['failed'], $res['remaining']]);
        }

        return redirect()->to('mailer/campanas/' . $id)->with('success', $msg);
    }

    /** Encola la campaña para envío automático por cron (no envía sincrónicamente). */
    public function queue($id)
    {
        $c = $this->owned($id);
        if (!$c) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }
        $companyId = (int) $c['company_id'];

        if (!(new PlanGuard())->serviceEnabled($companyId)) {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mNoPlanShort'));
        }
        if ($c['status'] === 'sent') {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mCampAlreadySent'));
        }
        if (trim((string) $c['body_html']) === '') {
            return redirect()->to('mailer/campanas/' . $id)->with('error', lang('Mailer.mCampNoBody'));
        }
        if ($block = $this->reputationBlock($companyId)) {
            return redirect()->to('mailer/campanas/' . $id)->with('error', $block);
        }

        $queued = (new MailerService())->buildQueue((int) $id);

        return redirect()->to('mailer/campanas/' . $id)->with(
            'success',
            lang('Mailer.mQueued', [$queued])
        );
    }

    public function delete($id)
    {
        if (!$this->owned($id)) {
            return redirect()->to('mailer/campanas')->with('error', lang('Mailer.mCampNotFound'));
        }
        $this->model->delete($id);

        return redirect()->to('mailer/campanas')->with('success', lang('Mailer.mCampDeleted'));
    }

    private function owned($id): ?array
    {
        $companyId = $this->companyId();
        $c         = $this->model->find($id);

        return ($c && (int) $c['company_id'] === $companyId) ? $c : null;
    }
}
