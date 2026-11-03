<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\Access;
use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Models\ConversionModel;
use Components\LeadFlow\Models\ExtContactModel;
use Components\LeadFlow\Models\IntegrationLogModel;
use Components\LeadFlow\Models\MappingModel;
use Components\LeadFlow\Models\ProviderModel;

/**
 * Docketwise: conexión (OAuth), sincronización, conversiones y errores (§7).
 */
class Docketwise extends BaseLfl
{
    public const TABS = ['conexion', 'sincronizacion', 'conversiones', 'errores'];

    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('docketwise/index', ['integration' => null]);
        }
        $tab = (string) $this->request->getGet('tab');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'conexion';
        $svc = new IntegrationService();

        $data = [
            'tab'          => $tab,
            'diag'         => session()->getFlashdata('lfl_diag'),
            'integration'  => $svc->integration($cid),
            'provider'     => (new ProviderModel())->config(IntegrationService::PROVIDER),
            'isSuper'      => Access::isSuperAdmin((int) $this->userId()),
            'canConfigure' => $this->can('lfl.configure'),
            'canConvert'   => $this->can('lfl.convert'),
        ];

        if ($tab === 'conexion') {
            $data['lists'] = $svc->lists($cid);
        } elseif ($tab === 'sincronizacion') {
            $data['contactCount'] = (new ExtContactModel())->where('company_id', $cid)->countAllResults();
            $data['contacts']     = (new ExtContactModel())->where('company_id', $cid)->orderBy('last_name', 'asc')->findAll(50);
            $data['mapCounts']    = [];
            foreach ((new MappingModel())->select('entity_type, COUNT(*) AS n')->where('company_id', $cid)->groupBy('entity_type')->findAll() as $r) {
                $data['mapCounts'][$r['entity_type']] = (int) $r['n'];
            }
        } elseif ($tab === 'conversiones') {
            $data['conversions'] = (new ConversionModel())->select('cmp_lfl_conversions.*, l.first_name, l.last_name')
                ->join('cmp_lfl_leads l', 'l.id = cmp_lfl_conversions.lead_id')
                ->where('cmp_lfl_conversions.company_id', $cid)->orderBy('cmp_lfl_conversions.id', 'desc')->findAll(100);
        } else {
            $data['logs']   = (new IntegrationLogModel())->forCompany($cid)->where('status', 'error')->orderBy('id', 'desc')->findAll(100);
            $data['failed'] = (new ConversionModel())->select('cmp_lfl_conversions.*, l.first_name, l.last_name')
                ->join('cmp_lfl_leads l', 'l.id = cmp_lfl_conversions.lead_id')
                ->where('cmp_lfl_conversions.company_id', $cid)->where('cmp_lfl_conversions.status', 'failed')->findAll();
        }

        return $this->render('docketwise/index', $data);
    }

    public function connect()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound('leadflow/docketwise');
        }
        $res = (new IntegrationService())->startConnect($cid);
        if (! $res['ok']) {
            $this->flash('error', $res['message']);

            return redirect()->to(base_url('leadflow/docketwise'));
        }

        return redirect()->to($res['url']);
    }

    /**
     * La «pantalla de consentimiento» del Docketwise simulado. Solo existe en modo
     * simulado y solo para el `state` que acabamos de emitir.
     */
    public function simAuthorize()
    {
        $cid   = $this->companyId();
        $state = (string) $this->request->getGet('state');
        $row   = $cid ? (new IntegrationService())->integration($cid) : null;

        if (! $row || $row['mode'] !== 'simulated' || $row['status'] !== 'pending' || ! hash_equals((string) $row['oauth_state'], $state)) {
            return $this->notFound('leadflow/docketwise');
        }

        return $this->render('docketwise/sim_authorize', [
            'approve' => base_url('leadflow/docketwise/callback') . '?' . http_build_query(['code' => 'sim_' . bin2hex(random_bytes(8)), 'state' => $state]),
            'deny'    => base_url('leadflow/docketwise/callback') . '?' . http_build_query(['error' => 'access_denied', 'state' => $state]),
        ]);
    }

    public function callback()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound('leadflow/docketwise');
        }
        $svc = new IntegrationService();

        if ($this->request->getGet('error')) {
            $svc->disconnect($cid);
            $this->flash('error', lang('Lfl.errOauthDenied'));

            return redirect()->to(base_url('leadflow/docketwise'));
        }

        $this->result($svc->finishConnect($cid, (string) $this->request->getGet('code'), (string) $this->request->getGet('state'), $this->userId()));

        return redirect()->to(base_url('leadflow/docketwise'));
    }

    /**
     * Valida la conexión de ESTA firma contra Docketwise, de verdad: credenciales
     * de la app, autorización, token y tres llamadas de consulta a la API.
     *
     * Solo lee. Se deja el resultado en flash y lo pinta la pestaña «Conexión»,
     * porque el diagnóstico es de un momento concreto y no un estado guardado.
     */
    public function check()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound('leadflow/docketwise');
        }

        session()->setFlashdata('lfl_diag', (new IntegrationService())->diagnose($cid));

        return redirect()->to(base_url('leadflow/docketwise'));
    }

    /**
     * Lo mismo, pero solo de la app OAuth de la plataforma: es lo único que se
     * puede comprobar antes de que una firma autorice su cuenta. Como la pantalla
     * que la configura, solo el Super Admin.
     */
    public function checkPlatform()
    {
        if (! Access::isSuperAdmin((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow/docketwise'));
        }

        session()->setFlashdata('lfl_diag', (new IntegrationService())->diagnose($this->companyId(), true));

        return redirect()->to(base_url('leadflow/docketwise/plataforma'));
    }

    public function disconnect()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound('leadflow/docketwise');
        }
        $this->result((new IntegrationService())->disconnect($cid));

        return redirect()->to(base_url('leadflow/docketwise'));
    }

    public function syncContacts()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound('leadflow/docketwise');
        }
        $this->result((new IntegrationService())->syncContacts($cid));

        return $this->back('leadflow/docketwise?tab=sincronizacion');
    }

    /**
     * La app OAuth de la PLATAFORMA. Solo el Super Admin: es global y afecta a
     * todas las firmas, así que tener `lfl.configure` (que tiene el admin de una
     * firma) no basta.
     */
    public function platform()
    {
        if (! Access::isSuperAdmin((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow/docketwise'));
        }
        $model = new ProviderModel();

        if ($this->isPost()) {
            $model->saveConfig(IntegrationService::PROVIDER, [
                'client_id'    => $this->str('client_id', 255) ?: null,
                'secret'       => (string) $this->request->getPost('client_secret'),
                'redirect_uri' => $this->str('redirect_uri', 255) ?: null,
                'scope'        => $this->str('scope', 100),
                'mode'         => (string) $this->request->getPost('mode'),
            ]);
            $this->flash('success', lang('Lfl.okSaved'));

            return redirect()->to(base_url('leadflow/docketwise/plataforma'));
        }

        return $this->render('docketwise/platform', [
            'provider'    => $model->config(IntegrationService::PROVIDER),
            'callbackUrl' => base_url('leadflow/docketwise/callback'),
            'diag'        => session()->getFlashdata('lfl_diag'),
        ]);
    }
}
