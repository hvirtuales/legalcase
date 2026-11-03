<?php

namespace Components\LegalCase\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\LegalCase\Models\DocumentModel;

/**
 * Documentos del asunto.
 *
 *  · Fuera de `public/`: `writable/uploads/lgc/<empresa>/<nombre aleatorio>`. El nombre
 *    original solo se guarda en BD; en disco nunca hay un nombre que el usuario controle.
 *  · Tipo comprobado por el CONTENIDO (`getMimeType()`, finfo), no por la extensión ni
 *    por lo que declara el navegador.
 *  · SHA-256 de cada archivo: evidencia de integridad y detección de duplicados dentro
 *    del asunto.
 *  · Clasificación por defecto `internal`; lo que sube el cliente entra como `review`.
 *    Solo `shared` se ve en el portal y compartir queda en la bitácora.
 *  · Punto de extensión para antivirus: `scan()` (el documento maestro pide escaneo en
 *    F-05). Hoy acepta si no hay escáner configurado y lo deja anotado.
 */
class DocumentService
{
    public const MAX_BYTES = 15728640; // 15 MB

    public const ALLOWED = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private function dir(int $companyId): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'lgc' . DIRECTORY_SEPARATOR . $companyId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /**
     * Guarda un archivo subido.
     *
     * @param array{source?:string,classification?:string,request_id?:?int,user_id?:?int,account_id?:?int} $opt
     */
    public function store(UploadedFile $file, int $companyId, int $matterId, array $opt = []): Result
    {
        if (!$file->isValid() || $file->hasMoved()) {
            return Result::fail(lang('Lgc.errFile'));
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return Result::fail(lang('Lgc.errFileSize', [(int) (self::MAX_BYTES / 1048576)]));
        }
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, self::ALLOWED, true)) {
            return Result::fail(lang('Lgc.errFileType'));
        }

        $hash = hash_file('sha256', $file->getTempName());
        $dup  = (new DocumentModel())->where('company_id', $companyId)->where('matter_id', $matterId)->where('sha256', $hash)->first();
        if ($dup) {
            return Result::fail(lang('Lgc.errFileDuplicate', [$dup['name']]));
        }
        if (!$this->scan($file->getTempName())) {
            return Result::fail(lang('Lgc.errFileInfected'));
        }

        $stored = bin2hex(random_bytes(16)) . '.bin';
        $file->move($this->dir($companyId), $stored);

        $class = in_array($opt['classification'] ?? '', DocumentModel::CLASSIFICATIONS, true) ? $opt['classification'] : 'internal';
        $model = new DocumentModel();
        $model->insert([
            'company_id'          => $companyId,
            'matter_id'           => $matterId,
            'request_id'          => $opt['request_id'] ?? null,
            'name'                => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $file->getClientName()) ?: 'documento', 0, 190),
            'path'                => $stored,
            'mime'                => $mime,
            'size'                => (int) $file->getSize(),
            'sha256'              => $hash,
            'classification'      => $class,
            'source'              => ($opt['source'] ?? 'staff') === 'client' ? 'client' : 'staff',
            'uploaded_by'         => $opt['user_id'] ?? null,
            'uploaded_by_account' => $opt['account_id'] ?? null,
            'shared_by'           => $class === 'shared' ? ($opt['user_id'] ?? null) : null,
            'shared_at'           => $class === 'shared' ? date('Y-m-d H:i:s') : null,
        ]);
        $id = (int) $model->getInsertID();

        Audit::log($companyId, $matterId, 'document.uploaded', 'document', $id, ['class' => $class, 'size' => (int) $file->getSize()],
            ($opt['source'] ?? 'staff') === 'client' ? 'portal' : 'user', $opt['account_id'] ?? ($opt['user_id'] ?? null));

        return Result::ok(lang('Lgc.okDocument'), $id);
    }

    public function classify(array $doc, string $class, Access $acc): Result
    {
        if (!in_array($class, DocumentModel::CLASSIFICATIONS, true)) {
            return Result::fail(lang('Lgc.errClassification'));
        }
        $data = ['classification' => $class];
        if ($class === 'shared') {
            $data += ['shared_by' => $acc->userId ?: null, 'shared_at' => date('Y-m-d H:i:s')];
        }
        (new DocumentModel())->update((int) $doc['id'], $data);
        Audit::log($acc->companyId, (int) $doc['matter_id'], $class === 'shared' ? 'document.shared' : 'document.classified', 'document', (int) $doc['id'], ['from' => $doc['classification'], 'to' => $class]);

        return Result::ok(lang('Lgc.okClassified'));
    }

    public function delete(array $doc, Access $acc): Result
    {
        // Soft-delete: el archivo se conserva como evidencia; deja de verse y de descargarse.
        (new DocumentModel())->delete((int) $doc['id']);
        Audit::log($acc->companyId, (int) $doc['matter_id'], 'document.deleted', 'document', (int) $doc['id']);

        return Result::ok(lang('Lgc.okDeleted'));
    }

    public function absolutePath(array $doc): string
    {
        return $this->dir((int) $doc['company_id']) . DIRECTORY_SEPARATOR . basename((string) $doc['path']);
    }

    /**
     * Antivirus. Si hay `clamscan`/`clamdscan` en el PATH y la función está disponible, lo
     * usa; si no, acepta (documentado como pendiente de infraestructura).
     */
    private function scan(string $path): bool
    {
        if (!function_exists('shell_exec') || stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            return true;
        }
        $bin = trim((string) @shell_exec('command -v clamdscan || command -v clamscan'));
        if ($bin === '') {
            return true;
        }
        $out  = [];
        $code = 0;
        @exec(escapeshellcmd($bin) . ' --no-summary ' . escapeshellarg($path), $out, $code);

        // 0 = limpio, 1 = infectado, 2 = error del escáner (no bloquea, se registra).
        if ($code === 2) {
            log_message('warning', 'LegalCase: el antivirus no pudo analizar un archivo.');
        }

        return $code !== 1;
    }
}
