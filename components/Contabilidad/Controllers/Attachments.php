<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Components\Contabilidad\Libraries\AttachmentService;

class Attachments extends BaseController
{
    private AttachmentService $svc;

    public function __construct()
    {
        $this->svc = new AttachmentService();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** Valida que el dueño del adjunto pertenezca a la empresa activa. */
    private function ownsCompany(string $ownerType, int $ownerId): bool
    {
        $companyId = $this->companyId();

        return $companyId && AttachmentService::isOwnerType($ownerType)
            && $this->svc->ownerCompanyId($ownerType, $ownerId) === $companyId;
    }

    public function upload()
    {
        $ownerType = (string) $this->request->getPost('owner_type');
        $ownerId   = (int) $this->request->getPost('owner_id');
        $back      = (string) ($this->request->getPost('return') ?: 'contabilidad/comprobantes');

        if (!$this->ownsCompany($ownerType, $ownerId)) {
            return redirect()->to($back)->with('error', 'No autorizado para adjuntar aquí.');
        }

        $files = $this->request->getFileMultiple('files') ?? [];
        $ok    = 0;
        $err   = null;
        foreach ($files as $f) {
            if (!$f || $f->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $res = $this->svc->store($f, $ownerType, $ownerId, session()->get('user_id'));
            $res['ok'] ? $ok++ : $err = $res['message'];
        }

        if ($ok === 0) {
            return redirect()->to($back)->with('error', $err ?: 'No se subió ningún archivo.');
        }

        return redirect()->to($back)->with('success', $ok . ' adjunto(s) subido(s).');
    }

    public function download($id)
    {
        $att = $this->svc->find((int) $id);
        if (!$att || !$this->ownsCompany($att['owner_type'], (int) $att['owner_id'])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $path = $this->svc->absolutePath($att);
        if (!is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName($att['original_name']);
    }

    public function delete($id)
    {
        $att  = $this->svc->find((int) $id);
        $back = (string) ($this->request->getPost('return') ?: 'contabilidad/comprobantes');
        if (!$att || !$this->ownsCompany($att['owner_type'], (int) $att['owner_id'])) {
            return redirect()->to($back)->with('error', 'No autorizado.');
        }
        $this->svc->delete((int) $id);

        return redirect()->to($back)->with('success', 'Adjunto eliminado.');
    }
}
