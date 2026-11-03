<?php

namespace Components\Pqrsf\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\Pqrsf\Models\AttachmentModel;
use Components\Pqrsf\Models\RequestModel;

/**
 * Evidencias de la solicitud.
 *
 * Los archivos van a `writable/uploads/pqrsf`, **fuera de `public/`**: una foto
 * de una queja no puede quedar accesible por URL a quien adivine el nombre. La
 * descarga pasa siempre por el controlador, que comprueba permiso y empresa.
 */
class AttachmentService
{
    /** 8 MB: una foto de celular pasa de sobra. */
    public const MAX_BYTES = 8388608;

    public const ALLOWED = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf', 'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private AttachmentModel $model;

    public function __construct()
    {
        $this->model = new AttachmentModel();
    }

    private function dir(): string
    {
        $dir = WRITEPATH . 'uploads/pqrsf';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * Guarda un archivo subido.
     *
     * @return array{ok:bool,message:string,id:?int}
     */
    public function store(UploadedFile $file, string $ownerType, int $ownerId, ?int $userId = null, bool $public = false): array
    {
        if (!$file->isValid()) {
            return ['ok' => false, 'message' => lang('Pqr.attErrFile'), 'id' => null];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return ['ok' => false, 'message' => lang('Pqr.attErrSize'), 'id' => null];
        }
        if (!in_array($file->getMimeType(), self::ALLOWED, true)) {
            return ['ok' => false, 'message' => lang('Pqr.attErrType'), 'id' => null];
        }

        $name = $file->getRandomName();
        $file->move($this->dir(), $name);

        $this->model->insert([
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
            'name'       => mb_substr($file->getClientName(), 0, 190),
            'path'       => $name,
            'mime'       => (string) $file->getClientMimeType(),
            'size'       => (int) $file->getSize(),
            // Público = el ciudadano lo ve y lo descarga desde su ticket. Por
            // defecto NO: una evidencia interna no se publica por accidente.
            'is_public'  => $public ? 1 : 0,
            'user_id'    => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => lang('Pqr.attOk'), 'id' => (int) $this->model->getInsertID()];
    }

    public function listFor(string $ownerType, int $ownerId): array
    {
        return $this->model->listFor($ownerType, $ownerId);
    }

    /** Los que la empresa compartió con el ciudadano. */
    public function publicFor(string $ownerType, int $ownerId): array
    {
        return $this->model->publicFor($ownerType, $ownerId);
    }

    /** Publica o vuelve a ocultar un adjunto. */
    public function setPublic(int $id, bool $public): void
    {
        $this->model->update($id, ['is_public' => $public ? 1 : 0]);
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function absolutePath(array $att): string
    {
        return $this->dir() . DIRECTORY_SEPARATOR . $att['path'];
    }

    /** Borra el registro y el archivo. */
    public function delete(int $id): void
    {
        $att = $this->model->find($id);
        if (!$att) {
            return;
        }
        @unlink($this->absolutePath($att));
        $this->model->delete($id);
    }

    /**
     * ¿De qué empresa es el dueño del adjunto? Es lo que permite negar una
     * descarga de otra empresa aunque el usuario tenga el permiso.
     */
    public function ownerCompanyId(string $ownerType, int $ownerId): ?int
    {
        if ($ownerType !== 'request') {
            return null;
        }
        $row = (new RequestModel())->find($ownerId);

        return $row ? (int) $row['company_id'] : null;
    }
}
