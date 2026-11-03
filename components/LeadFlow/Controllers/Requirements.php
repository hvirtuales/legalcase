<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\DocumentService;
use Components\LeadFlow\Libraries\MatterService;
use Components\LeadFlow\Libraries\Timeline;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\MatterRequirementModel;
use Components\LeadFlow\Models\ServiceRequirementModel;

/**
 * El checklist documental de un expediente.
 *
 * ── Dos puertas, no una ──────────────────────────────────────────────────────
 *
 * El id que llega del POST es el del REQUISITO, pero la autorización no se resuelve
 * sobre él: se resuelve subiendo al expediente y de ahí al lead, con
 * `BaseLfl::matter()`. Tener `lfl.edit` no autoriza a aprobar el requisito del
 * expediente de otra firma, y comprobar solo `company_id` del requisito dejaría fuera
 * la visibilidad por función comercial.
 *
 * ── La subida reutiliza `DocumentService` sin tocarlo ────────────────────────
 *
 * El documento sigue siendo del LEAD, como todos: el requisito solo guarda a cuál
 * apunta. Así el archivo aparece en la pestaña de documentos del lead, viaja a
 * Docketwise si se autoriza y se borra con el lead, sin que nada de eso tenga que
 * aprender qué es un expediente.
 */
class Requirements extends BaseLfl
{
    /**
     * El requisito con su expediente y su lead, o null.
     *
     * @return array{req: array, matter: array, lead: array}|null
     */
    private function req(int $id, bool $write = true): ?array
    {
        $cid = $this->companyId();
        if ($cid <= 0 || $id <= 0) {
            return null;
        }

        $req = (new MatterRequirementModel())->owned($id, $cid);
        if (! $req) {
            return null;
        }

        $par = $this->matter((int) $req['matter_id'], $write);

        return $par ? ['req' => $req] + $par : null;
    }

    private function volver(int $matterId): string
    {
        return 'leadflow/expedientes/ficha/' . $matterId . '?tab=requisitos';
    }

    /** Añade un requisito suelto, fuera de la plantilla del servicio. */
    public function store($matterId = null)
    {
        $par = $this->matter((int) $matterId, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$matter, $lead] = [$par['matter'], $par['lead']];

        $etiqueta = $this->str('label', 120);
        if ($etiqueta === '') {
            $this->flash('error', lang('Lfl.vRequirementLabel'));

            return $this->back($this->volver((int) $matter['id']));
        }

        // Contra la lista blanca, no contra lo que llegue: `applies_to` viene del
        // formulario y un valor inventado dejaría una fila que no agrupa con ninguna.
        $aplica = (string) $this->request->getPost('applies_to');
        $aplica = in_array($aplica, ServiceRequirementModel::APPLIES, true) ? $aplica : 'client';
        $tipo   = (int) $this->request->getPost('document_type_id');

        (new MatterRequirementModel())->insert([
            'company_id'       => (int) $matter['company_id'],
            'matter_id'        => (int) $matter['id'],
            'label'            => $etiqueta,
            'document_type_id' => $tipo > 0 ? $tipo : null,
            'is_required'      => $this->request->getPost('is_required') ? 1 : 0,
            'applies_to'       => $aplica,
            'due_at'           => $this->fecha('due_date'),
            'sort'             => 900,
            'status'           => 'pending',
        ]);

        (new MatterService())->refreshChecklist((int) $matter['id']);
        (new MatterService())->refreshDeadline((int) $matter['id']);
        $this->flash('success', lang('Lfl.okCreated'));

        return $this->back($this->volver((int) $matter['id']));
    }

    /** Sube el documento que satisface un requisito. */
    public function upload($id = null)
    {
        $par = $this->req((int) $id);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$req, $matter, $lead] = [$par['req'], $par['matter'], $par['lead']];

        $file = $this->request->getFile('file');
        $cid  = (int) $matter['company_id'];

        /*
         * `DocumentService` deduplica por hash DENTRO DEL LEAD y, si el archivo ya
         * estaba, falla con «documento duplicado» y `id = 0`. Para un checklist eso
         * sería un callejón sin salida: un mismo PDF escaneado puede cubrir dos
         * requisitos legítimamente (pasaporte del titular y del cónyuge en una tanda),
         * y el segundo no podría marcarse nunca.
         *
         * Así que se mira ANTES: si el documento ya existe en este lead, se enlaza al
         * requisito en vez de volver a subirlo. No se toca `DocumentService`, que lo
         * usan también la pestaña del lead y la conversión a Docketwise.
         */
        $existente = null;
        if ($file && $file->isValid()) {
            $existente = (new DocumentModel())->forCompany($cid)
                ->where('lead_id', (int) $lead['id'])
                ->where('hash', (string) hash_file('sha256', $file->getTempName()))
                ->first();
        }

        if ($existente !== null) {
            $r = ['ok' => true, 'message' => lang('Lfl.mtrDocLinked'), 'id' => (int) $existente['id']];
        } else {
            $r = (new DocumentService())->store(
                $lead,
                $file,
                $req['document_type_id'] ? (int) $req['document_type_id'] : null,
                'internal',
                $this->userId()
            );
        }

        if ($r['ok']) {
            (new MatterRequirementModel())->update((int) $req['id'], [
                'status'      => 'received',
                'document_id' => (int) ($r['id'] ?? 0) ?: null,
                'received_at' => date('Y-m-d H:i:s'),
            ]);
            (new MatterService())->refreshChecklist((int) $matter['id']);
        (new MatterService())->refreshDeadline((int) $matter['id']);

            Timeline::log($cid, (int) $lead['id'], 'document',
                lang('Lfl.tlRequirementReceived', [$req['label'], $matter['number']]),
                ['matter' => (int) $matter['id'], 'requirement' => (int) $req['id']], $this->userId());
        }

        $this->result($r);

        return $this->back($this->volver((int) $matter['id']));
    }

    /**
     * Cambia el estado del requisito: recibido, aprobado, rechazado o eximido.
     *
     * `waived` es el que no se puede deducir de los documentos —es una decisión del
     * abogado sobre ESTE caso—, y por eso el estado vive en su propia columna en vez de
     * calcularse cruzando el checklist con los archivos subidos.
     */
    public function status($id = null)
    {
        $par = $this->req((int) $id);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$req, $matter, $lead] = [$par['req'], $par['matter'], $par['lead']];

        $estado = (string) $this->request->getPost('status');
        if (! in_array($estado, MatterRequirementModel::STATUSES, true)) {
            $this->flash('error', lang('Lfl.errInvalid'));

            return $this->back($this->volver((int) $matter['id']));
        }

        $nota = $this->str('note', 255);

        // Rechazar sin decir por qué deja al cliente sin saber qué corregir.
        if ($estado === 'rejected' && $nota === '') {
            $this->flash('error', lang('Lfl.mtrNeedRejectNote'));

            return $this->back($this->volver((int) $matter['id']));
        }

        (new MatterRequirementModel())->update((int) $req['id'], [
            'status'      => $estado,
            'note'        => $nota ?: null,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $this->userId(),
        ] + ($estado === 'received' && empty($req['received_at'])
            ? ['received_at' => date('Y-m-d H:i:s')] : []));

        (new MatterService())->refreshChecklist((int) $matter['id']);
        (new MatterService())->refreshDeadline((int) $matter['id']);

        Timeline::log((int) $matter['company_id'], (int) $lead['id'], 'document',
            lang('Lfl.tlRequirementStatus', [$req['label'], lang('Lfl.rq_' . $estado)]),
            ['matter' => (int) $matter['id'], 'requirement' => (int) $req['id'], 'status' => $estado],
            $this->userId());

        $this->flash('success', lang('Lfl.okUpdated'));

        return $this->back($this->volver((int) $matter['id']));
    }

    public function delete($id = null)
    {
        $par = $this->req((int) $id);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }

        (new MatterRequirementModel())->delete((int) $par['req']['id']);
        (new MatterService())->refreshChecklist((int) $par['matter']['id']);
        (new MatterService())->refreshDeadline((int) $par['matter']['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return $this->back($this->volver((int) $par['matter']['id']));
    }

    /** Una fecha del POST en formato de BD, o null si no vino o no es válida. */
    private function fecha(string $campo): ?string
    {
        $v = trim((string) $this->request->getPost($campo));
        if ($v === '') {
            return null;
        }
        $t = strtotime($v);

        return $t ? date('Y-m-d H:i:s', $t) : null;
    }
}
