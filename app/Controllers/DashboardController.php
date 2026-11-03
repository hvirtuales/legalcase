<?php

namespace App\Controllers;

use App\Models\UserModel;

class DashboardController extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Dashboard home.
     *
     * Con una empresa activa muestra el **Panel Empresa**: qué le da su plan
     * (servicios y cupos) y cómo va de vigencia. En el contexto Global (solo
     * Super Admin) se conserva el panel de siempre, que habla del usuario.
     */
    public function index()
    {
        $userId = session()->get('user_id');

        if (!$userId) {
            return redirect()->to('/login');
        }

        // Get user with roles
        $user = $this->userModel->getUserWithRoles($userId);

        // Get user permissions
        $permissions = $this->userModel->getUserPermissions($userId);

        // Trabajando dentro de un grupo: el panel que toca es el del tenant.
        if (session()->get('active_tenant_id')) {
            return redirect()->to('/tenant');
        }

        $companyId = (int) (session()->get('active_company_id') ?? 0);
        if ($companyId) {
            return $this->company($companyId, $user);
        }

        $data = [
            'user' => $user,
            'permissions' => $permissions,
            'title' => lang('App.dashboard'),
        ];

        return view('dashboard/index', $data);
    }

    /** Panel Empresa: plan, vigencia, servicios con sus cupos y accesos. */
    private function company(int $companyId, array $user)
    {
        $company = (new \App\Models\CompanyModel())->find($companyId);
        if (!$company) {
            return view('dashboard/index', [
                'user' => $user, 'permissions' => [], 'title' => lang('App.dashboard'),
            ]);
        }

        $subModel = new \App\Models\SubscriptionModel();
        $sub      = $subModel->markExpiredIfNeeded($subModel->getMapForCompanies([$companyId])[$companyId] ?? null);
        $plan     = !empty($company['plan_id']) ? (new \App\Models\PlanModel())->find((int) $company['plan_id']) : null;

        // Días que le quedan al servicio (negativo = ya venció).
        $daysLeft = null;
        if ($sub && !empty($sub['end_date'])) {
            $daysLeft = (int) floor((strtotime($sub['end_date']) - strtotime('today')) / 86400);
        }

        return view('dashboard/company', [
            'title'     => lang('App.companyPanel'),
            'user'      => $user,
            'company'   => $company,
            'plan'      => $plan,
            'sub'       => $sub,
            'valid'     => $subModel->isValid($sub),
            'daysLeft'  => $daysLeft,
            'services'  => (new \App\Libraries\ComponentManager())->planStatus(),
            'members'   => (new \App\Models\CompanyUserModel())->where('company_id', $companyId)->countAllResults(),
            'isManager' => (new \App\Models\CompanyModel())->isManager($companyId, (int) $user['id'])
                || in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true),
        ]);
    }
}
