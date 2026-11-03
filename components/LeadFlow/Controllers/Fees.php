<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\MatterService;
use Components\LeadFlow\Libraries\Timeline;
use Components\LeadFlow\Models\MatterFeeModel;
use Components\LeadFlow\Models\MatterPaymentModel;

/**
 * Honorarios y pagos de un expediente, más el estado de cuenta imprimible.
 *
 * ── Lo que esto NO es ────────────────────────────────────────────────────────
 *
 * No es facturación. Aquí no hay impuestos, ni retenciones, ni consecutivo fiscal, ni
 * asiento contable: eso es el componente Contabilidad, y este no lo toca ni por FK
 * (contrato §0.4). Lo que registra es lo pactado y lo recibido, que es lo que el
 * abogado necesita ver en la ficha y lo que se le enseña al cliente.
 *
 * ── Los totales no se suman, se recalculan ───────────────────────────────────
 *
 * Después de cada alta o baja se llama a `MatterService::refreshTotals()`, que suma
 * desde las filas. Aplicar deltas sería más rápido y dejaría el total desfasado el día
 * que una ruta se olvidara de hacerlo, sin que nadie lo notara.
 */
class Fees extends BaseLfl
{
    private function volver(int $matterId): string
    {
        return 'leadflow/expedientes/ficha/' . $matterId . '?tab=honorarios';
    }

    /** Añade una línea de lo pactado. */
    public function store($matterId = null)
    {
        $par = $this->matter((int) $matterId, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$matter, $lead] = [$par['matter'], $par['lead']];

        $concepto = $this->str('concept', 160);
        $importe  = (float) str_replace(',', '.', (string) $this->request->getPost('amount'));
        $tipo     = (string) $this->request->getPost('kind');

        if ($concepto === '' || $importe < 0) {
            $this->flash('error', lang('Lfl.vFeeConcept'));

            return $this->back($this->volver((int) $matter['id']));
        }

        (new MatterFeeModel())->insert([
            'company_id' => (int) $matter['company_id'],
            'matter_id'  => (int) $matter['id'],
            // Lista blanca: `kind` decide si el importe es ingreso de la firma o
            // dinero que solo pasa por ella, y un valor inventado rompería el desglose.
            'kind'       => in_array($tipo, MatterFeeModel::KINDS, true) ? $tipo : 'fee',
            'concept'    => $concepto,
            'amount'     => $importe,
            // La moneda se copia del expediente, no se elige: un expediente con dos
            // monedas no se puede sumar.
            'currency'   => (string) ($matter['currency'] ?: 'USD'),
            'due_at'     => $this->fecha('due_date'),
            'sort'       => 100,
            'note'       => $this->str('note', 255) ?: null,
        ]);

        (new MatterService())->refreshTotals((int) $matter['id']);

        Timeline::log((int) $matter['company_id'], (int) $lead['id'], 'fee',
            lang('Lfl.tlFeeAdded', [$concepto, $matter['number']]),
            ['matter' => (int) $matter['id']], $this->userId());

        $this->flash('success', lang('Lfl.okCreated'));

        return $this->back($this->volver((int) $matter['id']));
    }

    public function delete($id = null)
    {
        $cid = $this->companyId();
        $fee = $cid > 0 ? (new MatterFeeModel())->owned((int) $id, $cid) : null;
        if (! $fee) {
            return $this->notFound('leadflow/expedientes');
        }
        $par = $this->matter((int) $fee['matter_id'], true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }

        (new MatterFeeModel())->delete((int) $fee['id']);
        (new MatterService())->refreshTotals((int) $par['matter']['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return $this->back($this->volver((int) $par['matter']['id']));
    }

    /** Registra un pago recibido. */
    public function pay($matterId = null)
    {
        $par = $this->matter((int) $matterId, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$matter, $lead] = [$par['matter'], $par['lead']];

        $importe = (float) str_replace(',', '.', (string) $this->request->getPost('amount'));
        if ($importe <= 0) {
            $this->flash('error', lang('Lfl.vPaymentAmount'));

            return $this->back($this->volver((int) $matter['id']));
        }

        // La línea a la que se imputa, solo si es de ESTE expediente. Un `fee_id` de
        // otro caso llegado por el POST se descarta en vez de imputarse mal.
        $feeId = (int) $this->request->getPost('fee_id');
        $fee   = $feeId > 0 ? (new MatterFeeModel())->owned($feeId, (int) $matter['company_id']) : null;
        if ($fee && (int) $fee['matter_id'] !== (int) $matter['id']) {
            $fee = null;
        }

        $metodo = (string) $this->request->getPost('method');

        (new MatterPaymentModel())->insert([
            'company_id'  => (int) $matter['company_id'],
            'matter_id'   => (int) $matter['id'],
            'fee_id'      => $fee ? (int) $fee['id'] : null,
            'amount'      => $importe,
            'currency'    => (string) ($matter['currency'] ?: 'USD'),
            'paid_at'     => $this->fecha('paid_date') ?? date('Y-m-d H:i:s'),
            'method'      => in_array($metodo, MatterPaymentModel::METHODS, true) ? $metodo : 'transfer',
            'reference'   => $this->str('reference', 80) ?: null,
            'note'        => $this->str('note', 255) ?: null,
            'recorded_by' => $this->userId(),
        ]);

        (new MatterService())->refreshTotals((int) $matter['id']);

        Timeline::log((int) $matter['company_id'], (int) $lead['id'], 'payment',
            lang('Lfl.tlPaymentAdded', [
                \Components\LeadFlow\Libraries\Meta::money($importe, (string) ($matter['currency'] ?: 'USD')),
                $matter['number'],
            ]),
            ['matter' => (int) $matter['id']], $this->userId());

        $this->flash('success', lang('Lfl.okCreated'));

        return $this->back($this->volver((int) $matter['id']));
    }

    public function deletePayment($id = null)
    {
        $cid  = $this->companyId();
        $pago = $cid > 0 ? (new MatterPaymentModel())->owned((int) $id, $cid) : null;
        if (! $pago) {
            return $this->notFound('leadflow/expedientes');
        }
        $par = $this->matter((int) $pago['matter_id'], true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }

        (new MatterPaymentModel())->delete((int) $pago['id']);
        (new MatterService())->refreshTotals((int) $par['matter']['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return $this->back($this->volver((int) $par['matter']['id']));
    }

    /**
     * Estado de cuenta imprimible.
     *
     * Es GET y pide `lfl.view` porque no cambia nada: es la misma información de la
     * pestaña, maquetada para imprimir o guardar en PDF desde el navegador. No se
     * genera un PDF propio en esta fase: exigiría llevar una copia de `PdfService`
     * dentro del componente y no bloquea nada.
     */
    public function statement($id = null)
    {
        $par = $this->matter((int) $id);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$matter, $lead] = [$par['matter'], $par['lead']];
        $cid = (int) $matter['company_id'];

        return $this->render('matters/statement', [
            'title'    => lang('Lfl.mtrStatement'),
            'matter'   => $matter,
            'lead'     => $lead,
            'fees'     => (new MatterFeeModel())->forMatter((int) $matter['id'], $cid),
            'byKind'   => (new MatterFeeModel())->byKind((int) $matter['id'], $cid),
            'payments' => (new MatterPaymentModel())->forMatter((int) $matter['id'], $cid),
            'company'  => db_connect()->table('companies')->select('name')
                ->where('id', $cid)->get()->getRowArray() ?: [],
        ]);
    }

    /** Una fecha del POST en formato de BD, o null. */
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
