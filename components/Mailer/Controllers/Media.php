<?php

namespace Components\Mailer\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use Components\Mailer\Libraries\ImageOptimizer;

/**
 * Subida de imágenes para el editor de plantillas. Se guardan en
 * public/uploads/mailer/{empresa}/ (deben ser PÚBLICAS para que el cliente de
 * correo del destinatario pueda cargarlas). Devuelve JSON con la URL absoluta.
 */
class Media extends BaseController
{
    private const MAX_BYTES = 5_242_880; // 5 MB
    private const ALLOWED   = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** ¿El usuario puede editar contenido de correo (crear o editar)? */
    private function canEdit(): bool
    {
        $userId = session()->get('user_id');
        if (!$userId) {
            return false;
        }
        $um = new UserModel();

        return $um->hasPermission($userId, 'mailer.create') || $um->hasPermission($userId, 'mailer.edit');
    }

    public function upload()
    {
        if (!$this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => lang('Mailer.upDenied')]);
        }
        $companyId = $this->companyId();
        if (!$companyId) {
            return $this->response->setJSON(['ok' => false, 'error' => lang('Mailer.mNoCompany'), 'csrf' => csrf_hash()]);
        }

        // Se aceptan los DOS nombres a propósito. El constructor manda `image`,
        // pero hubo una versión que mandaba `file`, y un navegador con el guion
        // viejo en caché seguiría fallando con un mensaje que culpa al archivo.
        $file = $this->request->getFile('image') ?: $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            return $this->response->setJSON(['ok' => false, 'error' => lang('Mailer.upBadFile'), 'csrf' => csrf_hash()]);
        }
        $ext = strtolower($file->getClientExtension() ?: $file->getExtension());
        if (!in_array($ext, self::ALLOWED, true) || strpos((string) $file->getMimeType(), 'image/') !== 0) {
            return $this->response->setJSON(['ok' => false, 'error' => lang('Mailer.upNotImage'), 'csrf' => csrf_hash()]);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return $this->response->setJSON(['ok' => false, 'error' => lang('Mailer.upTooBig'), 'csrf' => csrf_hash()]);
        }

        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailer' . DIRECTORY_SEPARATOR . $companyId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $this->response->setJSON(['ok' => false, 'error' => lang('Mailer.upNoDir'), 'csrf' => csrf_hash()]);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        try {
            $file->move($dir, $name, true);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['ok' => false, 'error' => lang('Mailer.upFailed'), 'csrf' => csrf_hash()]);
        }

        // Acotada al ancho del correo y en un formato que pinte cualquier
        // cliente. El ancho lo propone el bloque; el servidor lo acota, nunca se
        // lo cree. Si la optimización falla, se queda el original.
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $ancho = (int) $this->request->getPost('width');
        $res   = (new ImageOptimizer())->optimize($full, $ancho > 0 ? $ancho : null);

        if ($res['ok'] && $res['ext'] !== $ext) {
            $nuevo = pathinfo($name, PATHINFO_FILENAME) . '.' . $res['ext'];
            if (@rename($full, $dir . DIRECTORY_SEPARATOR . $nuevo)) {
                $name = $nuevo;
            }
        }

        return $this->response->setJSON([
            'ok'     => true,
            'url'    => base_url('uploads/mailer/' . $companyId . '/' . $name),
            'width'  => $res['width'],
            'height' => $res['height'],
            'bytes'  => $res['bytes'],
            'csrf'   => csrf_hash(),
        ]);
    }
}
