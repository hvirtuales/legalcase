<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\DocumentService;
use Components\LeadFlow\Models\DocumentModel;

/**
 * Documentos preliminares: subir, descargar, autorizar la transferencia y borrar.
 *
 * La descarga va por aquí y no por una URL de `public/`: se comprueba el permiso
 * (en la ruta), que el documento sea de la empresa y que su lead sea visible para
 * el usuario. «No existe» y «no es tuyo» responden el mismo 404.
 */
class Documents extends BaseLfl
{
    private function doc(int $id, bool $write): ?array
    {
        $doc = (new DocumentModel())->owned($id, $this->companyId());

        return $doc && $this->lead((int) $doc['lead_id'], $write) ? $doc : null;
    }

    public function upload($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new DocumentService())->store($lead, $this->request->getFile('document'),
            (int) $this->request->getPost('document_type_id') ?: null, 'internal', $this->userId()));

        return $this->toLead((int) $lead['id'], 'documentos');
    }

    public function download($id = null)
    {
        $doc  = $this->doc((int) $id, false);
        $path = $doc ? DocumentService::path($doc) : null;
        if (! $path) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName($doc['original_name']);
    }

    public function authorize($id = null)
    {
        $doc = $this->doc((int) $id, true);
        if (! $doc) {
            return $this->notFound();
        }
        $this->result((new DocumentService())->toggleAuthorized($doc));

        return $this->toLead((int) $doc['lead_id'], 'documentos');
    }

    public function delete($id = null)
    {
        $doc = $this->doc((int) $id, true);
        if (! $doc) {
            return $this->notFound();
        }
        $this->result((new DocumentService())->remove($doc));

        return $this->toLead((int) $doc['lead_id'], 'documentos');
    }
}
