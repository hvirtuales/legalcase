<?php

namespace Components\Servicios\Controllers;

use App\Libraries\DataTable;
use Components\Servicios\Models\ClientModel;

/** Maestro de clientes. */
class Clients extends BaseCrud
{
    protected string $route   = 'servicios/clientes';
    protected string $viewDir = 'clients';

    public function __construct()
    {
        $this->model = new ClientModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Servicios\Views\clients\index', [
                'title' => lang('Quo.clients'), 'dt' => null,
                'tier' => null, 'city' => '', 'cities' => [],
            ]);
        }

        // Filtros. Vacío = sin filtrar; `?valor=0` es «con las condiciones base».
        $rawTier = $this->request->getGet('valor');
        $tier    = $rawTier === null || $rawTier === '' ? null : (int) $rawTier;
        $city    = trim((string) $this->request->getGet('ciudad'));

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'nit', 'city', 'contact_name', 'email'])
            ->sortable(['name', 'nit', 'city'])
            ->defaultSort('name', 'asc')
            ->query(static function ($m) use ($companyId, $tier, $city) {
                $m->where('company_id', $companyId);
                if ($tier === 0) {
                    $m->where('price_tier', null);
                } elseif ($tier !== null) {
                    $m->where('price_tier', $tier);
                }
                if ($city !== '') {
                    $m->where('city', $city);
                }

                return $m;
            })
            ->paginate(15);

        return view('Components\Servicios\Views\clients\index', [
            'title'      => lang('Quo.clients'),
            'dt'         => $dt,
            'tier'       => $tier,
            'city'       => $city,
            'cities'     => $this->model->citiesFor($companyId),
        ]);
    }

    protected function fields(int $companyId): array
    {
        $r = $this->request;

        return [
            'company_id'    => $companyId,
            // Qué valor del producto se le aplica (1..3); vacío = condiciones base.
            'price_tier'    => in_array((int) $r->getPost('price_tier'), [1, 2, 3], true) ? (int) $r->getPost('price_tier') : null,
            'name'          => trim((string) $r->getPost('name')),
            'nit'          => trim((string) $r->getPost('nit')),
            'city'         => trim((string) $r->getPost('city')),
            'address'      => trim((string) $r->getPost('address')),
            'contact_name' => trim((string) $r->getPost('contact_name')),
            'phone'        => trim((string) $r->getPost('phone')),
            'email'        => trim((string) $r->getPost('email')),
            'email2'       => trim((string) $r->getPost('email2')),
            'notes'        => trim((string) $r->getPost('notes')),
        ];
    }

    /**
     * POST servicios/clientes/rapido — alta desde el cotizador (JSON).
     * Devuelve el cliente creado para insertarlo en el selector sin recargar.
     */
    public function quickStore()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return $this->response->setJSON(['ok' => false, 'message' => lang('Quo.errNoCompany')]);
        }

        $data = $this->fields($companyId);
        if (!$this->model->insert($data)) {
            return $this->response->setJSON(['ok' => false, 'message' => implode(' ', $this->model->errors())]);
        }

        $id = (int) $this->model->getInsertID();

        return $this->response->setJSON([
            'ok'     => true,
            'client' => ['id' => $id, 'name' => $data['name'], 'nit' => $data['nit'], 'city' => $data['city']],
        ]);
    }
}
