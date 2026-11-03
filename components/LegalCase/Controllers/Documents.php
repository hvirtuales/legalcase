<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\DocumentService;
use Components\LegalCase\Libraries\MessageService;
use Components\LegalCase\Models\DocRequestModel;
use Components\LegalCase\Libraries\SignatureService;
use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\SignatureModel;

/** Documentos del asunto y solicitudes de documentos al cliente. */
class Documents extends BaseLgc
{
    public function upload($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        $file = $this->request->getFile('file');
        if (!$file) {
            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errFile')));
        }

        $class = (string) $this->post('classification', 'internal');
        if ($class === 'shared' && !$this->acc()->can('lgc.share')) {
            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errNeedShare')));
        }
        $r = (new DocumentService())->store($file, $this->companyId(), (int) $matter['id'], [
            'source'         => 'staff',
            'classification' => $class,
            'user_id'        => $this->acc()->userId ?: null,
        ]);

        return $this->done($r, $this->matterUrl($matter, 'documentos'));
    }

    public function download($id = null)
    {
        [$doc, $matter] = $this->findChild(DocumentModel::class, $id);
        if (!$doc) {
            return $this->notFound();
        }
        // Lo privilegiado es del equipo del asunto («necesidad de conocer»).
        if ($doc['classification'] === 'privileged' && !$this->acc()->isInsider($matter)) {
            Audit::log($this->companyId(), (int) $matter['id'], 'document.denied', 'document', (int) $doc['id']);

            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errPrivileged')));
        }
        $path = (new DocumentService())->absolutePath($doc);
        if (!is_file($path)) {
            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errFileMissing')));
        }
        Audit::log($this->companyId(), (int) $matter['id'], 'document.downloaded', 'document', (int) $doc['id']);

        return $this->response->download($path, null)->setFileName($doc['name'])
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function classify($id = null)
    {
        [$doc, $matter] = $this->findChild(DocumentModel::class, $id);
        if (!$doc) {
            return $this->notFound();
        }

        if ($doc['classification'] === 'privileged' && !$this->acc()->isInsider($matter)) {
            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errPrivileged')));
        }

        return $this->done((new DocumentService())->classify($doc, (string) $this->post('classification'), $this->acc(), $this->post('confirm_privileged') === '1'), $this->matterUrl($matter, 'documentos'));
    }

    public function delete($id = null)
    {
        [$doc, $matter] = $this->findChild(DocumentModel::class, $id);
        if (!$doc) {
            return $this->notFound();
        }

        return $this->done((new DocumentService())->delete($doc, $this->acc()), $this->matterUrl($matter, 'documentos'));
    }

    public function request($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        if ($matter['status'] === 'closed') {
            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errAlreadyClosed')));
        }

        return $this->done((new MessageService())->requestDocument($matter, $this->request->getPost(), $this->acc()), $this->matterUrl($matter, 'documentos'));
    }

    public function review($id = null)
    {
        [$req, $matter] = $this->findChild(DocRequestModel::class, $id);
        if (!$req) {
            return $this->notFound();
        }

        return $this->done(
            (new MessageService())->reviewRequest($req, $matter, (string) $this->post('decision'), (string) $this->post('note'), $this->acc()),
            $this->matterUrl($matter, 'documentos')
        );
    }

    /** POST — sube una nueva versión de un documento (la anterior se conserva). */
    public function version($id = null)
    {
        [$doc, $matter] = $this->findChild(DocumentModel::class, $id);
        if (!$doc) {
            return $this->notFound();
        }
        if ($doc['classification'] === 'privileged' && !$this->acc()->isInsider($matter)) {
            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errPrivileged')));
        }
        $file = $this->request->getFile('file');
        if (!$file) {
            return redirect()->to($this->matterUrl($matter, 'documentos'))->with('error', esc(lang('Lgc.errFile')));
        }

        return $this->done((new DocumentService())->newVersion($doc, $file, $this->acc()), $this->matterUrl($matter, 'documentos'));
    }

    /** POST — pide al cliente que firme un documento compartido. */
    public function requestSignature($id = null)
    {
        [$doc, $matter] = $this->findChild(DocumentModel::class, $id);
        if (!$doc) {
            return $this->notFound();
        }

        return $this->done((new SignatureService())->request($matter, $doc, (int) $this->post('account_id'), (string) $this->post('purpose'), $this->acc()), $this->matterUrl($matter, 'documentos'));
    }

    public function cancelSignature($id = null)
    {
        [$sig, $matter] = $this->findChild(SignatureModel::class, $id);
        if (!$sig) {
            return $this->notFound();
        }

        return $this->done((new SignatureService())->cancel($sig, $this->acc()), $this->matterUrl($matter, 'documentos'));
    }

    /** GET — constancia imprimible de la firma (evidencia). */
    public function evidence($id = null)
    {
        [$sig, $matter] = $this->findChild(SignatureModel::class, $id);
        if (!$sig) {
            return $this->notFound();
        }

        return view('Components\LegalCase\Views\signatures\evidence', [
            'title'   => lang('Lgc.signEvidence'),
            'sig'     => $sig,
            'matter'  => $matter,
            'doc'     => (new DocumentModel())->withDeleted()->find((int) $sig['document_id']),
            'account' => db_connect()->table('cmp_lgc_portal_accounts')->where('id', (int) $sig['account_id'])->get()->getRowArray(),
            'intact'  => SignatureService::verify($sig),
        ]);
    }
}
