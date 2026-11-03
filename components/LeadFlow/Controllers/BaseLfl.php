<?php

namespace Components\LeadFlow\Controllers;

use App\Controllers\BaseController;
use Components\LeadFlow\Libraries\Access;
use Components\LeadFlow\Libraries\Catalog;
use Components\LeadFlow\Libraries\LeadService;
use Components\LeadFlow\Models\MatterModel;

/**
 * Base de los controladores internos del componente.
 *
 * Lo que garantiza y no se puede olvidar en una subclase: la empresa sale de la
 * SESIÓN, nunca del POST; un lead se busca siempre con `lead()`, que comprueba
 * empresa **y** visibilidad de la función comercial (y escritura si se pide); y
 * los mensajes flash se escapan, porque el layout del núcleo los pinta en crudo.
 */
abstract class BaseLfl extends BaseController
{
    protected function companyId(): int
    {
        return (int) (session('active_company_id') ?? 0);
    }

    protected function userId(): ?int
    {
        return (int) (session('user_id') ?? 0) ?: null;
    }

    protected function access(): Access
    {
        return Access::current();
    }

    /**
     * Título del encabezado del layout por sección. El layout del núcleo pinta
     * `$title` (por defecto «Dashboard»): sin esto, todas las pantallas del
     * componente se llamarían igual.
     */
    private const TITLES = [
        'dashboard' => 'navHome', 'reports' => 'navReports', 'leads' => 'navLeads', 'pipeline' => 'navPipeline',
        'activities' => 'navActivities', 'appointments' => 'navAppts', 'forms' => 'navForms', 'marketing' => 'navMarketing',
        'settings' => 'navSettings', 'docketwise' => 'navDocketwise', 'conversions' => 'convertToClient',
        'matters' => 'navMatters', 'directory' => 'navTeam',
    ];

    protected function render(string $view, array $data = [])
    {
        $cid = $this->companyId();
        if ($cid) {
            Catalog::ensure($cid);
        }
        $data['title'] ??= lang('Lfl.' . (self::TITLES[strtok($view, '/')] ?? 'compName'));

        return view('Components\LeadFlow\Views\\' . $view, $data + ['access' => $this->access(), 'companyId' => $cid]);
    }

    /** ¿Tiene el usuario este permiso? (para pintar o no un botón; la ruta ya lo exige). */
    protected function can(string $perm): bool
    {
        $uid = $this->userId();

        return $uid !== null && (new \App\Models\UserModel())->hasPermission($uid, $perm);
    }

    /** El lead, si es de la empresa activa, visible para el usuario y —si se pide— editable. */
    protected function lead(int $id, bool $write = false): ?array
    {
        $a    = $this->access();
        $lead = (new LeadService())->visible($id, $a);
        if (! $lead || ($write && ! $a->canWrite)) {
            return null;
        }

        return $lead;
    }

    /**
     * El expediente y su lead, o null. La puerta única de todo lo que toca un caso.
     *
     * **El expediente no tiene alcance propio: lo hereda del lead.** Se comprueba la
     * empresa del expediente y, encima, que el lead de origen sea visible para quien
     * pregunta. Así un abogado sigue viendo solo los casos de sus leads sin que haya
     * que escribir una segunda versión de `Access::scope()` para esta tabla.
     *
     * Devuelve las dos filas juntas porque casi todo lo que viene después necesita
     * ambas —la línea de tiempo se escribe en el lead, no en el expediente— y buscarlas
     * por separado invita a que una de las dos se quede sin comprobar.
     *
     * @return array{matter: array, lead: array}|null
     */
    protected function matter(int $id, bool $write = false): ?array
    {
        $cid = $this->companyId();
        if ($cid <= 0 || $id <= 0) {
            return null;
        }

        $matter = (new MatterModel())->owned($id, $cid);
        if (! $matter) {
            return null;
        }

        $lead = $this->lead((int) $matter['lead_id'], $write);

        return $lead ? ['matter' => $matter, 'lead' => $lead] : null;
    }

    protected function flash(string $type, string $message)
    {
        return session()->setFlashdata($type, esc($message));
    }

    protected function toLead(int $id, string $tab = 'resumen')
    {
        return redirect()->to(base_url('leadflow/leads/ficha/' . $id . '?tab=' . $tab));
    }

    /**
     * Vuelve a donde se pidió, validado contra un patrón cerrado: un `return_to`
     * libre sería una redirección abierta.
     */
    protected function back(string $default)
    {
        $to = (string) $this->request->getPost('return_to');
        if ($to !== '' && preg_match('~^leadflow/[a-z0-9/_-]+(\?[a-z0-9=&_-]*)?$~', $to)) {
            return redirect()->to(base_url($to));
        }

        return redirect()->to(base_url($default));
    }

    protected function notFound(string $to = 'leadflow/leads')
    {
        $this->flash('error', lang('Lfl.errNotFound'));

        return redirect()->to(base_url($to));
    }

    protected function str(string $name, int $max = 255): string
    {
        return mb_substr(trim((string) $this->request->getPost($name)), 0, $max);
    }

    protected function isPost(): bool
    {
        return strtolower($this->request->getMethod()) === 'post';
    }

    /**
     * Pinta el mensaje de un servicio.
     *
     * **`final` a propósito.** `Appointments` llegó a declarar una acción pública
     * `result()` para la ruta «registrar resultado», que tapaba a esta: entonces
     * `$this->result($res)` entraba en la acción, convertía el array a `(int) 1` y
     * volvía a llamarse, hasta agotar la memoria. El choque no daba ningún aviso.
     * Ahora PHP lo rechaza al cargar la clase, que es cuando hay que enterarse: una
     * acción de ruta nueva no puede llamarse como un ayudante de esta base.
     */
    final protected function result(array $r)
    {
        $this->flash($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
