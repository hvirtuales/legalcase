<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\DocumentService;
use Components\LegalCase\Libraries\MessageService;
use Components\LegalCase\Models\DocRequestModel;
use Components\LegalCase\Models\DocumentModel;

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

        $r = (new DocumentService())->store($file, $this->companyId(), (int) $matter['id'], [
            'source'         => 'staff',
            'classification' => (string) $this->post('classification', 'internal'),
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

        return $this->done((new DocumentService())->classify($doc, (string) $this->post('classification'), $this->acc()), $this->matterUrl($matter, 'documentos'));
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
}
