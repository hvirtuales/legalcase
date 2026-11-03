<?php

namespace Components\Mailer\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Mailer\Models\ContactModel;
use Components\Mailer\Libraries\QrService;
use Components\Mailer\Libraries\SignupService;
use Components\Mailer\Models\ListModel;

/** Listas de contactos («bases de datos»). Aisladas por empresa activa. */
class Lists extends BaseController
{
    private ListModel $model;

    public function __construct()
    {
        $this->model = new ListModel();
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
            return view('Components\Mailer\Views\lists\index', ['title' => lang('Mailer.lists'), 'dt' => null]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'description'])
            ->sortable(['name', 'created_at'])
            ->defaultSort('name', 'asc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        // Conteo de contactos por lista (para la columna).
        $counts = [];
        foreach ($dt['rows'] as $row) {
            $counts[$row['id']] = (new ContactModel())->where('list_id', $row['id'])->countAllResults();
        }

        return view('Components\Mailer\Views\lists\index', [
            'title'  => 'Listas de contactos',
            'dt'     => $dt,
            'counts' => $counts,
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mNoCompany'));
        }
        if ($this->request->getMethod() === 'POST') {
            $data = [
                'company_id'  => $companyId,
                'name'        => trim((string) $this->request->getPost('name')),
                'description' => trim((string) $this->request->getPost('description')) ?: null,
            ];
            if (!$this->model->insert($data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }

            return redirect()->to('mailer/listas/' . $this->model->getInsertID())->with('success', lang('Mailer.mListCreated'));
        }

        return view('Components\Mailer\Views\lists\form', ['title' => lang('Mailer.newList'), 'list' => [], 'link' => '']);
    }

    public function edit($id)
    {
        $list = $this->owned($id);
        if (!$list) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }
        if ($this->request->getMethod() === 'POST') {
            $abierta = (bool) $this->request->getPost('public_enabled');
            $data = [
                'name'           => trim((string) $this->request->getPost('name')),
                'description'    => trim((string) $this->request->getPost('description')) ?: null,
                'public_enabled' => $abierta ? 1 : 0,
                'consent_text'   => trim((string) $this->request->getPost('consent_text')) ?: null,
            ];
            // El token se crea la primera vez que se abre el alta y NO se borra
            // al cerrarla: un enlace que ya está en un cartel o en una web no
            // puede cambiar porque alguien apague y encienda el interruptor.
            if ($abierta) {
                (new SignupService())->tokenFor((int) $id);
            }
            if (!$this->model->update($id, $data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }

            return redirect()->to('mailer/listas')->with('success', lang('Mailer.mListUpdated'));
        }

        return view('Components\Mailer\Views\lists\form', [
            'title' => lang('Mailer.editList'),
            'list'  => $list,
            'link'  => !empty($list['public_token'])
                ? base_url('mailer-publico/suscribir/' . $list['public_token'])
                : '',
        ]);
    }

    public function show($id)
    {
        $companyId = $this->companyId();
        $list      = $this->owned($id);
        if (!$list) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }

        $dt = (new DataTable(new ContactModel(), $this->request))
            ->searchable(['email', 'name', 'entity'])
            ->sortable(['email', 'name', 'entity', 'status', 'created_at'])
            ->defaultSort('created_at', 'desc')
            ->query(fn ($m) => $m->where('company_id', $companyId)->where('list_id', $id))
            ->paginate(20);

        // Métricas por estado.
        $stats = ['subscribed' => 0, 'pending' => 0, 'unsubscribed' => 0, 'bounced' => 0, 'complained' => 0];
        $rows  = (new ContactModel())->select('status, COUNT(*) as c')->where('list_id', $id)->groupBy('status')->findAll();
        foreach ($rows as $r) {
            $stats[$r['status']] = (int) $r['c'];
        }

        // El enlace de alta se enseña AQUÍ, que es donde se va a buscar para
        // pegarlo en una web o en un cartel; en el formulario de edición solo
        // se configura.
        $link = (!empty($list['public_enabled']) && !empty($list['public_token']))
            ? base_url('mailer-publico/suscribir/' . $list['public_token'])
            : '';

        return view('Components\Mailer\Views\lists\show', [
            'title' => lang('Mailer.listIs', [$list['name']]),
            'list'  => $list,
            'dt'    => $dt,
            'stats' => $stats,
            'link'  => $link,
            // El QR del mismo enlace, para un cartel o el mostrador. Se genera
            // aquí y no en la vista para que la vista no calcule nada.
            'qr'    => $link !== '' ? (new QrService())->dataUri($link, 5) : null,
        ]);
    }

    public function delete($id)
    {
        if (!$this->owned($id)) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }
        $this->model->delete($id);

        return redirect()->to('mailer/listas')->with('success', lang('Mailer.mListDeleted'));
    }

    /** Devuelve la lista si pertenece a la empresa activa, o null. */
    private function owned($id): ?array
    {
        $companyId = $this->companyId();
        $list      = $this->model->find($id);

        return ($list && (int) $list['company_id'] === $companyId) ? $list : null;
    }
}
