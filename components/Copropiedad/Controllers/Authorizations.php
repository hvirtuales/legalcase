<?php

namespace Components\Copropiedad\Controllers;

use App\Libraries\DataTable;
use Components\Copropiedad\Models\AuthorizationModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\UnitModel;

/**
 * Autorizaciones especiales.
 *
 * Al entrar se **cierran por calendario** las que ya pasaron de fecha
 * (`expireOverdue()`). Se hace aquí y no con una tarea programada porque un
 * componente drop-in no puede dar por hecho que la instalación tenga cron: si el
 * vencimiento dependiera de una tarea que nadie configuró, la portería vería como
 * vigente algo que caducó en marzo.
 */
class Authorizations extends BaseCrud
{
    protected string $route   = 'copropiedad/autorizaciones';
    protected string $viewDir = 'authorizations';
    protected string $langKey = 'auths';

    public function __construct()
    {
        $this->model = new AuthorizationModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\authorizations\index', ['dt' => null]);
        }

        $this->model->expireOverdue($companyId);

        $status = (string) ($this->request->getGet('estado') ?? '');
        $kind   = (string) ($this->request->getGet('tipo') ?? '');

        $dt = (new DataTable(new AuthorizationModel(), $this->request))
            ->searchable([
                'cmp_cop_authorizations.authorized_name',
                'cmp_cop_authorizations.authorized_doc',
                'cmp_cop_authorizations.details',
            ])
            ->sortable([
                'cmp_cop_authorizations.kind', 'cmp_cop_authorizations.status',
                'cmp_cop_authorizations.end_date', 'cmp_cop_units.code',
            ])
            ->defaultSort('cmp_cop_authorizations.end_date', 'desc')
            ->query(function ($b) use ($companyId, $status, $kind) {
                $b->select('cmp_cop_authorizations.*, cmp_cop_units.code AS unit_code,'
                        . ' cmp_cop_blocks.name AS block_name,'
                        . ' cmp_cop_people.first_name, cmp_cop_people.last_name,'
                        . ' quien.first_name AS grantor_first, quien.last_name AS grantor_last')
                    ->join('cmp_cop_units', 'cmp_cop_units.id = cmp_cop_authorizations.unit_id', 'left')
                    ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
                    ->join('cmp_cop_people', 'cmp_cop_people.id = cmp_cop_authorizations.person_id', 'left')
                    ->join('cmp_cop_people quien', 'quien.id = cmp_cop_authorizations.granted_by_id', 'left')
                    ->where('cmp_cop_authorizations.company_id', $companyId);

                if (in_array($status, AuthorizationModel::STATUSES, true)) {
                    $b->where('cmp_cop_authorizations.status', $status);
                }
                if (in_array($kind, AuthorizationModel::KINDS, true)) {
                    $b->where('cmp_cop_authorizations.kind', $kind);
                }
            })
            ->paginate(20);

        return view('Components\Copropiedad\Views\authorizations\index', [
            'dt'     => $dt,
            'status' => $status,
            'kind'   => $kind,
            'counts' => $this->model->statusCounts($companyId),
        ]);
    }

    protected function fields(int $companyId): array
    {
        return [
            'company_id'       => $companyId,
            'unit_id'          => (int) $this->request->getPost('unit_id'),
            'kind'             => $this->pick('kind', AuthorizationModel::KINDS, 'ingreso'),
            'person_id'        => $this->int('person_id'),
            'authorized_name'  => $this->str('authorized_name', 100),
            'authorized_doc'   => $this->str('authorized_doc', 30),
            'authorized_phone' => $this->str('authorized_phone', 30),
            'granted_by_id'    => $this->int('granted_by_id'),
            'start_date'       => $this->date('start_date'),
            'end_date'         => $this->date('end_date'),
            'status'           => $this->pick('status', AuthorizationModel::STATUSES, 'vigente'),
            'details'          => $this->str('details'),
        ];
    }

    protected function extraCheck(array $data, ?array $existing): ?string
    {
        $companyId = (int) $data['company_id'];

        if (! (new UnitModel())->owned((int) $data['unit_id'], $companyId)) {
            return lang('Cop.errUnitNotFound');
        }
        if (! AuthorizationModel::hasSubject($data)) {
            return lang('Cop.errAuthSubject');
        }

        $people = new PersonModel();

        foreach (['person_id', 'granted_by_id'] as $campo) {
            if ($data[$campo] && ! $people->owned($data[$campo], $companyId)) {
                return lang('Cop.errPersonNotFound');
            }
        }

        // Una vigencia al revés deja la autorización vencida el día que nace.
        if ($data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date']) {
            return lang('Cop.errAuthDates');
        }

        return null;
    }

    protected function formData(int $companyId, ?array $row = null): array
    {
        return [
            'units'  => (new UnitModel())->optionsFor($companyId),
            'people' => (new PersonModel())->optionsFor($companyId),
        ];
    }

    /**
     * Revocar es una **decisión**, distinta de vencer.
     *
     * Por eso escribe `revocada` y no `vencida`, y por eso `expireOverdue()` nunca
     * la toca después: ante una reclamación no es lo mismo «se le quitó el permiso»
     * que «se le acabó el plazo».
     */
    public function revoke($id = null)
    {
        $row = $this->own((int) $id);
        if (! $row) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNotFound'));
        }

        $this->model->update((int) $row['id'], [
            'status'   => 'revocada',
            'end_date' => $row['end_date'] ?: date('Y-m-d'),
        ]);

        return redirect()->to($this->route)->with('success', lang('Cop.okAuthRevoked'));
    }
}
