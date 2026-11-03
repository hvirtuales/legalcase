<?php

namespace Components\Servicios\Controllers;

use App\Libraries\DataTable;
use Components\Servicios\Models\SellerModel;

/** Maestro de vendedores. */
class Sellers extends BaseCrud
{
    protected string $route   = 'servicios/vendedores';
    protected string $viewDir = 'sellers';

    public function __construct()
    {
        $this->model = new SellerModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Servicios\Views\sellers\index', ['title' => lang('Quo.sellers'), 'dt' => null]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'email', 'phone'])
            ->sortable(['name', 'email', 'status'])
            ->defaultSort('name', 'asc')
            ->query(static fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        return view('Components\Servicios\Views\sellers\index', ['title' => lang('Quo.sellers'), 'dt' => $dt]);
    }

    protected function fields(int $companyId): array
    {
        $r    = $this->request;
        $data = [
            'company_id' => $companyId,
            'name'       => trim((string) $r->getPost('name')),
            'position'   => trim((string) $r->getPost('position')),
            'email'      => trim((string) $r->getPost('email')),
            'phone'      => trim((string) $r->getPost('phone')),
            'status'     => $r->getPost('status') === 'inactivo' ? 'inactivo' : 'activo',
            'card_url'   => trim((string) $r->getPost('card_url')),
        ];

        // Foto y firma: solo se tocan si se sube una nueva (así editar otros
        // campos no borra las imágenes existentes).
        $uploader = new \App\Libraries\UploadService();
        if ($photo = $uploader->storeImage($r->getFile('photo'), 'servicios', 'vendedor')) {
            $data['photo'] = $photo;
        }
        if ($signature = $uploader->storeImage($r->getFile('signature'), 'servicios', 'firma')) {
            $data['signature'] = $signature;
        }

        return $data;
    }
}
