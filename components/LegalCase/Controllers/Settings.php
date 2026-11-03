<?php

namespace Components\LegalCase\Controllers;

use App\Models\UserModel;
use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\MatterService;
use Components\LegalCase\Libraries\Portal;
use Components\LegalCase\Libraries\Support;
use Components\LegalCase\Libraries\Team;
use Components\LegalCase\Libraries\Calendar;
use Components\LegalCase\Models\CalendarDayModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\SupportGrantModel;

/**
 * Configuración del despacho: políticas de aprobación y plazos, compromisos por defecto,
 * portal y aviso de privacidad, conservación, acceso de soporte y reasignación de trabajo.
 */
class Settings extends BaseLgc
{
    public function index()
    {
        $model = new SettingsModel();
        $row   = $model->forCompanyOrDefault($this->companyId());

        if ($this->isPost()) {
            $privacy = trim((string) $this->post('privacy_text'));
            $data    = [
                'matter_prefix'        => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->post('matter_prefix')) ?: 'LC'),
                'approval_policy'      => (string) $this->post('approval_policy'),
                'deadline_policy'      => (string) $this->post('deadline_policy'),
                'default_cadence_days' => (int) $this->post('default_cadence_days'),
                'response_sla_hours'   => (int) $this->post('response_sla_hours'),
                'portal_enabled'       => $this->post('portal_enabled') ? 1 : 0,
                'notify_email'         => $this->post('notify_email') ? 1 : 0,
                'portal_help'          => trim((string) $this->post('portal_help')),
                'privacy_text'         => $privacy,
                'retention_years'      => max(1, min(50, (int) $this->post('retention_years'))),
                'calendar_mode'        => (string) $this->post('calendar_mode') === 'common' ? 'common' : 'judicial',
            ];
            // Cambiar el aviso de privacidad crea una versión nueva: los clientes la vuelven a aceptar.
            if ($privacy !== trim((string) ($row['privacy_text'] ?? ''))) {
                $data['privacy_version'] = (string) ((int) ($row['privacy_version'] ?? 1) + 1);
            }
            if (!$model->update((int) $row['id'], $data)) {
                return redirect()->back()->withInput()->with('error', esc(implode(' ', $model->errors())));
            }
            Audit::log($this->companyId(), null, 'settings.saved', 'settings', (int) $row['id'], array_diff_key($data, ['portal_help' => 1, 'privacy_text' => 1]));

            return redirect()->to('legalcase/configuracion')->with('success', esc(lang('Lgc.okSaved')));
        }

        // Personas con trabajo asignado (también las que ya no pertenecen al despacho).
        $db      = db_connect();
        $holders = [];
        foreach ([['cmp_lgc_matters', 'lead_lawyer_id'], ['cmp_lgc_deadlines', 'assigned_to'], ['cmp_lgc_tasks', 'assigned_to'], ['cmp_lgc_threads', 'assigned_to']] as [$t, $c]) {
            foreach ($db->table($t)->select($c . ' AS uid')->distinct()->where('company_id', $this->companyId())->where($c . ' IS NOT NULL')->get()->getResultArray() as $r) {
                $holders[(int) $r['uid']] = true;
            }
        }
        $names = [];
        foreach (array_keys($holders) as $uid) {
            $u = (new UserModel())->withDeleted()->find($uid);
            $names[$uid] = ($u ? trim($u['first_name'] . ' ' . $u['last_name']) : '#' . $uid) . (Team::isMember($this->companyId(), $uid) ? '' : ' · ' . lang('Lgc.notMember'));
        }
        $grants = (new SupportGrantModel())->listFor($this->companyId());
        foreach ($grants as &$g) {
            $u = (new UserModel())->find((int) $g['user_id']);
            $g['username'] = $u['username'] ?? '#' . $g['user_id'];
        }
        unset($g);

        return view('Components\LegalCase\Views\settings', [
            'title' => lang('Lgc.settings'), 'row' => $row, 'portalUrl' => Portal::url(),
            'holders' => $names, 'users' => Team::users($this->companyId()), 'grants' => $grants,
            'days'    => (new CalendarDayModel())->where('company_id', $this->companyId())->where('day >=', date('Y-01-01'))->orderBy('day', 'ASC')->findAll(200),
            'national' => Calendar::colombia((int) date('Y')) + Calendar::colombia((int) date('Y') + 1),
        ]);
    }

    public function grantSupport()
    {
        return $this->done((new Support())->grant((string) $this->post('who'), (int) $this->post('hours'), (string) $this->post('reason'), $this->acc()), 'legalcase/configuracion#soporte');
    }

    public function revokeSupport($id = null)
    {
        return $this->done((new Support())->revoke((int) $id, $this->acc()), 'legalcase/configuracion#soporte');
    }

    public function reassign()
    {
        return $this->done((new MatterService())->reassign((int) $this->post('from_user'), (int) $this->post('to_user'), $this->acc()), 'legalcase/configuracion#reasignar');
    }

    /** POST — día no hábil propio (cierre del despacho o suspensión de términos decretada). */
    public function addDay()
    {
        $model = new CalendarDayModel();
        $day   = (string) $this->post('day');
        $data  = ['company_id' => $this->companyId(), 'day' => $day, 'kind' => (string) $this->post('kind', 'closure'),
            'name' => trim((string) $this->post('name')), 'created_by' => $this->acc()->userId ?: null, 'created_at' => date('Y-m-d H:i:s')];
        if ($model->where('company_id', $this->companyId())->where('day', $day)->first()) {
            return redirect()->to('legalcase/configuracion#calendario')->with('error', esc(lang('Lgc.errDayExists')));
        }
        if (!$model->insert($data)) {
            return redirect()->to('legalcase/configuracion#calendario')->with('error', esc(implode(' ', $model->errors())));
        }
        Audit::log($this->companyId(), null, 'calendar.day_added', 'calendar', (int) $model->getInsertID(), ['day' => $day, 'kind' => $data['kind']]);

        return redirect()->to('legalcase/configuracion#calendario')->with('success', esc(lang('Lgc.okSaved')));
    }

    public function removeDay($id = null)
    {
        $model = new CalendarDayModel();
        $row   = $model->where('company_id', $this->companyId())->find((int) $id);
        if ($row) {
            $model->delete((int) $id);
            Audit::log($this->companyId(), null, 'calendar.day_removed', 'calendar', (int) $id, ['day' => $row['day']]);
        }

        return redirect()->to('legalcase/configuracion#calendario')->with('success', esc(lang('Lgc.okDeleted')));
    }
}
