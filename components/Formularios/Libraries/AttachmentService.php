<?php

namespace Components\Formularios\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\Formularios\Models\AttachmentModel;

/**
 * Archivos subidos al responder y firmas dibujadas.
 *
 * Van a `writable/uploads/formularios/<empresa>/`, FUERA de `public/`: un
 * documento de identidad o una firma no pueden quedar accesibles por una URL
 * adivinable. La descarga pasa siempre por el controlador, que comprueba
 * permiso Y empresa (las dos cosas: el permiso solo dice que puede ver
 * adjuntos, no que pueda ver los de otra empresa).
 *
 * Copia propia del patrón de PQRSF y Copropiedad: un componente no depende de
 * otro (contrato 0.4).
 */
class AttachmentService
{
    /** Tope absoluto, por encima de lo que pida el campo. */
    public const MAX_MB = 8;

    /** Lista blanca por tipo real (`getMimeType`), no por extensión declarada. */
    public const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf', 'text/plain', 'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private AttachmentModel $model;

    public function __construct()
    {
        $this->model = new AttachmentModel();
    }

    /** Carpeta por empresa: un descuido de ruta no mezcla dos clientes. */
    public function dir(int $companyId): string
    {
        $dir = WRITEPATH . 'uploads/formularios/' . $companyId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /** Las extensiones declaradas en el campo, normalizadas. */
    public static function extensionList(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', mb_strtolower(trim($raw))) ?: [] as $e) {
            $e = ltrim(trim($e), '.');
            if ($e !== '' && preg_match('/^[a-z0-9]{1,8}$/', $e) === 1) {
                $out[$e] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Comprueba un archivo contra el campo que lo pide.
     *
     * @return string|null mensaje de error, o null si pasa
     */
    public function check(UploadedFile $file, array $field): ?string
    {
        if (!$file->isValid()) {
            return lang('Frm.errFileUpload');
        }

        $mb    = (int) ($field['maxSizeMB'] ?? self::MAX_MB);
        $limit = min($mb > 0 ? $mb : self::MAX_MB, self::MAX_MB) * 1024 * 1024;
        if ($file->getSize() > $limit) {
            return lang('Frm.errFileSize', [(int) round($limit / 1048576)]);
        }

        if (!in_array((string) $file->getMimeType(), self::ALLOWED_MIME, true)) {
            return lang('Frm.errFileType');
        }

        $exts = self::extensionList((string) ($field['allowedExtensions'] ?? ''));
        if ($exts && !in_array(mb_strtolower($file->getExtension()), $exts, true)) {
            return lang('Frm.errFileExt', [implode(', ', $exts)]);
        }

        return null;
    }

    /** Guarda un archivo ya comprobado y devuelve su id. */
    public function store(UploadedFile $file, int $responseId, int $formId, int $companyId, string $fieldId): int
    {
        $name = $file->getRandomName();
        $file->move($this->dir($companyId), $name);

        $this->model->insert([
            'response_id'   => $responseId,
            'form_id'       => $formId,
            'company_id'    => $companyId,
            'field_id'      => $fieldId,
            'original_name' => mb_substr((string) $file->getClientName(), 0, 190),
            'stored_name'   => $name,
            'mime'          => mb_substr((string) $file->getClientMimeType(), 0, 120),
            'size'          => (int) $file->getSize(),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->model->getInsertID();
    }

    /**
     * Guarda una firma dibujada. Llega como data URI y se comprueba que sea de
     * verdad un PNG antes de escribir nada: lo que manda el navegador es una
     * propuesta, no un hecho.
     *
     * @return int|null id del adjunto, o null si no era una firma válida
     */
    public function storeSignature(string $dataUri, int $responseId, int $formId, int $companyId, string $fieldId): ?int
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', trim($dataUri), $m)) {
            return null;
        }

        $bin = base64_decode($m[1], true);
        // Cabecera PNG. Sin esto, cualquier binario con el rótulo correcto
        // acabaría guardado como si fuera una imagen.
        if ($bin === false || strncmp($bin, "\x89PNG\r\n\x1a\n", 8) !== 0) {
            return null;
        }
        if (strlen($bin) > 2 * 1024 * 1024) {
            return null;
        }

        $name = bin2hex(random_bytes(12)) . '.png';
        if (file_put_contents($this->dir($companyId) . DIRECTORY_SEPARATOR . $name, $bin) === false) {
            return null;
        }

        $this->model->insert([
            'response_id'   => $responseId,
            'form_id'       => $formId,
            'company_id'    => $companyId,
            'field_id'      => $fieldId,
            'original_name' => 'firma.png',
            'stored_name'   => $name,
            'mime'          => 'image/png',
            'size'          => strlen($bin),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->model->getInsertID();
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function forResponse(int $responseId): array
    {
        return $this->model->forResponse($responseId);
    }

    public function absolutePath(array $att): string
    {
        return $this->dir((int) $att['company_id']) . DIRECTORY_SEPARATOR . $att['stored_name'];
    }

    /** Borra los archivos de una respuesta y sus filas. */
    public function deleteForResponse(int $responseId): void
    {
        foreach ($this->model->forResponse($responseId) as $att) {
            @unlink($this->absolutePath($att));
        }
        $this->model->where('response_id', $responseId)->delete();
    }
}
