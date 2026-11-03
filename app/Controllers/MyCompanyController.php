<?php

namespace App\Controllers;

use App\Models\CompanyModel;
use App\Models\UserModel;
use App\Traits\ManagesOwnCompany;

/**
 * "Mi empresa": el dueño o admin (is_admin) de la empresa activa edita sus
 * **Datos** (nombre, NIT, contacto, marca) y sus **Canales** de comunicación
 * (SMTP / WhatsApp / SMS, secretos cifrados). El Super Admin también entra.
 * No permite cambiar plan/owner/estado (eso es del panel global de Empresas).
 *
 * El trabajo lo hace `ManagesOwnCompany`, compartido con el **Panel del Tenant**
 * (`/tenant/datos`): un grupo es también una fila de `companies`, así que su
 * administrador edita exactamente la misma ficha. Aquí solo se resuelve **de qué
 * fila se habla** y a dónde se vuelve.
 */
class MyCompanyController extends BaseController
{
    use ManagesOwnCompany;

    private CompanyModel $companies;

    public function __construct()
    {
        $this->companies = new CompanyModel();
    }

    /** Prefijo de URL de estas pantallas (lo usan las vistas compartidas). */
    private function profileBase(): string
    {
        return 'mi-empresa';
    }

    /** Devuelve la empresa activa si el usuario puede gestionarla; null si no. */
    private function manageable(): ?array
    {
        $uid = (int) session()->get('user_id');
        $cid = (int) (session()->get('active_company_id') ?: 0);
        if (!$uid || !$cid) {
            return null;
        }
        $company = $this->companies->find($cid);
        if (!$company) {
            return null;
        }
        $user    = (new UserModel())->getUserWithRoles($uid);
        $isSuper = in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);

        return ($isSuper || $this->companies->isManager($cid, $uid)) ? $company : null;
    }

    // ------------------------------------------------------------------ datos

    public function datos()
    {
        $company = $this->manageable();
        if (!$company) {
            return redirect()->to('/dashboard')->with('error', 'Selecciona una empresa que administres para editar sus datos.');
        }

        if ($this->request->getMethod() === 'POST') {
            $errors = $this->saveProfile((int) $company['id']);
            if ($errors) {
                return redirect()->back()->withInput()->with('errors', $errors);
            }

            return redirect()->to('/mi-empresa')->with('success', 'Datos de la empresa actualizados.');
        }

        return view('my_company/datos', [
            'title'   => 'Mi empresa',
            'heading' => 'Mi empresa',
            'base'    => $this->profileBase(),
            'company' => $company,
            'tab'     => 'datos',
        ]);
    }

    // ---------------------------------------------------------------- canales

    public function channels()
    {
        $company = $this->manageable();
        if (!$company) {
            return redirect()->to('/dashboard')->with('error', 'Selecciona una empresa que administres.');
        }
        if ($this->request->getMethod() === 'POST') {
            $this->saveChannelsFor((int) $company['id']);

            return redirect()->to('/mi-empresa/canales')->with('success', 'Canales de comunicación guardados.');
        }

        [$cfg, $secret] = $this->channelConfig((int) $company['id']);

        return view('my_company/channels', [
            'title'     => 'Canales — ' . $company['name'],
            'heading'   => 'Mi empresa',
            'base'      => $this->profileBase(),
            'company'   => $company,
            'config'    => $cfg,
            'secretSet' => $secret,
            'tab'       => 'canales',
        ]);
    }

    public function testChannel($service = null)
    {
        $company = $this->manageable();
        if (!$company) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'No autorizado.', 'csrf' => csrf_hash()]);
        }

        $res         = $this->probeChannel((int) $company['id'], $service);
        $res['csrf'] = csrf_hash();

        return $this->response->setJSON($res);
    }
}
