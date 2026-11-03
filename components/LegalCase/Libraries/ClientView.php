<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\DocRequestModel;
use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\HearingModel;
use Components\LegalCase\Models\SignatureModel;
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
            ->select('p.matter_id, p.role, p.authority_document_id, p.authority_until, c.legal_capacity')
            ->join('cmp_lgc_contacts c', 'c.id = p.contact_id')
            ->join('cmp_lgc_matters m', 'm.id = p.matter_id')
            ->where('p.company_id', (int) $account['company_id'])
            ->where('m.company_id', (int) $account['company_id'])
            ->where('p.contact_id', (int) $account['contact_id'])
            ->where('p.portal_access', 1)
            ->where('m.deleted_at', null)
            ->get()->getResultArray();

        // Un representante con el poder vencido o sin acreditar deja de ver el asunto.
        $rows = array_filter($rows, static fn ($r) => Portal::authorityValid($r) && ($r['legal_capacity'] ?? 'full') === 'full');

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

        $threads = $this->threadsQuery($account, $mid)->orderBy('last_message_at', 'DESC')->findAll(50);
        $msgs    = new MessageModel();
        foreach ($threads as &$t) {
            // Solo lo ENVIADO: un borrador en revisión o descartado nunca llega al cliente.
            $t['messages'] = array_map(
                static fn ($x) => array_intersect_key($x, array_flip(['id', 'direction', 'body', 'is_auto', 'created_at', 'author_user_id'])),
                $msgs->sentForThread((int) $t['id'])
            );
        }
        unset($t);

        return [
            'updates'  => (new UpdateModel())->publishedFor($mid),
            'requests' => $this->requestsQuery($account, $mid)->where('status !=', 'cancelled')->orderBy('created_at', 'DESC')->findAll(100),
            'docs'     => (new DocumentModel())->where('matter_id', $mid)->where('classification', 'shared')->where('is_current', 1)->orderBy('created_at', 'DESC')->findAll(200),
            'hearings' => array_map(
                // Lista blanca: nunca las notas internas; el enlace solo si es https.
                static fn ($h) => array_intersect_key($h, array_flip(['id', 'title', 'scheduled_at', 'mode', 'location', 'client_attendance', 'client_notes', 'status']))
                    + ['link' => preg_match('#^https://#i', (string) $h['link']) ? (string) $h['link'] : ''],
                (new HearingModel())->where('matter_id', $mid)->where('is_shared', 1)->whereIn('status', ['scheduled', 'postponed'])
                    ->where('scheduled_at >=', date('Y-m-d H:i:s', strtotime('-1 day')))->orderBy('scheduled_at', 'ASC')->findAll(20)
            ),
            'signatures' => (new SignatureModel())->where('matter_id', $mid)->where('account_id', (int) $account['id'])->orderBy('id', 'DESC')->findAll(50),
            'mine'     => (new DocumentModel())->where('matter_id', $mid)->where('source', 'client')
                ->where('uploaded_by_account', (int) $account['id'])->orderBy('created_at', 'DESC')->findAll(200),
            'threads'  => $threads,
            'lawyer'   => Team::name((int) $matter['company_id'], (int) $matter['lead_lawyer_id'] ?: null),
        ];
    }

    /**
     * Hilos de la cuenta en el asunto: los suyos y los que el despacho marcó como compartidos.
     * Entre coclientes rige el secreto: lo que uno escribe no lo lee el otro.
     */
    private function threadsQuery(array $account, int $matterId): ThreadModel
    {
        $aid = (int) $account['id'];
        $m   = new ThreadModel();
        $m->where('matter_id', $matterId)->where('company_id', (int) $account['company_id'])
            ->groupStart()
            ->where('is_shared', 1)
            ->orWhere('account_id', $aid)
            ->orGroupStart()->where('account_id', null)->where('opened_by_account', $aid)->groupEnd()
            ->groupEnd();

        return $m;
    }

    /** Solicitudes dirigidas a la cuenta o a cualquiera de los clientes del asunto. */
    private function requestsQuery(array $account, int $matterId): DocRequestModel
    {
        $m = new DocRequestModel();
        $m->where('matter_id', $matterId)->where('company_id', (int) $account['company_id'])
            ->groupStart()->where('account_id', null)->orWhere('account_id', (int) $account['id'])->groupEnd();

        return $m;
    }

    /** Hilo visible para la cuenta (para responder), o null. */
    public function thread(array $account, int $matterId, int $threadId): ?array
    {
        return $this->threadsQuery($account, $matterId)->find($threadId) ?: null;
    }

    /** Documento descargable por la cuenta: compartido, o subido por ella misma. */
    public function document(array $account, int $docId): ?array
    {
        $doc = (new DocumentModel())->where('company_id', (int) $account['company_id'])->find($docId);
        if (!$doc || !$this->matter($account, (int) $doc['matter_id'])) {
            return null;
        }
        $own = $doc['source'] === 'client' && (int) $doc['uploaded_by_account'] === (int) $account['id'];

        // Un documento con firma pendiente para esta cuenta se puede leer aunque se haya reclasificado.
        $toSign = (new SignatureModel())->where('document_id', (int) $doc['id'])->where('account_id', (int) $account['id'])->countAllResults() > 0;

        return ($doc['classification'] === 'shared' || $own || $toSign) ? $doc : null;
    }

    /** Solicitud de firma de ESTA cuenta, o null. */
    public function signature(array $account, int $sigId): ?array
    {
        $s = (new SignatureModel())->where('company_id', (int) $account['company_id'])->where('account_id', (int) $account['id'])->find($sigId);

        return $s && $this->matter($account, (int) $s['matter_id']) ? $s : null;
    }

    /** Solicitud abierta de un asunto al que la cuenta tiene acceso. */
    public function request(array $account, int $requestId): ?array
    {
        $r = (new DocRequestModel())->where('company_id', (int) $account['company_id'])->find($requestId);
        if (!$r || !in_array($r['status'], DocRequestModel::OPEN, true) || !$this->matter($account, (int) $r['matter_id'])) {
            return null;
        }
        if (!empty($r['account_id']) && (int) $r['account_id'] !== (int) $account['id']) {
            return null; // dirigida a otro cliente del mismo asunto
        }

        return $r;
    }
}
