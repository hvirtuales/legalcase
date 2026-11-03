<?php

namespace Components\LegalCase\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Components\LegalCase\Libraries\Access;
use Components\LegalCase\Libraries\PlanGuard;
use Components\LegalCase\Libraries\Result;
use Components\LegalCase\Models\MatterModel;

/**
 * Base de los controladores internos (personal del despacho).
 *
 * Delgada a propósito: resuelve el contexto (empresa activa + usuario), hace valer el
 * plan en TODAS las acciones (`_remap`) y ofrece las dos resoluciones que evitan el
 * IDOR —`matterOr()` y `childOr()`—, que devuelven el registro solo si es de la empresa
 * activa y el usuario puede ver su asunto.
 */
abstract class BaseLgc extends BaseController
{
    protected ?Access $acc = null;

    protected function acc(): Access
    {
        return $this->acc ??= Access::current();
    }

    protected function companyId(): int
    {
        return $this->acc()->companyId;
    }

    /**
     * Toda acción pasa por aquí: sin empresa activa o sin el servicio en el plan no se
     * ejecuta nada, ni por GET ni por un POST directo.
     */
    public function _remap(string $method, ...$params)
    {
        if (!method_exists($this, $method) || str_starts_with($method, '_')) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if ($this->companyId() <= 0) {
            return view('Components\LegalCase\Views\no_company', ['title' => lang('Lgc.compName')]);
        }
        if (!(new PlanGuard())->serviceEnabled($this->companyId())) {
            return redirect()->to('dashboard')->with('error', esc(lang('Lgc.errPlanDisabled')));
        }
        // Ni el Super Admin ni el distribuidor leen datos jurídicos sin un acceso de soporte
        // concedido por el propio despacho (documento maestro §3).
        if (!$this->acc()->hasCompanyAccess()) {
            return view('Components\LegalCase\Views\no_access', ['title' => lang('Lgc.compName')]);
        }
        if ($this->acc()->isSupport()) {
            $grant = $this->acc()->supportGrant();
            $key   = 'lgc_support_logged_' . (int) $grant['id'];
            if (!session($key)) {
                session()->set($key, 1);
                \Components\LegalCase\Libraries\Audit::log($this->companyId(), null, 'support.access', 'grant', (int) $grant['id']);
            }
            // El soporte consulta; no modifica nada.
            if ($this->isPost()) {
                return redirect()->back()->with('error', esc(lang('Lgc.errSupportReadOnly')));
            }
        }

        return $this->{$method}(...$params);
    }

    /** Motivo del último rechazo de findMatter(): 'none' | 'team'. */
    protected string $deny = 'none';

    /**
     * El asunto si es de la empresa y el usuario lo puede ver. En un POST además debe poder
     * TRABAJARLO (responsable, equipo no observador o administrador): consultar un asunto del
     * despacho no autoriza a actuar sobre él.
     */
    protected function findMatter($id): ?array
    {
        $this->deny = 'none';
        $m = (new MatterModel())->own($this->companyId(), (int) $id);
        if (!$m || !$this->acc()->canSeeMatter($m)) {
            return null;
        }
        if ($this->isPost() && !$this->acc()->canWorkOn($m)) {
            $this->deny = 'team';

            return null;
        }

        return $m;
    }

    /**
     * Un registro hijo (proceso, plazo, documento…) por id, acotado a la empresa y a un
     * asunto visible. Devuelve [registro, asunto] o [null, null].
     *
     * @param class-string<\Components\LegalCase\Models\BaseLgcModel> $modelClass
     */
    protected function findChild(string $modelClass, $id): array
    {
        $row = (new $modelClass())->own($this->companyId(), (int) $id);
        if (!$row) {
            return [null, null];
        }
        $matter = $this->findMatter((int) $row['matter_id']);

        return $matter ? [$row, $matter] : [null, null];
    }

    protected function notFound(string $to = 'legalcase/asuntos'): RedirectResponse
    {
        if ($this->deny === 'team') {
            return redirect()->back()->with('error', esc(lang('Lgc.errNotTeam')));
        }

        return redirect()->to($to)->with('error', esc(lang('Lgc.errNotFound')));
    }

    /** Traduce un fallo con conflictos o pendientes a la redirección con sus datos en flash. */
    protected function failWithData(\Components\LegalCase\Libraries\Result $r, string $back): RedirectResponse
    {
        $red = redirect()->to($back)->withInput()->with('error', esc($r->message));
        if (!empty($r->data['conflicts'])) {
            $red = $red->with('lgc_conflicts', $r->data['conflicts']);
        }
        if (!empty($r->data['pending'])) {
            $red = $red->with('lgc_pending', $r->data['pending']);
        }

        return $red;
    }

    /**
     * Traduce un Result a una redirección con aviso. El layout del núcleo pinta los
     * avisos SIN escapar, así que aquí se escapan siempre (pueden llevar datos del usuario).
     */
    protected function done(Result $r, string $to, ?string $toOnFail = null): RedirectResponse
    {
        if ($r->ok) {
            return redirect()->to($to)->with('success', esc($r->message));
        }

        return redirect()->to($toOnFail ?? $to)->withInput()->with('error', esc($r->message));
    }

    protected function matterUrl(array $matter, string $tab = ''): string
    {
        return 'legalcase/asuntos/ficha/' . (int) $matter['id'] . ($tab !== '' ? '?tab=' . $tab : '');
    }

    protected function isPost(): bool
    {
        return strtolower($this->request->getMethod()) === 'post';
    }

    protected function post(string $key, $default = '')
    {
        $v = $this->request->getPost($key);

        return $v === null ? $default : $v;
    }
}
