<?php

namespace Components\Copropiedad\Controllers;

use App\Controllers\BaseController;
use Components\Copropiedad\Libraries\DocumentService;

/**
 * Documentos del registro: subir, descargar y borrar.
 *
 * **La descarga va por aquí y no por una URL de `public/`**: una escritura o una
 * cédula no pueden quedar en una dirección que se adivine probando números. El
 * archivo se sirve leyéndolo del disco, y solo después de comprobar el permiso
 * `cop.view` (en la ruta) **y** que el documento sea de la empresa activa (en el
 * servicio, que además vuelve a comprobar que su dueño lo siga siendo).
 */
class Documents extends BaseController
{
    private DocumentService $docs;

    public function __construct()
    {
        $this->docs = new DocumentService();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /**
     * A dónde volver tras subir o borrar: la ficha de donde salió.
     *
     * Se valida contra un patrón cerrado en vez de redirigir a lo que llegue: un
     * `return_to` libre es una redirección abierta —se manda a alguien a otro sitio
     * desde una URL de confianza—, y aquí no hace falta esa libertad.
     */
    private function back()
    {
        $to = (string) $this->request->getPost('return_to');

        if ($to !== '' && preg_match('~^copropiedad/[a-z]+/ficha/\d+$~', $to)) {
            return redirect()->to(base_url($to));
        }

        return redirect()->to(base_url('copropiedad/inmuebles'));
    }

    public function upload()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return $this->back()->with('error', lang('Cop.errNoCompany'));
        }

        $r = $this->docs->store(
            $this->request->getFile('document'),
            [
                'owner_type' => (string) $this->request->getPost('owner_type'),
                'owner_id'   => (int) $this->request->getPost('owner_id'),
                'kind'       => (string) $this->request->getPost('kind'),
                'title'      => (string) $this->request->getPost('title'),
                'issued_at'  => (string) $this->request->getPost('issued_at'),
                'expires_at' => (string) $this->request->getPost('expires_at'),
                'notes'      => (string) $this->request->getPost('notes'),
            ],
            $companyId,
            (int) (session()->get('user_id') ?? 0) ?: null
        );

        return $this->back()->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    public function download($id = null)
    {
        $companyId = $this->companyId();
        $doc       = $companyId ? $this->docs->readable((int) $id, $companyId) : null;

        if (! $doc) {
            // El mismo 404 para «no existe» y «es de otra empresa»: distinguirlos
            // convertiría esta ruta en una sonda de qué documentos hay.
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($doc['absolute_path'], null)
            ->setFileName($doc['original_name']);
    }

    public function delete($id = null)
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return $this->back()->with('error', lang('Cop.errNoCompany'));
        }

        $r = $this->docs->remove((int) $id, $companyId);

        return $this->back()->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
