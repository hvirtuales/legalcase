<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\Uscis\UscisService;
use Components\LeadFlow\Models\CaseStatusModel;
use Components\LeadFlow\Models\LeadModel;

/**
 * El módulo USCIS de la firma: consultas internas, consumo y enlaces.
 *
 * Existe porque el panel del lead solo sirve cuando ya hay un lead. El administrador
 * necesita poder consultar un recibo suelto —alguien llama por teléfono, llega un
 * correo, hay que comprobar algo antes de dar de alta nada— y hasta ahora la única
 * forma era inventarse un lead para colgarle el recibo.
 *
 * También es donde se ve **cuánto cupo queda**, que es el dato que de verdad gobierna
 * este servicio, y de donde se copia el enlace que la firma le pasa a sus clientes.
 */
class Uscis extends BaseLfl
{
    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            $this->flash('error', lang('Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow'));
        }

        return $this->render('uscis/index', $this->data($cid));
    }

    /**
     * Consulta interna de un recibo.
     *
     * **Gasta cupo**, igual que la pública: por eso es un POST y no algo que ocurra al
     * cargar la pantalla. El resultado va en flash y lo pinta la misma vista, como el
     * diagnóstico de la conexión: es la respuesta a una pregunta concreta, no un
     * estado que haya que guardar en ningún sitio.
     *
     * Pide `lfl.view` y no `lfl.edit`: consultar no cambia nada del lead. Si el recibo
     * resulta ser de uno, la vista enlaza a su ficha, pero no lo toca.
     */
    public function lookup()
    {
        $cid = $this->companyId();
        if (! $cid) {
            $this->flash('error', lang('Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow'));
        }

        $recibo = (string) $this->request->getPost('receipt');
        session()->setFlashdata('lfl_case', (new UscisService())->status($cid, $recibo) + ['receipt' => $recibo]);

        return redirect()->to(base_url('leadflow/uscis'));
    }

    /**
     * Lo que la pantalla necesita.
     *
     * Los últimos casos salen de la caché de la firma, que es lo que ya se consultó:
     * **no se vuelve a preguntar a USCIS para pintar una lista**. Una pantalla que
     * refrescara veinte casos al abrirse se comería el cupo del día cada vez que
     * alguien pasa por ella.
     */
    private function data(int $cid): array
    {
        $svc    = new UscisService();
        $cfg    = $svc->config($cid);
        $cache  = new CaseStatusModel();
        $recent = $cache->forCompany($cid)->orderBy('checked_at', 'desc')->findAll(20);

        // Si un recibo de la caché es de un lead, se enlaza. Una consulta en bucle por
        // fila sería una consulta por caso: se piden todos de una vez.
        $porRecibo = [];
        $recibos   = array_values(array_filter(array_column($recent, 'receipt')));
        if ($recibos !== []) {
            foreach ((new LeadModel())->select('id, first_name, last_name, uscis_receipt')
                ->where('company_id', $cid)->whereIn('uscis_receipt', $recibos)
                ->where('deleted_at', null)->findAll() as $l) {
                $porRecibo[(string) $l['uscis_receipt']] = $l;
            }
        }

        return [
            'title'     => lang('Lfl.uscModuleTitle'),
            'cfg'       => $cfg,
            'quota'     => $svc->quota($cid),
            'recent'    => $recent,
            'leadsBy'   => $porRecibo,
            'publicUrl' => $cfg['mode'] === 'live' && $cfg['configured'] && ! empty($this->company()['slug'])
                ? base_url('firma/' . rawurlencode((string) $this->company()['slug']) . '/caso')
                : null,
            'result'    => session()->getFlashdata('lfl_case'),
            'canConfigure' => $this->can('lfl.configure'),
        ];
    }

    /** La fila de la empresa activa, para su slug público. */
    private function company(): array
    {
        return db_connect()->table('companies')->select('id, name, slug')
            ->where('id', (int) $this->companyId())->get()->getRowArray() ?: [];
    }
}
