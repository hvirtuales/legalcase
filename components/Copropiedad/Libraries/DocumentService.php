<?php

namespace Components\Copropiedad\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\Copropiedad\Models\DocumentModel;

/**
 * Documentos del registro: escrituras, contratos de arriendo, cédulas, SOAT,
 * carnés de vacunas, actas.
 *
 * **Los archivos viven fuera de `public/`** (`writable/uploads/copropiedad/<empresa>/`)
 * y se sirven por controlador. Una escritura o una cédula en una URL adivinable es
 * una fuga de datos personales, y el contrato §11 lo exige para todo adjunto.
 *
 * La comprobación que de verdad importa está en `ownerCompanyId()`: **el permiso no
 * basta**. Tener `cop.view` autoriza a ver los documentos de *tu* conjunto, no los del
 * de al lado, así que antes de servir un archivo se resuelve a qué empresa pertenece
 * su dueño y se compara con la activa. Se comprueba en la **subida** y en la
 * **descarga**, porque el id viaja en los dos sentidos.
 */
class DocumentService
{
    /** 8 MB: una escritura escaneada cabe; un vídeo no tiene por qué estar aquí. */
    public const MAX_BYTES = 8 * 1024 * 1024;

    /** Lista blanca de tipos. Nada ejecutable, nada de office con macros. */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    ];

    private DocumentModel $docs;

    public function __construct()
    {
        $this->docs = new DocumentModel();
    }

    /** Carpeta de la empresa, creada si hace falta. */
    private function folder(int $companyId): string
    {
        $dir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'copropiedad' . DIRECTORY_SEPARATOR . $companyId;

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * ¿De qué empresa es el dueño de un documento?
     *
     * `owner_type` llega del formulario, así que se resuelve **solo** contra la lista
     * blanca del modelo: un valor inventado no llega a tocar ninguna consulta.
     */
    public function ownerCompanyId(string $type, int $id): ?int
    {
        $table = DocumentModel::OWNER_TABLES[$type] ?? null;
        if ($table === null || $id <= 0) {
            return null;
        }

        $row = \Config\Database::connect()
            ->table($table)->select('company_id')->where('id', $id)
            ->get()->getRowArray();

        return $row ? (int) $row['company_id'] : null;
    }

    /**
     * Sube un documento y lo registra.
     *
     * @return array{ok:bool,message:string,id:int}
     */
    public function store(?UploadedFile $file, array $meta, int $companyId, ?int $userId = null): array
    {
        $type    = (string) ($meta['owner_type'] ?? '');
        $ownerId = (int) ($meta['owner_id'] ?? 0);

        if ($this->ownerCompanyId($type, $ownerId) !== $companyId) {
            return $this->fail(lang('Cop.errDocOwner'));
        }
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $this->fail(lang('Cop.errDocFile'));
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return $this->fail(lang('Cop.errDocSize', [round(self::MAX_BYTES / 1024 / 1024)]));
        }

        // `getMimeType()` lee el contenido, no la extensión: renombrar un .exe a .pdf
        // no lo cuela.
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            return $this->fail(lang('Cop.errDocType'));
        }

        $original = $file->getClientName();
        $name     = $file->getRandomName();

        try {
            $file->move($this->folder($companyId), $name);
        } catch (\Throwable $e) {
            log_message('error', 'Copropiedad: no se pudo guardar el documento: ' . $e->getMessage());

            return $this->fail(lang('Cop.errDocSave'));
        }

        $data = [
            'company_id'    => $companyId,
            'owner_type'    => $type,
            'owner_id'      => $ownerId,
            'kind'          => in_array($meta['kind'] ?? '', DocumentModel::KINDS, true) ? $meta['kind'] : 'otro',
            'title'         => trim((string) ($meta['title'] ?? '')) ?: $original,
            'file_path'     => $name,
            'original_name' => $original,
            'mime'          => $mime,
            'size'          => (int) $file->getSize(),
            'issued_at'     => $meta['issued_at'] ?: null,
            'expires_at'    => $meta['expires_at'] ?: null,
            'uploaded_by'   => $userId ?: null,
            'notes'         => $meta['notes'] ?? null,
        ];

        if (! $this->docs->insert($data)) {
            @unlink($this->folder($companyId) . DIRECTORY_SEPARATOR . $name);

            return $this->fail(implode(' ', $this->docs->errors()));
        }

        return ['ok' => true, 'message' => lang('Cop.okDocUploaded'), 'id' => (int) $this->docs->getInsertID()];
    }

    /** El documento, **solo si es de esta empresa**, con la ruta ya resuelta. */
    public function readable(int $id, int $companyId): ?array
    {
        $doc = $this->docs->owned($id, $companyId);
        if (! $doc) {
            return null;
        }

        // Cinturón y tirantes: además de `company_id` en la propia fila, se comprueba
        // que su DUEÑO siga siendo de esta empresa.
        if ($this->ownerCompanyId($doc['owner_type'], (int) $doc['owner_id']) !== $companyId) {
            return null;
        }

        $doc['absolute_path'] = $this->folder($companyId) . DIRECTORY_SEPARATOR . $doc['file_path'];

        return is_file($doc['absolute_path']) ? $doc : null;
    }

    /** Borra el registro y su archivo. */
    public function remove(int $id, int $companyId): array
    {
        $doc = $this->docs->owned($id, $companyId);
        if (! $doc) {
            return $this->fail(lang('Cop.errDocNotFound'));
        }

        $path = $this->folder($companyId) . DIRECTORY_SEPARATOR . $doc['file_path'];
        if (is_file($path)) {
            @unlink($path);
        }
        $this->docs->delete($id);

        return ['ok' => true, 'message' => lang('Cop.okDeleted'), 'id' => $id];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0];
    }
}
