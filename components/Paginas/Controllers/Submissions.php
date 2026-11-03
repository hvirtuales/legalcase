<?php

namespace Components\Paginas\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Paginas\Models\PageModel;
use Components\Paginas\Models\SubmissionModel;

/**
 * Bandeja de los mensajes que llegan por el bloque «formulario de contacto».
 *
 * Todo va acotado a la empresa activa: el `company_id` se copia de la página al
 * recibir el mensaje, así que aquí basta filtrar por él —y comprobarlo otra vez
 * en el detalle, porque el id viaja en la URL.
 */
class Submissions extends BaseController
{
    private SubmissionModel $model;

    public function __construct()
    {
        $this->model = new SubmissionModel();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    private function own(int $id): ?array
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return null;
        }
        $row = $this->model->find($id);

        return $row && (int) $row['company_id'] === $companyId ? $row : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Paginas\Views\submissions', ['title' => lang('Pag.messages'), 'dt' => null]);
        }

        $unreadOnly = $this->request->getGet('estado') === 'nuevos';

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['form_title', 'data'])
            ->sortable(['created_at', 'form_title', 'is_read'])
            ->defaultSort('created_at', 'desc')
            ->query(static function ($m) use ($companyId, $unreadOnly) {
                $m->where('company_id', $companyId);
                if ($unreadOnly) {
                    $m->where('is_read', 0);
                }
            })
            ->paginate(15);

        // Título de la página de origen sin una consulta por fila: una sola
        // lectura del maestro y un mapa en memoria.
        $pageIds = array_values(array_unique(array_map(
            static fn ($r) => (int) $r['page_id'],
            $dt['rows'] ?? []
        )));
        $pages = $pageIds
            ? array_column((new PageModel())->select('id, title, slug')->whereIn('id', $pageIds)->findAll(), null, 'id')
            : [];

        return view('Components\Paginas\Views\submissions', [
            'title'       => lang('Pag.messages'),
            'dt'          => $dt,
            'pages'       => $pages,
            'unreadOnly'  => $unreadOnly,
            'extraParams' => array_filter(['estado' => $unreadOnly ? 'nuevos' : null]),
            'unread'      => $this->model->unreadCount($companyId),
        ]);
    }

    public function show($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to('paginas/mensajes')->with('error', lang('Pag.errMessageNotFound'));
        }

        // Abrirlo lo marca leído: es lo que espera quien atiende la bandeja.
        if ((int) $row['is_read'] === 0) {
            $this->model->update((int) $row['id'], ['is_read' => 1]);
            $row['is_read'] = 1;
        }

        return view('Components\Paginas\Views\submission_show', [
            'title' => lang('Pag.message'),
            'row'   => $row,
            'page'  => (new PageModel())->find((int) $row['page_id']),
        ]);
    }

    /** POST mensajes/leido/{id} — volver a marcarlo como no leído (o al revés). */
    public function markRead($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to('paginas/mensajes')->with('error', lang('Pag.errMessageNotFound'));
        }
        $this->model->update((int) $row['id'], ['is_read' => (int) $row['is_read'] === 1 ? 0 : 1]);

        return redirect()->back()->with('success', lang('Pag.okMessageUpdated'));
    }

    public function delete($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to('paginas/mensajes')->with('error', lang('Pag.errMessageNotFound'));
        }
        $this->model->delete((int) $row['id']);

        return redirect()->to('paginas/mensajes')->with('success', lang('Pag.okMessageDeleted'));
    }
}
