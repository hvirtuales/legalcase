<?php

namespace Components\Networking\Controllers;

use App\Controllers\BaseController;
use Components\Networking\Libraries\NetworkService;
use Components\Networking\Libraries\PlanGuard;
use Components\Networking\Models\ProfileModel;

/** «Mi perfil de red» (el check de compartir) y la ficha pública de otro usuario. */
class Profiles extends BaseController
{
    private NetworkService $net;
    private ProfileModel $profiles;

    public function __construct()
    {
        $this->net      = new NetworkService();
        $this->profiles = new ProfileModel();
    }

    /** GET/POST networking/mi-perfil */
    public function mine()
    {
        $me = (int) session()->get('user_id');

        if ($this->request->getMethod() === 'POST') {
            // La empresa elegida debe ser una a la que el usuario pertenezca.
            $companyId = (int) $this->request->getPost('company_id');
            $allowed   = array_map(static fn ($c) => (int) $c['id'], $this->net->companiesOf($me));
            if (!in_array($companyId, $allowed, true)) {
                $companyId = $allowed[0] ?? null;
            }

            $data = [
                'user_id'       => $me,
                'company_id'    => $companyId,
                'share_profile' => $this->request->getPost('share_profile') ? 1 : 0,
                'position'      => trim((string) $this->request->getPost('position')),
                'branch'        => trim((string) $this->request->getPost('branch')),
                'bio'           => trim((string) $this->request->getPost('bio')),
                'interests'     => trim((string) $this->request->getPost('interests')),
                'show_email'    => $this->request->getPost('show_email') ? 1 : 0,
                'show_phone'    => $this->request->getPost('show_phone') ? 1 : 0,
            ];

            $existing = $this->profiles->forUser($me);
            $ok       = $existing
                ? $this->profiles->update($existing['id'], $data)
                : $this->profiles->insert($data);

            if (!$ok) {
                return redirect()->back()->withInput()
                    ->with('error', implode(' ', $this->profiles->errors()));
            }

            return redirect()->to('networking/mi-perfil')->with('success', lang('Net.profileSaved'));
        }

        $profile   = $this->net->profileOf($me);
        $companies = $this->net->companiesOf($me);
        $company   = $this->net->companyOf($me, $profile);

        return view('Components\Networking\Views\my_profile', [
            'title'       => lang('Net.myProfile'),
            'profile'     => $profile,
            'user'        => $this->net->userOf($me),
            'companies'   => $companies,
            'card'        => $this->net->card($me, $profile),
            // ¿puede además retocar la marca (logo/banner) de la empresa mostrada?
            'canEditCompany' => $company
                && (new \App\Models\CompanyModel())->isManager((int) $company['id'], $me),
            'counts'      => $this->net->counts($me),
            'net'         => $this->net,
            'me'          => $me,
            'planEnabled' => (new PlanGuard())->serviceEnabled(),
        ]);
    }

    /** GET networking/perfil/{userId} — ficha pública. */
    public function show($userId = null)
    {
        $me      = (int) session()->get('user_id');
        $userId  = (int) $userId;
        $user    = $this->net->userOf($userId);
        $profile = $this->profiles->forUser($userId);

        // Solo visible si comparte su perfil, o si ya están conectados, o es el propio.
        $visible = $user && !empty($user['is_active'])
            && ($userId === $me || $this->net->isConnected($me, $userId) || ($profile && !empty($profile['share_profile'])));

        if (!$visible) {
            return redirect()->to('networking/descubrir')->with('error', lang('Net.profileUnavailable'));
        }

        return view('Components\Networking\Views\profile', [
            'title'    => $this->net->displayName($user),
            'user'     => $user,
            'profile'  => $this->net->profileOf($userId),
            'card'     => $this->net->card($userId),
            'relation' => $this->net->relation($me, $userId),
            'counts'   => $this->net->counts($me),
            'net'      => $this->net,
            'me'       => $me,
        ]);
    }
}
