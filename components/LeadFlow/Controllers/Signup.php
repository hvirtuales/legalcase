<?php

namespace Components\LeadFlow\Controllers;

use App\Controllers\BaseController;
use Components\LeadFlow\Libraries\Captcha;
use Components\LeadFlow\Libraries\Directory\SignupService;
use Components\LeadFlow\Libraries\Directory\Taxonomy;

/**
 * «Haz parte de Leads Lawyers»: el alta pública de un abogado o de una firma.
 *
 * Sin `auth` —quien se registra todavía no tiene cuenta— y con todas las defensas de un
 * POST público que crea **cuentas**, que es más caro que crear un lead:
 *
 *  · CSRF, que el núcleo aplica a todo salvo `api/v1/*`;
 *  · **honeypot** invisible, y cuando se llena se responde como si todo hubiera ido bien
 *    sin crear nada: decirle al robot que se le ha pillado solo le enseña a esquivarlo;
 *  · **sello de tiempo firmado** con la clave de la instalación — un formulario enviado
 *    en menos de tres segundos no lo rellenó una persona;
 *  · **freno por IP de 5 a la hora**, no por minuto, porque crear cuentas es caro;
 *  · **captcha**. Aquí se aparta del criterio de la tarjeta de Salud, que lo omite
 *    razonando que lo peor que logra un robot es una empresa vacía: esto además crea
 *    USUARIOS con contraseña, que es superficie de abuso de verdad, y el captcha ya
 *    estaba escrito en el componente.
 */
class Signup extends BaseController
{
    public function form()
    {
        Taxonomy::ensure();

        return view('Components\LeadFlow\Views\public\directory\signup', [
            'pageTitle'   => lang('Lfl.suTitle') . ' · ' . lang('Lfl.dirBrand'),
            // Sí se indexa: es la página que tiene que encontrar un abogado que busque
            // cómo publicar su perfil.
            'robots'      => 'index, follow',
            'canonical'   => base_url('registro-legal'),
            'description' => lang('Lfl.suMeta'),
            'stamp'       => (new Captcha())->stamp(),
            'hasGd'       => extension_loaded('gd'),
            'kind'        => in_array($this->request->getGet('tipo'), SignupService::KINDS, true)
                ? (string) $this->request->getGet('tipo') : 'lawyer',
        ]);
    }

    public function submit()
    {
        $r = $this->request;

        // Honeypot: se contesta como si todo hubiera ido bien, sin crear nada.
        if (trim((string) $r->getPost('website')) !== '') {
            return redirect()->to(base_url('registro-legal/listo'));
        }

        $captcha = new Captcha();
        if (! $captcha->humanSpeed((string) $r->getPost('stamp'))) {
            return redirect()->back()->withInput()->with('dir_error', lang('Lfl.pubTooFast'));
        }
        if (extension_loaded('gd') && ! $captcha->check((string) $r->getPost('captcha'))) {
            return redirect()->back()->withInput()->with('dir_error', lang('Lfl.pubCaptchaError'));
        }

        // La IP va HASHEADA en la clave: el throttler la valida y una IPv6 lleva dos
        // puntos, que son caracteres reservados — daría un 500 antes de validar nada.
        if (! service('throttler')->check('lfl_signup_' . md5((string) $r->getIPAddress()), SignupService::RATE, HOUR)) {
            return redirect()->back()->withInput()->with('dir_error', lang('Lfl.pubTooMany'));
        }

        $res = (new SignupService())->register([
            'kind'             => (string) $r->getPost('kind'),
            'first_name'       => (string) $r->getPost('first_name'),
            'last_name'        => (string) $r->getPost('last_name'),
            'email'            => (string) $r->getPost('email'),
            'phone'            => (string) $r->getPost('phone'),
            'company_name'     => (string) $r->getPost('company_name'),
            'password'         => (string) $r->getPost('password'),
            'password_confirm' => (string) $r->getPost('password_confirm'),
        ], (string) $r->getIPAddress());

        if (! $res['ok']) {
            return redirect()->back()->withInput()->with('dir_error', $res['message']);
        }

        // Se le manda a entrar con su usuario ya puesto: `completeLogin()` del núcleo es
        // privado y resuelve además 2FA, rol activo, idioma y bitácora.
        return redirect()->to(base_url('registro-legal/listo') . '?u=' . rawurlencode($res['username']));
    }

    /** «Ya está»: qué hacer ahora. */
    public function done()
    {
        return view('Components\LeadFlow\Views\public\directory\signup_done', [
            'pageTitle' => lang('Lfl.suDoneTitle') . ' · ' . lang('Lfl.dirBrand'),
            // Una confirmación no se indexa: no tiene contenido propio y aparecería en
            // los resultados por delante del formulario.
            'robots'    => 'noindex, follow',
            'username'  => mb_substr((string) ($this->request->getGet('u') ?? ''), 0, 60),
        ]);
    }

    /** La imagen del captcha, igual que en el formulario de consulta. */
    public function captcha()
    {
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }
}
