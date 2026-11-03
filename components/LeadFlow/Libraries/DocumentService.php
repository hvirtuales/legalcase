<?php

namespace Components\LeadFlow\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\DocumentTypeModel;

/**
 * Documentos preliminares del lead (§24).
 *
 * **Fuera de `public/`** (`writable/uploads/leadflow/<empresa>/`): un pasaporte o
 * una visa en una URL adivinable es una fuga de datos personales. Se guarda el
 * **hash SHA-256** del archivo (§31: validación y hash) y se usa para no aceptar
 * dos veces el mismo archivo en el mismo lead. El tipo se valida por el contenido
 * (`getMimeType()`), no por la extensión.
 */
class DocumentService
{
    private DocumentModel $docs;

    public function __construct()
    {
        $this->docs = new DocumentModel();
    }

    public static function folder(int $companyId): string
    {
        $dir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'leadflow'
            . DIRECTORY_SEPARATOR . $companyId;
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public function store(array $lead, ?UploadedFile $file, ?int $typeId, string $source = 'internal', ?int $userId = null): array
    {
        $cid = (int) $lead['company_id'];

        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return ['ok' => false, 'message' => lang('Lfl.errDocFile'), 'id' => 0];
        }
        if ($file->getSize() > DocumentModel::MAX_BYTES) {
            return ['ok' => false, 'message' => lang('Lfl.errDocSize', [DocumentModel::MAX_BYTES / 1024 / 1024]), 'id' => 0];
        }
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, DocumentModel::MIMES, true)) {
            return ['ok' => false, 'message' => lang('Lfl.errDocType'), 'id' => 0];
        }

        $hash = (string) hash_file('sha256', $file->getTempName());
        $dup  = $this->docs->forCompany($cid)->where('lead_id', (int) $lead['id'])->where('hash', $hash)->first();
        if ($dup) {
            return ['ok' => false, 'message' => lang('Lfl.errDocDuplicate'), 'id' => 0];
        }

        if ($typeId && ! (new DocumentTypeModel())->owned($typeId, $cid)) {
            $typeId = null;
        }

        $original = mb_substr($file->getClientName(), 0, 255);
        $name     = $file->getRandomName();
        try {
            $file->move(self::folder($cid), $name);
        } catch (\Throwable $e) {
            log_message('error', 'LeadFlow: no se pudo guardar el documento: ' . $e->getMessage());

            return ['ok' => false, 'message' => lang('Lfl.errDocSave'), 'id' => 0];
        }

        $this->docs->insert([
            'company_id'       => $cid,
            'lead_id'          => (int) $lead['id'],
            'document_type_id' => $typeId,
            'original_name'    => $original,
            'storage_key'      => $name,
            'mime_type'        => $mime,
            'size'             => (int) $file->getSize(),
            'hash'             => $hash,
            'is_authorized'    => 0,
            'source'           => $source === 'public' ? 'public' : 'internal',
            'uploaded_by'      => $userId,
        ]);
        $id = (int) $this->docs->getInsertID();

        Timeline::log($cid, (int) $lead['id'], 'document', lang('Lfl.tlDocument', [$original]), [], $userId);
        (new ScoringService())->recalculate((int) $lead['id'], $cid, 'document');

        return ['ok' => true, 'message' => lang('Lfl.okDocUploaded'), 'id' => $id];
    }

    /** Ruta absoluta del archivo, o null si no existe. */
    public static function path(array $doc): ?string
    {
        $p = self::folder((int) $doc['company_id']) . DIRECTORY_SEPARATOR . basename((string) $doc['storage_key']);

        return is_file($p) ? $p : null;
    }

    public function toggleAuthorized(array $doc): array
    {
        $new = (int) $doc['is_authorized'] === 1 ? 0 : 1;
        $this->docs->update((int) $doc['id'], ['is_authorized' => $new]);
        Timeline::log((int) $doc['company_id'], (int) $doc['lead_id'], 'document',
            lang($new ? 'Lfl.tlDocAuthorized' : 'Lfl.tlDocUnauthorized', [$doc['original_name']]));

        return ['ok' => true, 'message' => lang($new ? 'Lfl.okDocAuthorized' : 'Lfl.okDocUnauthorized')];
    }

    public function remove(array $doc): array
    {
        $path = self::path($doc);
        if ($path) {
            @unlink($path);
        }
        $this->docs->delete((int) $doc['id']);
        (new ScoringService())->recalculate((int) $doc['lead_id'], (int) $doc['company_id'], 'document');

        return ['ok' => true, 'message' => lang('Lfl.okDeleted')];
    }
}
