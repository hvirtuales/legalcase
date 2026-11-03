<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\ScoringService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\DocumentTypeModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MemberModel;
use Components\LeadFlow\Models\ScoringRuleModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SettingsModel;
use Components\LeadFlow\Models\StageModel;

/**
 * Configuración de la firma: general, equipo comercial, etapas, scoring y tipos
 * de documento. (Los servicios tienen su propio controlador.)
 */
class Settings extends BaseLfl
{
    /** Etapas que no se pueden desactivar: las reglas del pipeline cuentan con ellas. */
    private const LOCKED = ['new', 'won', 'converted'];

    private function guard()
    {
        if (! $this->companyId()) {
            $this->flash('error', lang('Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow'));
        }

        return null;
    }

    /**
     * Manual de la firma: cómo funciona LeadFlow, en el orden en que se usa.
     *
     * Es de LECTURA y va con `lfl.view`, no con `lfl.configure`: quien más lo
     * necesita es el asesor que acaba de entrar, que no configura nada. Junto al
     * manual va el estado real de la puesta en marcha de esta firma, porque «qué
     * me falta» se contesta mirando datos, no leyendo una lista genérica.
     */
    public function help()
    {
        $cid   = $this->companyId();
        $setup = ['team' => 0, 'services' => 0, 'stages' => 0, 'scoring' => 0, 'forms' => 0, 'docketwise' => false];

        if ($cid) {
            $setup['team']       = (new MemberModel())->forCompany($cid)->where('is_active', 1)->countAllResults();
            $setup['services']   = (new ServiceModel())->forCompany($cid)->countAllResults();
            $setup['stages']     = (new StageModel())->forCompany($cid)->countAllResults();
            $setup['scoring']    = (new ScoringRuleModel())->forCompany($cid)->where('is_active', 1)->countAllResults();
            $setup['forms']      = (new FormModel())->forCompany($cid)->where('status', 'published')->countAllResults();
            $setup['docketwise'] = (new IntegrationService())->isConnected($cid);
        }

        // Los permisos propios del componente, resueltos una vez para las 33 fichas
        // del catálogo. Sin empresa activa no hay nada que abrir, así que ninguno.
        $myPerms = [];
        foreach (['lfl.view', 'lfl.create', 'lfl.edit', 'lfl.delete', 'lfl.configure', 'lfl.convert', 'lfl.import'] as $p) {
            if ($this->can($p)) {
                $myPerms[] = $p;
            }
        }

        return $this->render('settings/help', [
            'setup'        => $setup,
            'canConfigure' => $this->can('lfl.configure'),
            'planStatus'   => $cid ? \Components\LeadFlow\Libraries\HelpCatalog::planStatus($cid) : [],
            'myPerms'      => $myPerms,
        ]);
    }

    /**
     * Conexión de ESTA firma con USCIS.
     *
     * Las credenciales son por firma porque el cupo también lo es: USCIS da 1.000
     * consultas al día por credencial, y con una sola de plataforma la firma con más
     * tráfico se lo agotaría a todas las demás.
     */
    public function uscis()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound();
        }

        $svc = new \Components\LeadFlow\Libraries\Uscis\UscisService();

        if ($this->isPost()) {
            $res = $svc->save(
                $cid,
                (string) $this->request->getPost('uscis_client_id'),
                (string) $this->request->getPost('uscis_client_secret'),
                (string) $this->request->getPost('uscis_mode')
            );
            $this->flash($res['ok'] ? 'success' : 'error', $res['message']);

            return redirect()->to(base_url('leadflow/configuracion/uscis'));
        }

        return $this->render('settings/uscis', [
            'title'        => lang('Lfl.setUscis'),
            'cfg'          => $svc->config($cid),
            'canConfigure' => true,
            // Los tres del sandbox, para que probar no exija buscarlos en otro sitio.
            'samples'      => array_keys(\Components\LeadFlow\Libraries\Uscis\UscisSimulatedAdapter::CASES),
        ]);
    }

    /**
     * Prueba la conexión de verdad: pide token y consulta un caso.
     *
     * El resultado va en flash y lo pinta la propia pestaña, igual que el diagnóstico
     * de Docketwise: es la foto de un momento, no un estado que haya que guardar.
     */
    public function uscisCheck()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound();
        }

        session()->setFlashdata('lfl_diag', (new \Components\LeadFlow\Libraries\Uscis\UscisService())->diagnose($cid));

        return redirect()->to(base_url('leadflow/configuracion/uscis'));
    }

    /** Borra las credenciales y devuelve la firma al modo simulado. */
    public function uscisDisconnect()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound();
        }

        $res = (new \Components\LeadFlow\Libraries\Uscis\UscisService())->disconnect($cid);
        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);

        return redirect()->to(base_url('leadflow/configuracion/uscis'));
    }

    public function general()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid   = $this->companyId();
        $model = new SettingsModel();

        if ($this->isPost()) {
            $currency = (string) $this->request->getPost('currency');
            $model->saveFor($cid, [
                'currency'         => in_array($currency, SettingsModel::CURRENCIES, true) ? $currency : 'USD',
                'no_response_days' => max(1, min(90, (int) $this->request->getPost('no_response_days'))),
                'consent_text'     => $this->str('consent_text', 3000) ?: null,
                'public_intro'     => $this->str('public_intro', 2000) ?: null,
            ]);
            $this->flash('success', lang('Lfl.okSaved'));

            return redirect()->to(base_url('leadflow/configuracion'));
        }

        return $this->render('settings/general', ['settings' => $model->forCompany($cid)]);
    }

    /**
     * La vieja pantalla de equipo, ahora una redirección permanente.
     *
     * Se fundió con el directorio en `/leadflow/directorio`: `cmp_lfl_members` y
     * `cmp_lfl_profiles` se indexan por `(company_id, user_id)` y eran dos mitades del
     * mismo registro en dos pantallas que no se hablaban.
     *
     * La ruta se conserva en vez de borrarse porque hay enlaces guardados y el manual
     * la citaba; un 404 aquí se lee como que la funcionalidad desapareció, cuando lo
     * que pasó es que se movió.
     */
    public function team()
    {
        return redirect()->to(base_url('leadflow/directorio'), 301);
    }

    public function stages()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid   = $this->companyId();
        $model = new StageModel();

        if ($this->isPost()) {
            $names = (array) ($this->request->getPost('name') ?? []);
            $probs = (array) ($this->request->getPost('probability') ?? []);
            $cols  = (array) ($this->request->getPost('color') ?? []);
            $sorts = (array) ($this->request->getPost('sort') ?? []);
            $act   = (array) ($this->request->getPost('active') ?? []);

            foreach ($model->ordered($cid) as $s) {
                $id   = (int) $s['id'];
                $name = mb_substr(trim(strip_tags((string) ($names[$id] ?? $s['name']))), 0, 80);
                $data = [
                    'name'        => $name !== '' ? $name : $s['name'],
                    'probability' => max(0, min(100, (int) ($probs[$id] ?? $s['probability']))),
                    'color'       => in_array($cols[$id] ?? '', StageModel::COLORS, true) ? $cols[$id] : $s['color'],
                    'sort'        => (int) ($sorts[$id] ?? $s['sort']),
                    'is_active'   => in_array($s['code'], self::LOCKED, true) ? 1 : (isset($act[$id]) ? 1 : 0),
                ];
                $model->update($id, $data);
            }
            $this->flash('success', lang('Lfl.okSaved'));

            return redirect()->to(base_url('leadflow/configuracion/etapas'));
        }

        $counts = [];
        foreach ((new LeadModel())->select('stage_id, COUNT(*) AS n')->where('company_id', $cid)->groupBy('stage_id')->findAll() as $r) {
            $counts[(int) $r['stage_id']] = (int) $r['n'];
        }

        return $this->render('settings/stages', ['stages' => $model->ordered($cid), 'counts' => $counts, 'locked' => self::LOCKED]);
    }

    public function addStage()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid   = $this->companyId();
        $model = new StageModel();
        $kind  = (string) $this->request->getPost('kind') === 'lost' ? 'lost' : 'open';

        // Una etapa abierta nueva entra justo antes de «Ganado»; una alternativa, al final.
        $won  = $model->byCode($cid, 'won');
        // Por el builder y no con first(): first() añade un ORDER BY id que, junto a
        // un agregado, rechaza MySQL con ONLY_FULL_GROUP_BY.
        $max  = (int) ($model->builder()->selectMax('sort')->where('company_id', $cid)->get()->getRowArray()['sort'] ?? 0);
        $sort = $kind === 'open' && $won ? (int) $won['sort'] - 1 : $max + 10;

        $ok = $model->insert([
            'company_id'  => $cid,
            'name'        => $this->str('name', 80),
            'kind'        => $kind,
            'sort'        => $sort,
            'probability' => max(0, min(100, (int) $this->request->getPost('probability'))),
            'color'       => in_array($this->request->getPost('color'), StageModel::COLORS, true) ? $this->request->getPost('color') : 'secondary',
            'is_active'   => 1,
        ]);
        $this->flash($ok ? 'success' : 'error', $ok ? lang('Lfl.okCreated') : implode(' ', $model->errors()));

        return redirect()->to(base_url('leadflow/configuracion/etapas'));
    }

    public function deleteStage($id = null)
    {
        $cid   = $this->companyId();
        $model = new StageModel();
        $stage = $model->owned((int) $id, $cid);

        if (! $stage) {
            return $this->notFound('leadflow/configuracion/etapas');
        }
        if ($stage['code'] !== null) {
            $this->flash('error', lang('Lfl.errStageSystem'));
        } elseif ((new LeadModel())->where('company_id', $cid)->where('stage_id', (int) $stage['id'])->countAllResults() > 0) {
            $this->flash('error', lang('Lfl.errStageInUse'));
        } else {
            $model->delete((int) $stage['id']);
            $this->flash('success', lang('Lfl.okDeleted'));
        }

        return redirect()->to(base_url('leadflow/configuracion/etapas'));
    }

    public function scoring()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid   = $this->companyId();
        $rules = new ScoringRuleModel();
        $map   = $rules->mapFor($cid);

        if ($this->isPost()) {
            $points = (array) ($this->request->getPost('points') ?? []);
            $active = (array) ($this->request->getPost('active') ?? []);
            // Solo los criterios que existen en el código: lista blanca.
            foreach (array_keys(ScoringService::CRITERIA) as $c) {
                if (isset($map[$c])) {
                    $rules->update((int) $map[$c]['id'], [
                        'points'    => max(-100, min(100, (int) ($points[$c] ?? $map[$c]['points']))),
                        'is_active' => isset($active[$c]) ? 1 : 0,
                    ]);
                }
            }

            // Las reglas cambiaron: se recalculan los leads abiertos y ganados.
            $svc = new ScoringService();
            foreach ((new LeadModel())->select('id')->where('company_id', $cid)->whereIn('status', ['open', 'won'])->findAll(3000) as $l) {
                $svc->recalculate((int) $l['id'], $cid, 'rules_changed');
            }
            $this->flash('success', lang('Lfl.okScoringSaved'));

            return redirect()->to(base_url('leadflow/configuracion/scoring'));
        }

        return $this->render('settings/scoring', ['rules' => $map]);
    }

    public function docTypes()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid   = $this->companyId();
        $model = new DocumentTypeModel();

        if ($this->isPost()) {
            $name = $this->str('name', 80);
            if ($name !== '') {
                $model->insert(['company_id' => $cid, 'name' => $name, 'sort' => 100, 'is_active' => 1]);
                $this->flash('success', lang('Lfl.okCreated'));
            }

            return redirect()->to(base_url('leadflow/configuracion/documentos'));
        }

        return $this->render('settings/doctypes', ['types' => $model->forCompany($cid)->orderBy('sort', 'asc')->orderBy('name', 'asc')->findAll()]);
    }

    public function deleteDocType($id = null)
    {
        $model = new DocumentTypeModel();
        $row   = $model->owned((int) $id, $this->companyId());
        if (! $row) {
            return $this->notFound('leadflow/configuracion/documentos');
        }
        $model->delete((int) $row['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return redirect()->to(base_url('leadflow/configuracion/documentos'));
    }
}
