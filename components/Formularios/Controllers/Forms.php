<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Formularios\Libraries\DemoData;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Libraries\PlanGuard;
use Components\Formularios\Libraries\QrService;
use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\ResponseModel;
use Components\Formularios\Models\TemplateModel;

/**
 * Listado y ciclo de vida del formulario.
 *
 * Todo lo que muta pasa por el servicio, y todo lo que lee comprueba antes que
 * el formulario sea DE LA EMPRESA ACTIVA: tener `frm.edit` autoriza a editar
 * formularios, no a editar los de otra empresa (contrato: permiso y pertenencia
 * son dos cosas distintas).
 */
class Forms extends BaseController
{
    private FormModel $forms;
    private FormService $svc;
    private PlanGuard $guard;

    public function __construct()
    {
        $this->forms = new FormModel();
        $this->svc   = new FormService();
        $this->guard = new PlanGuard();
    }

    private function companyId(): int
    {
        return (int) (session('active_company_id') ?? 0);
    }

    /** El formulario pedido, solo si es de la empresa activa. */
    private function own(int $id): ?array
    {
        $companyId = $this->companyId();

        return $companyId ? $this->forms->ofCompany($id, $companyId) : null;
    }

    public function index()
    {
        $companyId = $this->companyId();

        if (!$companyId) {
            return view('Components\Formularios\Views\index', [
                'title' => lang('Frm.forms'),
                'dt'    => null,
            ]);
        }

        $status = (string) ($this->request->getGet('status') ?? '');

        $dt = (new DataTable($this->forms, $this->request))
            ->searchable(['title', 'slug'])
            ->sortable(['title', 'status', 'created_at', 'views'])
            ->defaultSort('created_at', 'desc')
            ->query(static function ($m) use ($companyId, $status) {
                $m->where('company_id', $companyId);
                if ($status !== '') {
                    $m->where('status', $status);
                }
            })
            ->paginate(15);

        // Conteo de respuestas por formulario en UNA consulta: pedirlo fila a
        // fila serían quince consultas por pantalla.
        $counts = [];
        $ids    = array_column($dt['rows'], 'id');
        if ($ids) {
            $rows = (new ResponseModel())->builder()
                ->select('form_id, COUNT(*) AS n')
                ->whereIn('form_id', $ids)
                ->where('deleted_at IS NULL')
                ->groupBy('form_id')
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $counts[(int) $r['form_id']] = (int) $r['n'];
            }
        }

        return view('Components\Formularios\Views\index', [
            'title'       => lang('Frm.forms'),
            'dt'          => $dt,
            'status'      => $status,
            'extraParams' => $status !== '' ? ['status' => $status] : [],
            'counts'      => $counts,
            'planEnabled' => $this->guard->serviceEnabled($companyId),
            'quota'       => $this->guard->formsLeft($companyId),
            'showDemo'    => (new DemoData())->isEmpty($companyId),
            'templates'   => (new TemplateModel())->available($companyId),
        ]);
    }

    /**
     * Alta. La PRESENTACION se elige aquí, en el diálogo del listado: ficha
     * centrada o pantalla completa. Se puede cambiar después en el editor, pero
     * pedirla al crear evita montar un formulario entero y descubrir al final
     * que no se ve como se quería.
     */
    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('formularios')->with('error', lang('Frm.noCompany'));
        }

        if (($reason = $this->guard->blockedReason($companyId)) !== null) {
            return redirect()->to('formularios')->with('error', $reason);
        }

        $display = (string) ($this->request->getPost('display') ?? 'card');
        $title   = trim((string) ($this->request->getPost('title') ?? ''));

        $id = $this->svc->create(
            $companyId,
            (int) (session('user_id') ?? 0) ?: null,
            $title !== '' ? $title : lang('Frm.untitled'),
            $display
        );

        return redirect()->to('formularios/editor/' . $id)->with('success', lang('Frm.created'));
    }

    public function duplicate($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }
        if (($reason = $this->guard->blockedReason((int) $form['company_id'])) !== null) {
            return redirect()->to('formularios')->with('error', $reason);
        }

        $newId = $this->svc->duplicate($form, (int) $form['company_id'], (int) (session('user_id') ?? 0) ?: null);

        return redirect()->to('formularios/editor/' . $newId)->with('success', lang('Frm.duplicated'));
    }

    /** Publicar: congela el esquema que verá quien responda. */
    public function publish($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        $schema = $this->svc->sanitizeTree($form['schema_json'] ?? []);
        if (($schema['fields'] ?? []) === []) {
            return redirect()->back()->with('error', lang('Frm.errNoFields'));
        }

        $this->svc->publish($form);

        return redirect()->back()->with('success', lang('Frm.published'));
    }

    /** Cerrar o reabrir. Cerrado deja de admitir respuestas pero no borra nada. */
    public function changeStatus($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        $to = (string) $this->request->getPost('status');
        if (!in_array($to, FormModel::STATUSES, true)) {
            return redirect()->back()->with('error', lang('Frm.errStatus'));
        }
        // Publicar desde aquí exigiría congelar el esquema: para eso está su
        // propia acción, que además comprueba que haya campos.
        if ($to === 'published') {
            return $this->publish($id);
        }

        $this->forms->update((int) $form['id'], ['status' => $to]);

        return redirect()->back()->with('success', lang('Frm.statusChanged'));
    }

    public function delete($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        $this->svc->deleteForm((int) $form['id']);

        return redirect()->to('formularios')->with('success', lang('Frm.deleted'));
    }

    /**
     * Pantalla de compartir: la URL pública, su QR y el fragmento para
     * incrustarlo en una página.
     *
     * El fragmento es un `iframe` a la URL real y no una copia del HTML del
     * formulario: así lo incrustado sigue siendo el formulario vivo, con su
     * validación y su cupo, en vez de una fotografía que deja de parecerse al
     * original en cuanto se edita.
     */
    public function share($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        $url = base_url('formulario/' . $form['public_token']);

        return view('Components\Formularios\Views\share', [
            'title' => lang('Frm.share'),
            'form'  => $form,
            'url'   => $url,
            'qr'    => (new QrService())->dataUri($url, 6),
            'embed' => '<iframe src="' . $url . '" style="width:100%;min-height:720px;border:0" '
                . 'title="' . esc($form['title'], 'attr') . '" loading="lazy"></iframe>',
        ]);
    }

    /** Datos de ejemplo. Solo si la empresa no tiene ningún formulario. */
    public function seedDemo()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('formularios')->with('error', lang('Frm.noCompany'));
        }

        $result = (new DemoData())->seed($companyId, (int) (session('user_id') ?? 0) ?: null);

        return redirect()->to('formularios')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
