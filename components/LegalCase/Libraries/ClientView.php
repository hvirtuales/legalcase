<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\DocRequestModel;
use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\MessageModel;
use Components\LegalCase\Models\ThreadModel;
use Components\LegalCase\Models\UpdateModel;

/**
 * Lo que el CLIENTE puede ver, y nada más (§6.2, §6.13-1).
 *
 * Es la única puerta de datos del portal. Cada método parte de la cuenta autenticada y
 * resuelve el asunto por la relación `participante con portal_access = 1` de ESA cuenta
 * en ESA empresa; un id cambiado en la URL devuelve null.
 *
 * Devuelve listas blancas de campos: el asunto llega sin `description`, `objectives`,
 * `strategy`, `risk_level` ni `title` interno; los documentos solo `shared`; las
 * actualizaciones solo `published`.
 */
class ClientView
{
    /** Campos del asunto que el portal puede pintar. */
    private const MATTER_FIELDS = ['id', 'company_id', 'number', 'public_title', 'public_stage', 'stage_hidden', 'status', 'lead_lawyer_id', 'last_published_at', 'next_update_due_at', 'opened_at', 'closed_at'];

    private function matterIds(array $account): array
    {
        $rows = db_connect()->table('cmp_lgc_participants p')
            ->select('p.matter_id')
            ->join('cmp_lgc_matters m', 'm.id = p.matter_id')
            ->where('p.company_id', (int) $account['company_id'])
            ->where('m.company_id', (int) $account['company_id'])
            ->where('p.contact_id', (int) $account['contact_id'])
            ->where('p.portal_access', 1)
            ->where('m.deleted_at', null)
            ->get()->getResultArray();

        return array_values(array_unique(array_map('intval', array_column($rows, 'matter_id'))));
    }

    private function publicMatter(array $m): array
    {
        $out          = array_intersect_key($m, array_flip(self::MATTER_FIELDS));
        $out['label'] = MatterModel::clientTitle($m);

        return $out;
    }

    /** Asuntos visibles para la cuenta. */
    public function matters(array $account): array
    {
        $ids = $this->matterIds($account);
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach ((new MatterModel())->where('company_id', (int) $account['company_id'])->whereIn('id', $ids)->orderBy('opened_at', 'DESC')->findAll() as $m) {
            $pub                = $this->publicMatter($m);
            $pub['last_update'] = (new UpdateModel())->where('matter_id', (int) $m['id'])->where('status', 'published')
                ->orderBy('published_at', 'DESC')->first();
            $pub['pending']     = (new DocRequestModel())->where('matter_id', (int) $m['id'])->whereIn('status', ['pending', 'rejected'])->countAllResults();
            $out[]              = $pub;
        }

        return $out;
    }

    /** El asunto (versión pública) si la cuenta tiene acceso; null si no. */
    public function matter(array $account, int $matterId): ?array
    {
        if (!in_array($matterId, $this->matterIds($account), true)) {
            return null;
        }
        $m = (new MatterModel())->where('company_id', (int) $account['company_id'])->find($matterId);

        return $m ? $this->publicMatter($m) : null;
    }

    /** Todo lo de la pantalla del asunto. */
    public function detail(array $account, array $matter): array
    {
        $mid = (int) $matter['id'];

        $threads = (new ThreadModel())->where('matter_id', $mid)->orderBy('last_message_at', 'DESC')->findAll(50);
        $msgs    = new MessageModel();
        foreach ($threads as &$t) {
            $t['messages'] = array_map(
                static fn ($x) => array_intersect_key($x, array_flip(['id', 'direction', 'body', 'is_auto', 'created_at', 'author_user_id'])),
                $msgs->forThread((int) $t['id'])
            );
        }
        unset($t);

        return [
            'updates'  => (new UpdateModel())->publishedFor($mid),
            'requests' => (new DocRequestModel())->where('matter_id', $mid)->whereIn('status', DocRequestModel::STATUSES)
                ->where('status !=', 'cancelled')->orderBy('created_at', 'DESC')->findAll(100),
            'docs'     => (new DocumentModel())->where('matter_id', $mid)->where('classification', 'shared')->orderBy('created_at', 'DESC')->findAll(200),
            'mine'     => (new DocumentModel())->where('matter_id', $mid)->where('source', 'client')
                ->where('uploaded_by_account', (int) $account['id'])->orderBy('created_at', 'DESC')->findAll(200),
            'threads'  => $threads,
            'lawyer'   => Team::name((int) $matter['company_id'], (int) $matter['lead_lawyer_id'] ?: null),
        ];
    }

    /** Documento descargable por la cuenta: compartido, o subido por ella misma. */
    public function document(array $account, int $docId): ?array
    {
        $doc = (new DocumentModel())->where('company_id', (int) $account['company_id'])->find($docId);
        if (!$doc || !$this->matter($account, (int) $doc['matter_id'])) {
            return null;
        }
        $own = $doc['source'] === 'client' && (int) $doc['uploaded_by_account'] === (int) $account['id'];

        return ($doc['classification'] === 'shared' || $own) ? $doc : null;
    }

    /** Solicitud abierta de un asunto al que la cuenta tiene acceso. */
    public function request(array $account, int $requestId): ?array
    {
        $r = (new DocRequestModel())->where('company_id', (int) $account['company_id'])->find($requestId);
        if (!$r || !in_array($r['status'], DocRequestModel::OPEN, true) || !$this->matter($account, (int) $r['matter_id'])) {
            return null;
        }

        return $r;
    }
}
