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

        return $this->{$method}(...$params);
    }

    /** El asunto si es de la empresa y el usuario lo puede ver; si no, null. */
    protected function findMatter($id): ?array
    {
        $m = (new MatterModel())->own($this->companyId(), (int) $id);

        return $m && $this->acc()->canSeeMatter($m) ? $m : null;
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
        return redirect()->to($to)->with('error', esc(lang('Lgc.errNotFound')));
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
