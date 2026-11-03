<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\Access;
use Components\LeadFlow\Libraries\Directory\ProfileService;
use Components\LeadFlow\Libraries\Directory\Taxonomy;
use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\AreaModel;
use Components\LeadFlow\Models\ComplaintModel;
use Components\LeadFlow\Models\JurisdictionModel;
use Components\LeadFlow\Models\MemberModel;
use Components\LeadFlow\Models\ProfileModel;

/**
 * Los perfiles de la firma en el directorio público.
 *
 * Es configuración de la firma (`lfl.configure`), no trabajo del día: se monta una
 * vez y se retoca. Publicar y despublicar son cambios de estado —`edit`—, y la baja
 * es `delete`, con el criterio de verbos del contrato.
 *
 * El perfil se busca siempre con `owned()`: el id llega por la URL y podría ser de
 * otra firma.
 */
class Profiles extends BaseLfl
{
    private function guard()
    {
        if (! $this->companyId()) {
            $this->flash('error', lang('Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow'));
        }
        Taxonomy::ensure();

        return null;
    }

    private function profile(int $id): ?array
    {
        return (new ProfileModel())->owned($id, $this->companyId());
    }

    /**
     * Los perfiles de la firma en el directorio público.
     *
     * ── Lista de perfiles, no de personas ───────────────────────────────────
     *
     * Lo que es de la PERSONA —su función comercial y su usuario de Docketwise— se
     * edita en el formulario del perfil. Eran dos controles por fila y convertían la
     * lista en una rejilla; aquí la función solo se lee, para que se vea de un vistazo
     * si el abogado publicado tiene la función que le corresponde.
     *
     * El vínculo perfil↔usuario sigue siendo 1:1 y obligatorio para `kind='lawyer'`
     * (`ProfileService::create()`), que es lo que hace que ambos datos puedan convivir
     * en la misma pantalla sin duplicarse.
     */
    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid  = $this->companyId();
        $plan = new PlanGuard();

        $profiles = (new ProfileModel())->forCompanyList($cid);

        // El perfil de la firma no es una persona: tiene su propio acceso en la cabecera.
        $firm = null;
        foreach ($profiles as $p) {
            if ($p['kind'] === 'firm' || empty($p['user_id'])) {
                $firm = $p;
                break;
            }
        }

        // Las cifras que justifican el plan, sumadas de los perfiles: cuántos están
        // publicados, cuántas visitas han tenido y cuántos prospectos han traído.
        $totales = ['published' => 0, 'views' => 0, 'leads' => 0];
        foreach ($profiles as $row) {
            $totales['published'] += $row['status'] === 'published' ? 1 : 0;
            $totales['views']     += (int) $row['views_count'];
            $totales['leads']     += (int) $row['leads_count'];
        }

        return $this->render('directory/index', [
            'title'        => lang('Lfl.navTeam'),
            'profiles'     => $profiles,
            'members'      => (new MemberModel())->mapFor($cid),
            'firmProfile'  => $firm,
            'totales'      => $totales,
            'isSuper'      => Access::isSuperAdmin((int) $this->userId()),
            'directoryOn'  => $plan->directoryEnabled($cid),
            'featured'     => $plan->featured($cid),
            'profilesLeft' => $plan->profilesLeft($cid),
            'publicBase'   => base_url('abogado/'),
            'canDelete'    => $this->can('lfl.delete'),
        ]);
    }

    public function create()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid = $this->companyId();

        if ($this->isPost()) {
            $res = (new ProfileService())->create($cid, $this->postData());
            $this->result($res);
            if ($res['ok']) {
                $this->saveMember($cid, $this->request->getPost('user_id'));
                return redirect()->to(base_url('leadflow/directorio/editar/' . $res['id']));
            }

            return redirect()->back()->withInput();
        }

        return $this->render('directory/form', $this->formData($cid) + [
            'profile' => null,
            'action'  => base_url('leadflow/directorio/nuevo'),
            'areas'   => [],
            'jurs'    => [],
        ]);
    }

    public function edit($id = null)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $profile = $this->profile((int) $id);
        if (! $profile) {
            return $this->notFound('leadflow/directorio');
        }

        if ($this->isPost()) {
            $res = (new ProfileService())->update($profile, $this->postData());
            $this->result($res);
            if ($res['ok']) {
                $this->saveMember((int) $profile['company_id'], $this->request->getPost('user_id'));
            }

            return redirect()->to(base_url('leadflow/directorio/editar/' . (int) $profile['id']));
        }

        $db = db_connect();

        return $this->render('directory/form', $this->formData((int) $profile['company_id']) + [
            'profile' => $profile,
            'action'  => base_url('leadflow/directorio/editar/' . (int) $profile['id']),
            'areas'   => array_map('intval', array_column($db->table('cmp_lfl_profile_areas')
                ->select('area_id')->where('profile_id', (int) $profile['id'])->get()->getResultArray(), 'area_id')),
            'jurs'    => array_map('intval', array_column($db->table('cmp_lfl_profile_jurisdictions')
                ->select('jurisdiction_id')->where('profile_id', (int) $profile['id'])->get()->getResultArray(), 'jurisdiction_id')),
        ]);
    }

    public function publish($id = null)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $profile = $this->profile((int) $id);
        if (! $profile) {
            return $this->notFound('leadflow/directorio');
        }

        $svc = new ProfileService();
        $this->result($profile['status'] === 'published' ? $svc->unpublish($profile) : $svc->publish($profile));

        return redirect()->to(base_url('leadflow/directorio'));
    }

    /**
     * La verificación de perfiles, para toda la plataforma.
     *
     * **Solo Super Admin**, como la app OAuth de Docketwise: la insignia de verificado es
     * una afirmación de la plataforma sobre una firma, así que tener `lfl.configure`
     * —que tiene el administrador de cada firma— no basta. Aquí se ven los perfiles
     * publicados de TODAS las firmas, y por eso la pantalla no filtra por empresa: es la
     * única del componente que mira por encima de ellas, igual que `cmp_lfl_providers`.
     */
    /**
     * Moderación: la cola de perfiles publicados de TODA la plataforma.
     *
     * ── Por qué trae las denuncias al lado ──────────────────────────────────
     *
     * Verificar es afirmar algo sobre un tercero. El dato que más debería pesar en esa
     * decisión es si alguien ha denunciado ese perfil, y hasta ahora vivía en otra
     * pantalla: había que acordarse de ir a mirarla. Aquí va como columna.
     *
     * Se trae solo el RECUENTO de denuncias pendientes, no su contenido: el texto lo
     * escribió un ciudadano sobre una firma y su lectura sigue siendo cosa del Super
     * Admin, en la otra pestaña. Un Verificador necesita saber que hay algo que mirar,
     * no leerlo.
     */
    public function platform()
    {
        if (! Access::canVerify((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow'));
        }
        Taxonomy::ensure();

        $profiles = (new ProfileModel())->select('cmp_lfl_profiles.*, c.name AS company_name, c.slug AS company_slug')
            ->join('companies c', 'c.id = cmp_lfl_profiles.company_id')
            ->where('cmp_lfl_profiles.status', 'published')
            // Sin verificar primero: es la cola de trabajo de quien revisa.
            ->orderBy('cmp_lfl_profiles.verified_at', 'asc')
            ->orderBy('cmp_lfl_profiles.published_at', 'desc')
            ->findAll(200);

        /*
         * Denuncias pendientes por perfil, en UNA consulta agrupada y no una por fila.
         * Las que no señalan a un perfil concreto (son sobre la firma) no entran aquí:
         * se ven enteras en la bandeja.
         */
        $denuncias = [];
        $ids       = array_values(array_filter(array_column($profiles, 'id')));
        if ($ids !== []) {
            foreach ((new ComplaintModel())->select('profile_id, COUNT(*) AS n')
                ->whereIn('profile_id', $ids)
                ->where('status', ComplaintModel::PENDING)
                ->where('deleted_at', null)
                ->groupBy('profile_id')->findAll() as $d) {
                $denuncias[(int) $d['profile_id']] = (int) $d['n'];
            }
        }

        return $this->render('directory/platform', [
            'title'      => lang('Lfl.modTitle'),
            'profiles'   => $profiles,
            'complaints' => $denuncias,
            'isSuper'    => Access::isSuperAdmin((int) $this->userId()),
            'pending'    => (new ComplaintModel())->countPending(),
        ]);
    }

    /** Verifica o retira la verificación. Solo Super Admin, como la pantalla. */
    public function verify($id = null)
    {
        if (! Access::canVerify((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow'));
        }

        // Sin `owned()`: la verificación es de la plataforma y cruza empresas a propósito.
        $profile = (new ProfileModel())->find((int) $id);
        if (! $profile) {
            return $this->notFound('leadflow/moderacion');
        }

        $this->result((new ProfileService())->verify($profile, $this->userId(), empty($profile['verified_at'])));

        return redirect()->to(base_url('leadflow/moderacion'));
    }

    public function delete($id = null)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $profile = $this->profile((int) $id);
        if (! $profile) {
            return $this->notFound('leadflow/directorio');
        }
        $this->result((new ProfileService())->delete($profile));

        return redirect()->to(base_url('leadflow/directorio'));
    }

    /** Lo que necesitan los selectores del formulario. */
    /**
     * Guarda la mitad del registro que vive en `cmp_lfl_members`.
     *
     * Va aquí y no en `ProfileService` porque es OTRA tabla con otra semántica: el
     * perfil es la ficha pública, el miembro es qué leads ve esa persona. Lo que las
     * une es el usuario, y por eso se editan juntos; mezclarlas en el mismo servicio
     * haría que publicar un perfil pudiera cambiar permisos de visibilidad.
     *
     * Si el perfil no apunta a un usuario —el de la firma— no hay nada que guardar.
     */
    private function saveMember(int $companyId, $userId): void
    {
        $uid = (int) $userId;
        if ($uid <= 0 || ! Team::isMember($companyId, $uid)) {
            return;
        }

        $rol = (string) $this->request->getPost('member_role');
        $rol = in_array($rol, MemberModel::ROLES, true) ? $rol : null;

        (new MemberModel())->setFor($companyId, $uid, $rol, $this->str('docketwise_user_id', 40) ?: null);

        // `Access` cachea el alcance por petición: sin esto, el usuario seguiría viendo
        // lo de antes hasta la siguiente carga.
        Access::flush();
    }
    private function formData(int $cid): array
    {
        return [
            'team'     => Team::users($cid),
            // La función comercial y el usuario de Docketwise son de la PERSONA, no
            // del perfil, pero se editan aquí porque el vínculo perfil↔usuario es 1:1
            // y obligatorio: tenerlos en dos pantallas distintas ya había descuadrado
            // los datos una vez.
            'members'  => (new MemberModel())->mapFor($cid),
            'dwUsers'  => (new IntegrationService())->lists($cid)['users'],
            'roles'    => \Components\LeadFlow\Libraries\Meta::options(\Components\LeadFlow\Libraries\Meta::MEMBER_ROLES),
            'allAreas' => (new AreaModel())->active(),
            'allJurs'  => (new JurisdictionModel())->active(),
            'modes'    => ProfileModel::MODES,
        ];
    }

    /** Lo que llega del formulario; el servicio decide qué de esto es válido. */
    private function postData(): array
    {
        $r = $this->request;

        return [
            'kind'             => (string) $r->getPost('kind'),
            'user_id'          => (int) $r->getPost('user_id'),
            'display_name'     => (string) $r->getPost('display_name'),
            'headline'         => (string) $r->getPost('headline'),
            'bio'              => (string) $r->getPost('bio'),
            'email'            => (string) $r->getPost('email'),
            'phone'            => (string) $r->getPost('phone'),
            'whatsapp'         => (string) $r->getPost('whatsapp'),
            'website'          => (string) $r->getPost('website'),
            'city'             => (string) $r->getPost('city'),
            'languages'        => (string) $r->getPost('languages'),
            'consult_modes'    => (array) ($r->getPost('consult_modes') ?? []),
            'experience_years' => (int) $r->getPost('experience_years'),
            'bar_number'       => (string) $r->getPost('bar_number'),
            'bar_state'        => (string) $r->getPost('bar_state'),
            'bar_url'          => (string) $r->getPost('bar_url'),
            'seo_description'  => (string) $r->getPost('seo_description'),
            'areas'            => (array) ($r->getPost('areas') ?? []),
            'jurisdictions'    => (array) ($r->getPost('jurisdictions') ?? []),
            'renew_slug'       => (bool) $r->getPost('renew_slug'),
        ];
    }
}
