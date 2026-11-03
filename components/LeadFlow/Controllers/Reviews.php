<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\DemoData;
use Components\LeadFlow\Libraries\Access;
use Components\LeadFlow\Libraries\Directory\ReviewService;
use Components\LeadFlow\Models\ReviewModel;

/**
 * Las opiniones dentro del panel: pedirlas, responderlas y —solo la plataforma—
 * moderarlas.
 *
 * Los verbos siguen el criterio del contrato: ver la lista es `view`, **pedir una opinión
 * es `create`** (se da de alta una invitación), responder es `edit` y moderar es de la
 * plataforma, con `configure` más la comprobación de Super Admin, igual que la
 * verificación de perfiles y la app OAuth de Docketwise.
 *
 * Una firma nunca puede borrar ni editar una opinión: no hay ruta para eso, a propósito.
 */
class Reviews extends BaseLfl
{
    private function guard()
    {
        if (! $this->companyId()) {
            $this->flash('error', lang('Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow'));
        }

        return null;
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid = $this->companyId();
        $svc = new ReviewService();

        return $this->render('reviews/index', [
            'title'      => lang('Lfl.navReviews'),
            'reviews'    => (new ReviewModel())->forCompanyList($cid),
            'invitable'  => $svc->invitableLeads($cid),
            'enabled'    => $svc->enabledFor($cid),
            'isSuper'    => Access::isSuperAdmin((int) $this->userId()),
            'canInvite'  => $this->can('lfl.create') && $this->access()->canWrite,
            'canReply'   => $this->can('lfl.edit') && $this->access()->canWrite,
            // Para el botón de datos de ejemplo del estado vacío.
            'canConfigure' => $this->can('lfl.configure'),
        ]);
    }

    /** Crea la invitación de un lead convertido y deja el enlace a la vista. */
    public function invite($leadId = null)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound('leadflow/opiniones');
        }

        $res = (new ReviewService())->invite($lead);
        $this->result($res);
        if ($res['ok'] && ! empty($res['url'])) {
            // El enlace va en flash y no en la URL: es el que se le pasa al cliente.
            session()->setFlashdata('lfl_review_url', $res['url']);
        }

        return redirect()->to(base_url('leadflow/opiniones'));
    }

    public function reply($id = null)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $review = (new ReviewModel())->owned((int) $id, $this->companyId());
        if (! $review) {
            return $this->notFound('leadflow/opiniones');
        }

        $this->result((new ReviewService())->reply($review, (string) $this->request->getPost('reply')));

        return redirect()->to(base_url('leadflow/opiniones'));
    }

    /**
     * La cola de moderación de toda la plataforma. **Solo Super Admin**: publicar una
     * opinión es una afirmación de la plataforma sobre una firma.
     */
    /**
     * Siembra opiniones de ejemplo para ver el módulo funcionando.
     *
     * Pide `lfl.configure` y no `lfl.create`: sembrar datos de ejemplo es una acción de
     * puesta en marcha, no el trabajo diario de pedir una opinión a un cliente.
     */
    public function demo()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound();
        }

        $res = (new DemoData())->runReviews($cid, $this->userId());
        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);

        return redirect()->to(base_url('leadflow/opiniones'));
    }
    public function platform()
    {
        if (! Access::isSuperAdmin((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow/opiniones'));
        }

        $reviews = (new ReviewModel())
            ->select('cmp_lfl_reviews.*, c.name AS company_name, p.slug AS profile_slug, p.display_name AS profile_name')
            ->join('companies c', 'c.id = cmp_lfl_reviews.company_id')
            ->join('cmp_lfl_profiles p', 'p.id = cmp_lfl_reviews.profile_id', 'left')
            // Solo las contestadas: una invitación sin notas no es una opinión. Y las
            // pendientes primero, que es la cola de trabajo.
            ->where('cmp_lfl_reviews.score IS NOT NULL', null, false)
            ->orderBy('cmp_lfl_reviews.status', 'asc')
            ->orderBy('cmp_lfl_reviews.created_at', 'desc')
            ->findAll(200);

        return $this->render('reviews/platform', [
            'title'   => lang('Lfl.revModerationTitle'),
            'reviews' => $reviews,
        ]);
    }

    /** Publica o rechaza. Solo Super Admin, como la pantalla. */
    public function moderate($id = null)
    {
        if (! Access::isSuperAdmin((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow/opiniones'));
        }

        // Sin `owned()`: moderar cruza empresas a propósito.
        $review = (new ReviewModel())->find((int) $id);
        if (! $review) {
            return $this->notFound('leadflow/opiniones/plataforma');
        }

        $approve = $this->request->getPost('decision') === 'publish';
        $this->result((new ReviewService())->moderate($review, $approve, $this->userId(),
            (string) $this->request->getPost('note')));

        return redirect()->to(base_url('leadflow/opiniones/plataforma'));
    }
}
