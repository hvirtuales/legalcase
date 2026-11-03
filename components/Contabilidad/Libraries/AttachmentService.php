<?php

namespace Components\Contabilidad\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\Contabilidad\Models\AttachmentModel;

/**
 * Adjuntos polimórficos. Archivos privados en writable/uploads/contabilidad
 * (fuera de public/); solo se descargan por controlador con permiso.
 */
class AttachmentService
{
    public const MAX_SIZE = 5_242_880; // 5 MB
    public const ALLOWED  = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx', 'xls', 'xlsx'];

    private const OWNER_TABLE = [
        'voucher'         => 'cmp_cont_vouchers',
        'payment_request' => 'cmp_cont_payment_requests',
        'beneficiary'     => 'cmp_cont_beneficiaries',
    ];

    private function baseDir(): string
    {
        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'contabilidad';
    }

    public static function isOwnerType(string $t): bool
    {
        return isset(self::OWNER_TABLE[$t]);
    }

    /** @return array{ok:bool, message:string} */
    public function store(UploadedFile $file, string $ownerType, int $ownerId, ?int $userId = null): array
    {
        if (!self::isOwnerType($ownerType)) {
            return ['ok' => false, 'message' => 'Tipo de adjunto inválido.'];
        }
        if (!$file->isValid()) {
            return ['ok' => false, 'message' => $file->getErrorString()];
        }
        $size       = $file->getSize();
        $clientName = $file->getClientName() ?: 'archivo';
        $mime       = $file->getClientMimeType() ?: 'application/octet-stream';
        $ext        = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if ($size > self::MAX_SIZE) {
            return ['ok' => false, 'message' => 'El archivo supera 5 MB.'];
        }
        if (!in_array($ext, self::ALLOWED, true)) {
            return ['ok' => false, 'message' => 'Tipo de archivo no permitido (' . esc($ext) . ').'];
        }

        try {
            $dir = $this->baseDir() . DIRECTORY_SEPARATOR . $ownerType;
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $name = bin2hex(random_bytes(8)) . '.' . $ext;
            $file->move($dir, $name);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el archivo.'];
        }

        (new AttachmentModel())->insert([
            'owner_type'    => $ownerType,
            'owner_id'      => $ownerId,
            'original_name' => mb_substr($clientName, 0, 255),
            'mimetype'      => $mime,
            'size'          => $size,
            'file_path'     => $ownerType . '/' . $name,
            'uploaded_by'   => $userId,
        ]);

        return ['ok' => true, 'message' => 'Adjunto subido.'];
    }

    public function listFor(string $ownerType, int $ownerId): array
    {
        return (new AttachmentModel())->where('owner_type', $ownerType)->where('owner_id', $ownerId)
            ->orderBy('id', 'asc')->findAll();
    }

    public function find(int $id): ?array
    {
        return (new AttachmentModel())->find($id);
    }

    public function absolutePath(array $att): string
    {
        return $this->baseDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $att['file_path']);
    }

    public function delete(int $id): void
    {
        $att = $this->find($id);
        if (!$att) {
            return;
        }
        $path = $this->absolutePath($att);
        if (is_file($path)) {
            @unlink($path);
        }
        (new AttachmentModel())->delete($id);
    }

    /** company_id del dueño del adjunto (para aislar por empresa). */
    public function ownerCompanyId(string $ownerType, int $ownerId): ?int
    {
        $table = self::OWNER_TABLE[$ownerType] ?? null;
        if (!$table) {
            return null;
        }
        $row = \Config\Database::connect()->table($table)->select('company_id')->where('id', $ownerId)->get()->getRow();

        return $row ? (int) $row->company_id : null;
    }
}
