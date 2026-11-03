<?php

namespace Components\Directorio\Controllers;

use App\Controllers\BaseController;
use Components\Directorio\Libraries\BoardService;
use Components\Directorio\Models\BoardModel;

/**
 * **Vistas** del directorio: páginas públicas con un subconjunto de las
 * empresas del grupo, o bien por sector o bien elegidas a mano.
 *
 * Todo el aislamiento se apoya en `session('active_tenant_id')`, que el filtro
 * `tenant` ya validó (contexto + vínculo + vigencia). El id **nunca** sale del
 * POST ni de la URL, y cada consulta filtra por él: tener `dir.edit` no
 * autoriza a tocar la vista de otro grupo.
 */
class Boards extends BaseController
{
    private BoardModel $model;
    private BoardService $service;

    public function __construct()
    {
        $this->model   = new BoardModel();
        $this->service = new BoardService();
    }

    private function tenantId(): int
    {
        return (int) (session('active_tenant_id') ?? 0);
    }

    public function index()
    {
        $tenantId = $this->tenantId();
        $boards   = $this->model->forTenant($tenantId);

        return view('Components\Directorio\Views\boards\index', [
            'title'   => lang('Dir.boards'),
            'boards'  => $boards,
            'counts'  => $this->service->countsFor($boards),
            'tenant'  => (new \App\Models\CompanyModel())->find($tenantId),
            'sectors' => $this->service->sectorsInUse($tenantId),
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->store(null);
        }

        return $this->form(null);
    }

    public function edit($id = null)
    {
        $board = $this->model->findForTenant((int) $id, $this->tenantId());
        if (!$board) {
            return redirect()->to('directorio')->with('error', lang('Dir.errBoardNotFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store((int) $id);
        }

        return $this->form($board);
    }

    private function form(?array $board)
    {
        $tenantId = $this->tenantId();

        return view('Components\Directorio\Views\boards\form', [
            'title'     => $board ? lang('Dir.editBoard') : lang('Dir.newBoard'),
            'board'     => $board,
            'sectors'   => $this->service->sectorsInUse($tenantId),
            'companies' => $this->service->tenantCompanies($tenantId),
            'picked'    => $board ? $this->service->pickedIds((int) $board['id']) : [],
            'tenant'    => (new \App\Models\CompanyModel())->find($tenantId),
        ]);
    }

    private function store(?int $id)
    {
        $tenantId = $this->tenantId();
        $r        = $this->request;

        if (!$this->validate(['title' => 'required|min_length[2]|max_length[150]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $title = trim((string) $r->getPost('title'));
        $mode  = in_array($r->getPost('mode'), BoardModel::MODES, true) ? $r->getPost('mode') : 'sector';
        $layout = in_array($r->getPost('layout'), BoardModel::LAYOUTS, true) ? $r->getPost('layout') : 'both';

        $data = [
            'tenant_id'    => $tenantId,
            'title'        => $title,
            'slug'         => $this->model->uniqueSlug($title, $tenantId, $id),
            'intro'        => trim((string) $r->getPost('intro')) ?: null,
            'mode'         => $mode,
            'layout'       => $layout,
            // El sector solo tiene sentido en el modo «sector»: guardarlo en el
            // manual dejaría un dato que no gobierna nada y que confunde al
            // volver a abrir el formulario.
            'sector_id'    => $mode === 'sector' ? ((int) $r->getPost('sector_id') ?: null) : null,
            'center_lat'   => \App\Models\CompanyModel::coordinate($r->getPost('center_lat'), 90),
            'center_lng'   => \App\Models\CompanyModel::coordinate($r->getPost('center_lng'), 180),
            'zoom'         => $this->zoom($r->getPost('zoom')),
            'sort_order'   => (int) $r->getPost('sort_order'),
            'is_published' => $r->getPost('is_published') ? 1 : 0,
        ];

        $this->model->skipValidation(true);
        $ok = $id ? $this->model->update($id, $data) : $this->model->insert($data);
        $this->model->skipValidation(false);

        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        $boardId = $id ?: (int) $this->model->getInsertID();

        // La selección se guarda siempre, no solo en modo manual: quien cambia
        // a «por sector» y vuelve atrás espera encontrar lo que había marcado.
        $this->service->savePicked($boardId, $tenantId, (array) $r->getPost('companies'));

        return redirect()->to('directorio')
            ->with('success', $id ? lang('Dir.okBoardUpdated') : lang('Dir.okBoardCreated'));
    }

    /** Zoom de Leaflet: 1..19, o null para encuadrar sobre los marcadores. */
    private function zoom($value): ?int
    {
        $z = (int) $value;

        return ($z >= 1 && $z <= 19) ? $z : null;
    }

    public function delete($id = null)
    {
        $board = $this->model->findForTenant((int) $id, $this->tenantId());
        if (!$board) {
            return redirect()->to('directorio')->with('error', lang('Dir.errBoardNotFound'));
        }

        /*
         * La pivote se limpia **a mano**: el modelo usa soft-delete, así que la
         * fila no desaparece y el `ON DELETE CASCADE` de la FK nunca dispara.
         * Sin esto, cada vista borrada dejaba sus filas de selección para
         * siempre. Es el mismo caso que el sector: un soft-delete no propaga.
         *
         * Borrar una vista NO toca ninguna empresa: la vista es una forma de
         * mirarlas, no su dueña.
         */
        db_connect()->table('cmp_dir_board_companies')->where('board_id', (int) $id)->delete();
        $this->model->delete((int) $id);

        return redirect()->to('directorio')->with('success', lang('Dir.okBoardDeleted'));
    }
}
