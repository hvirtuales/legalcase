<?php

namespace App\Libraries;

/**
 * Gestor de componentes del núcleo. Descubre componentes en components/, maneja
 * su ciclo de vida (instalar/activar/desactivar/actualizar/desinstalar) y expone
 * sus menús. El estado vive en writable/components.json (lo lee también el
 * Autoload para cargar solo los componentes ACTIVOS).
 *
 * La auto-CARGA de código la hace app/Config/Autoload.php (registra namespaces);
 * este gestor cubre el ciclo de vida de datos (migrar, sembrar, activar…).
 */
class ComponentManager
{
    private function dir(): string
    {
        return ROOTPATH . 'components';
    }

    private function stateFile(): string
    {
        return WRITEPATH . 'components.json';
    }

    /**
     * Caché **por petición** del estado y de los manifiestos. `state()` e
     * `isActive()` se preguntan por cada componente y en cada menú, y
     * `discover()` recorre `components/` y hace `require` de cada Manifest:
     * hacerlo una vez por petición en vez de una por pregunta le quita al
     * sidebar una decena de lecturas de disco.
     */
    private static ?array $stateCache = null;
    private static ?array $manifestCache = null;

    /** Olvida lo memoizado (tras instalar/activar/desinstalar, o en CLI). */
    public static function flushCache(): void
    {
        self::$stateCache    = null;
        self::$manifestCache = null;
        self::$planCache     = null;
        self::$planResolved  = false;
    }

    /** Estado persistido: [carpeta => ['installed'=>bool,'active'=>bool,'version'=>..,'installed_at'=>..,'installed_by'=>..]]. */
    public function state(): array
    {
        if (self::$stateCache !== null) {
            return self::$stateCache;
        }

        $file = $this->stateFile();
        if (!is_file($file)) {
            return self::$stateCache = [];
        }

        return self::$stateCache = (json_decode((string) file_get_contents($file), true) ?: []);
    }

    private function saveState(array $state): void
    {
        file_put_contents($this->stateFile(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        self::$stateCache = $state;
    }

    /** @return array<string, array> [carpeta => manifest + _folder/_namespace/_path] */
    public function discover(): array
    {
        if (self::$manifestCache !== null) {
            return self::$manifestCache;
        }

        $out = [];
        $dir = $this->dir();
        if (!is_dir($dir)) {
            return self::$manifestCache = $out;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path     = $dir . DIRECTORY_SEPARATOR . $entry;
            $manifest = $path . DIRECTORY_SEPARATOR . 'Manifest.php';
            if (is_dir($path) && is_file($manifest)) {
                $m = require $manifest;
                if (is_array($m)) {
                    $m['_folder']    = $entry;
                    $m['_namespace'] = 'Components\\' . $entry;
                    $m['_path']      = $path;
                    $out[$entry]     = $m;
                }
            }
        }

        return self::$manifestCache = $out;
    }

    public function get(string $folder): ?array
    {
        return $this->discover()[$folder] ?? null;
    }

    // ------------------------------------------------------------------ estado

    public function isActive(string $folder): bool
    {
        $s = $this->state();

        return !isset($s[$folder]) || ($s[$folder]['active'] ?? true); // por defecto activo
    }

    public function isInstalled(string $folder): bool
    {
        return !empty($this->state()[$folder]['installed']);
    }

    /** Lista para el panel: cada componente con su manifest + estado. */
    public function all(): array
    {
        $state = $this->state();
        $out   = [];
        foreach ($this->discover() as $folder => $m) {
            $installed = !empty($state[$folder]['installed']);
            $active    = !isset($state[$folder]) || ($state[$folder]['active'] ?? true);
            $instVer   = $state[$folder]['version'] ?? null;
            $out[]     = array_merge($m, [
                'installed'         => $installed,
                'active'            => $active,
                'installed_version' => $instVer,
                'installed_at'      => $state[$folder]['installed_at'] ?? null,
                'updatable'         => $installed && $instVer !== null && version_compare((string) ($m['version'] ?? '0'), (string) $instVer, '>'),
                'status'            => !$installed ? 'not_installed' : ($active ? 'active' : 'inactive'),
            ]);
        }

        return $out;
    }

    // ------------------------------------------------------------------ rótulos

    /**
     * Traduce un rótulo del Manifest al idioma activo.
     *
     * El Manifest es **metadatos que el gestor lee al instalar**, antes de que
     * haya locale, así que sus textos se escriben en el idioma del componente y
     * se quedan como respaldo. Para que el menú, el panel y el formulario de
     * planes hablen el idioma del usuario, el componente declara junto a cada
     * rótulo **su clave** (`name_key`, `label_key`) y aquí se resuelve.
     *
     * La caída al literal no es cortesía, es lo que evita un menú roto: un
     * componente **inactivo** no tiene su namespace registrado, así que su
     * archivo de idioma no se puede cargar y `lang()` devuelve la clave tal
     * cual; sin el respaldo, el panel de Componentes pintaría «Map.compName».
     * Lo mismo con un componente traído de otra instalación que aún no declare
     * claves: sigue funcionando, en su idioma.
     */
    private function t(?string $key, string $literal): string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return $literal;
        }
        $text = lang($key);

        // `lang()` devuelve la clave cuando no la encuentra.
        return ($text === '' || $text === $key) ? $literal : (string) $text;
    }

    /** El nombre del componente en el idioma activo. */
    private function nameOf(array $m, string $folder): string
    {
        return $this->t($m['name_key'] ?? null, (string) ($m['name'] ?? $folder));
    }

    // ------------------------------------------------------------------ ámbito

    /**
     * Ámbito del componente: **`company`** (por defecto, lo de siempre: sus
     * datos y su menú cuelgan de la empresa activa) o **`tenant`** (sus datos
     * cuelgan del grupo y su menú solo aparece dentro del Panel del Tenant).
     *
     * Los componentes que no lo declaran son `company`, así que los cinco que
     * ya existen no cambian de comportamiento.
     */
    public function scopeOf(array $manifest): string
    {
        $scope = trim((string) ($manifest['scope'] ?? 'company'));

        return $scope === 'tenant' ? 'tenant' : 'company';
    }

    /**
     * Ámbito en el que se está trabajando ahora mismo. Los dos contextos son
     * excluyentes: o se está dentro del grupo (`active_tenant_id`, puesto por
     * el Panel del Tenant) o dentro de una empresa. En Global se mantiene el
     * ámbito empresa, que es lo que se veía hasta ahora.
     */
    public function currentScope(): string
    {
        return session('active_tenant_id') ? 'tenant' : 'company';
    }

    /**
     * ¿El plan del contexto habilita este componente? Un componente puede
     * declarar en su Manifest `plan_flag` = columna booleana de `plans` que
     * debe estar activa para que aparezca (menú). Además, su **ámbito** tiene
     * que coincidir con el del contexto: los componentes de tenant no se
     * ofrecen dentro de una empresa ni al revés. Sin `plan_flag`, sin contexto
     * (Global) o sin plan asignado → permitido (se muestra).
     */
    private static ?array $planCache = null;
    private static bool $planResolved = false;

    /**
     * Plan del contexto activo (cacheado por request): el del tenant si se está
     * dentro del grupo, el de la empresa si no. `null` si Global o sin plan.
     */
    private function contextPlan(): ?array
    {
        // Estático, no por instancia: en una misma pantalla hay varios
        // ComponentManager (el sidebar, el panel) y el contexto es el mismo
        // para todos. Antes cada uno resolvía el plan por su cuenta.
        if (self::$planResolved) {
            return self::$planCache;
        }
        self::$planResolved = true;

        $id = (int) (session('active_tenant_id') ?? session('active_company_id') ?? 0);
        if ($id <= 0) {
            return self::$planCache = null;
        }

        return self::$planCache = (new \App\Models\CompanyModel())->effectivePlan($id);
    }

    public function planAllows(array $manifest): bool
    {
        if ($this->scopeOf($manifest) !== $this->currentScope()) {
            return false; // componente de otro ámbito: aquí no pinta nada
        }

        $flag = $manifest['plan_flag'] ?? null;
        if (!$flag || !session('active_tenant_id') && !session('active_company_id')) {
            return true;
        }
        $plan = $this->contextPlan();

        return $plan ? !empty($plan[$flag]) : true; // sin plan asignado → permitido
    }

    /**
     * Qué le da el plan a la empresa activa, componente por componente.
     *
     * Lo usa el **Panel Empresa** para enseñar servicios y cupos sin saber nada
     * de ningún componente concreto: cada uno declara en su Manifest su
     * `plan_flag` y, si tiene cupos, un `plan_limits` con la columna de `plans`
     * y la tabla donde contar lo consumido:
     *
     *     'plan_limits' => [
     *         ['column' => 'max_vouchers', 'label' => 'Comprobantes', 'table' => 'cmp_cont_vouchers'],
     *     ],
     *
     * `0` en la columna = sin límite. Sin plan asignado → todo permitido e
     * ilimitado, igual que en `planAllows()`.
     *
     * @return list<array{code:string,label:string,icon:string,route:?string,enabled:bool,limits:list<array{label:string,used:int,max:int}>}>
     */
    public function planStatus(): array
    {
        $plan  = $this->contextPlan();
        $scope = $this->currentScope();
        // En ámbito tenant se cuenta contra el grupo; en empresa, contra la
        // empresa activa. Son excluyentes (ver `currentScope()`).
        $cid = (int) ($scope === 'tenant' ? session('active_tenant_id') : session('active_company_id') ?? 0);
        if (!$cid) {
            return [];
        }

        $db  = \Config\Database::connect();
        $out = [];

        foreach ($this->discover() as $folder => $m) {
            if (!$this->isActive($folder) || $this->scopeOf($m) !== $scope) {
                continue;
            }

            $flag    = $m['plan_flag'] ?? null;
            $enabled = !$flag || !$plan || !empty($plan[$flag]);
            $limits  = [];

            foreach ($m['plan_limits'] ?? [] as $lim) {
                $max = (int) ($plan[$lim['column']] ?? 0);

                // Contar solo tiene sentido si la tabla existe (el componente
                // puede estar activo pero sin migrar todavía).
                $used = 0;
                if (!empty($lim['table']) && $db->tableExists($lim['table'])) {
                    // Un componente de tenant cuelga sus datos del grupo, así
                    // que su columna por defecto es `tenant_id`.
                    $column = $lim['company_column'] ?? ($scope === 'tenant' ? 'tenant_id' : 'company_id');
                    $b      = $db->table($lim['table'])->where($column, $cid);
                    if ($db->fieldExists('deleted_at', $lim['table'])) {
                        $b->where('deleted_at IS NULL');
                    }
                    $used = (int) $b->countAllResults();
                }

                $limits[] = ['label' => $this->t($lim['label_key'] ?? null, (string) ($lim['label'] ?? $lim['column'])), 'used' => $used, 'max' => $max];
            }

            $menus  = $m['menus'] ?? [];
            $out[] = [
                'code'    => (string) ($m['code'] ?? $folder),
                'label'   => $this->nameOf($m, $folder),
                'icon'    => (string) ($m['icon'] ?? 'icon-puzzle'),
                'route'   => $menus[0]['route'] ?? null,
                'enabled' => $enabled,
                'limits'  => $limits,
            ];
        }

        usort($out, static fn ($a, $b) => [$b['enabled'], $a['label']] <=> [$a['enabled'], $b['label']]);

        return $out;
    }

    // ------------------------------------------------------------------ planes

    /**
     * Los recuadros que el **formulario de planes** pinta, uno por componente
     * del ámbito indicado.
     *
     * Antes cada componente tenía que añadir su bloque a mano en
     * `app/Views/plans/_form.php` y sus campos a `PlanController::collect()` —
     * tres ediciones del núcleo por componente, y si olvidabas una el plan no
     * se podía configurar. Ahora sale del Manifest:
     *
     *     'plan_flag'   => 'networking_enabled',
     *     'plan_fields' => [
     *         ['column' => 'max_connections', 'label' => 'Máx. conexiones por usuario'],
     *     ],
     *
     * Si no declaras `plan_fields`, se usan las columnas de `plan_limits` (que
     * ya traen `column` y `label`). `plan_fields` existe para los cupos que
     * **no** se cuentan por empresa —el de Networking es por usuario— y que por
     * eso no aparecen en `plan_limits`.
     *
     * Solo se pintan los componentes **activos** y las columnas que existen de
     * verdad en `plans` (un componente puede estar activo y sin migrar).
     *
     * @return list<array{code:string,label:string,icon:string,flag:?string,fields:list<array{column:string,label:string,hint:?string}>}>
     */
    public function planFormGroups(?string $scope = null): array
    {
        $scope = $scope === 'tenant' ? 'tenant' : 'company';
        $db    = \Config\Database::connect();
        $out   = [];

        /*
         * El valor inicial de cada columna sale del ESQUEMA, no de un 0 fijo en
         * la vista: el componente declara su defecto en su migración
         * (`lfl_directory_enabled` nace en 1 y `lfl_reviews_enabled` en 0), y
         * el formulario de creación tiene que ofrecer eso mismo. Con un 0 fijo,
         * crear un plan apagaba en silencio un servicio que venía encendido.
         */
        $defaults = [];
        foreach ($db->getFieldData('plans') as $col) {
            $defaults[$col->name] = (int) trim((string) ($col->default ?? 0), "'\"");
        }

        foreach ($this->discover() as $folder => $m) {
            if (!$this->isActive($folder) || $this->scopeOf($m) !== $scope) {
                continue;
            }

            $flag = $m['plan_flag'] ?? null;
            if ($flag && !$db->fieldExists($flag, 'plans')) {
                $flag = null;
            }

            $fields = [];
            foreach ($this->planFieldDefs($m) as $def) {
                if (!empty($def['column']) && $db->fieldExists($def['column'], 'plans')) {
                    /*
                     * `type` y `hint_key` son opcionales y tienen un valor por
                     * defecto que deja intacto a quien no los declare: sin
                     * `type` el campo es un cupo numérico —lo que eran todos
                     * hasta ahora— y sin `hint_key` no hay ayuda que mostrar.
                     * Así un Manifest viejo sigue pintando lo mismo.
                     */
                    $fields[] = [
                        'column' => (string) $def['column'],
                        'label'  => $this->t($def['label_key'] ?? null, (string) ($def['label'] ?? $def['column'])),
                        'hint'   => $this->t($def['hint_key'] ?? null, (string) ($def['hint'] ?? '')) ?: null,
                        'type'   => ($def['type'] ?? '') === 'bool' ? 'bool' : 'number',
                        'default' => $defaults[(string) $def['column']] ?? 0,
                    ];
                }
            }

            if (!$flag && !$fields) {
                continue; // el componente no participa del plan
            }

            $out[] = [
                'code'   => (string) ($m['code'] ?? $folder),
                'label'  => $this->nameOf($m, $folder),
                'icon'   => (string) ($m['icon'] ?? 'icon-puzzle'),
                'flag'   => $flag,
                'fields' => $fields,
            ];
        }

        usort($out, static fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $out;
    }

    /**
     * Campos de plan que declara un componente: sus cupos (`plan_limits`) **y**
     * sus campos propios (`plan_fields`), en ese orden.
     *
     * Los dos, no uno o el otro. Antes `plan_fields` tapaba a `plan_limits`, y
     * un componente que declaraba ambos —LeadFlow— perdía sus cupos del
     * formulario sin ningún aviso: la columna existía, el modelo la aceptaba y
     * el panel de consumo la leía, pero no había forma de ponerle un valor.
     *
     * Se deduplica por columna y gana `plan_fields`, que es el que puede
     * declarar `type` y `hint_key`. Los componentes que solo declaran cupos
     * siguen viendo exactamente lo mismo que antes.
     */
    /**
     * Ranuras de la pantalla de Configuración que los componentes rellenan.
     *
     * Existe porque la Configuración del sistema es del núcleo y un componente no
     * puede editarla: sin esto, meter ahí un campo propio obligaría a tocar
     * `app/Views/settings/`, que es justo lo que el contrato prohíbe y lo que rompe la
     * portabilidad del componente.
     *
     * Un componente lo declara en su Manifest:
     *
     *     'settings_slots' => [
     *         'general' => [
     *             'view'    => 'Components\Mío\Views\settings\campo',
     *             'handler' => 'Components\Mío\Libraries\Algo::handleSettings',
     *         ],
     *     ],
     *
     * La vista pinta **campos sueltos, nunca un `<form>`**: la pantalla entera es un
     * único formulario y uno anidado es HTML inválido. El `handler` recibe la petición
     * después de que el núcleo guarde lo suyo.
     *
     * @return array<int,array{code:string,view:string,handler:?string}>
     */
    public function settingsSlots(string $tab): array
    {
        $out = [];
        foreach ($this->discover() as $folder => $m) {
            if (!$this->isActive($folder)) {
                continue;
            }
            $slot = $m['settings_slots'][$tab] ?? null;
            if (!is_array($slot) || empty($slot['view'])) {
                continue;
            }
            $out[] = [
                'code'    => (string) ($m['code'] ?? $folder),
                'view'    => (string) $slot['view'],
                'handler' => isset($slot['handler']) ? (string) $slot['handler'] : null,
            ];
        }

        return $out;
    }

    /**
     * Ejecuta los `handler` de las ranuras de una pestaña.
     *
     * **Cada uno va en su propio try**: un componente que falle al guardar lo suyo no
     * puede tumbar el guardado de la Configuración del sistema, que es de otro. Se
     * registra y se sigue, igual que hace el motor de automatizaciones con sus acciones.
     *
     * @return array<int,string> los mensajes que devuelvan los handlers, para el aviso
     */
    public function runSettingsHandlers(string $tab, $request): array
    {
        $msgs = [];
        foreach ($this->settingsSlots($tab) as $slot) {
            if (!$slot['handler'] || !str_contains($slot['handler'], '::')) {
                continue;
            }
            [$class, $method] = explode('::', $slot['handler'], 2);
            if (!class_exists($class) || !method_exists($class, $method)) {
                continue;
            }
            try {
                $r = $class::$method($request);
                if (is_string($r) && $r !== '') {
                    $msgs[] = $r;
                }
            } catch (\Throwable $e) {
                log_message('error', 'ComponentManager: el handler ' . $slot['handler'] . ' falló: ' . $e->getMessage());
            }
        }

        return $msgs;
    }

    private function planFieldDefs(array $manifest): array
    {
        $out = [];
        foreach ([$manifest['plan_limits'] ?? [], $manifest['plan_fields'] ?? []] as $set) {
            foreach ($set as $def) {
                if (is_array($def) && !empty($def['column'])) {
                    $out[(string) $def['column']] = $def;
                }
            }
        }

        return array_values($out);
    }

    /**
     * Todas las columnas de `plans` que declaran los componentes (banderas y
     * cupos), de cualquier ámbito y estén activos o no.
     *
     * Lo usa `PlanModel` para completar sus `allowedFields`: sin esto habría
     * que listar a mano en el núcleo la columna de cada componente, y una que
     * faltara se descartaría en silencio al guardar el plan.
     *
     * @return list<string>
     */
    public function planColumns(): array
    {
        $cols = [];
        foreach ($this->discover() as $m) {
            if (!empty($m['plan_flag'])) {
                $cols[] = (string) $m['plan_flag'];
            }
            foreach ($this->planFieldDefs($m) as $def) {
                if (!empty($def['column'])) {
                    $cols[] = (string) $def['column'];
                }
            }
        }

        return array_values(array_unique($cols));
    }

    /** Menús de los componentes ACTIVOS y habilitados por el plan, agrupados por componente. */
    public function menuGroups(): array
    {
        $groups = [];
        foreach ($this->discover() as $folder => $m) {
            if (!$this->isActive($folder) || empty($m['menus']) || !$this->planAllows($m)) {
                continue;
            }
            $items = [];
            foreach ($m['menus'] as $mi) {
                $mi['label'] = $this->t($mi['label_key'] ?? null, (string) ($mi['label'] ?? ''));
                $items[]     = $mi;
            }
            usort($items, static fn ($a, $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));
            $groups[] = ['code' => $m['code'] ?? $folder, 'label' => $this->nameOf($m, $folder), 'icon' => $m['icon'] ?? 'icon-puzzle', 'items' => $items];
        }
        usort($groups, static fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $groups;
    }

    // ------------------------------------------------------------------ ciclo de vida

    /** Instala: migra el namespace, siembra permisos y marca instalado+activo. @return array{ok,message} */
    public function install(string $folder): array
    {
        $m = $this->get($folder);
        if (!$m) {
            return ['ok' => false, 'message' => "Componente '{$folder}' no encontrado."];
        }
        if (!$this->depsSatisfied($m, $missing)) {
            return ['ok' => false, 'message' => 'Faltan dependencias activas: ' . implode(', ', $missing) . '.'];
        }
        $this->ensureNamespace($m);

        try {
            $runner = \Config\Services::migrations();
            $runner->setNamespace($m['_namespace']);
            $runner->latest();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Migraciones: ' . $e->getMessage()];
        }
        $this->seedPermissions($m);

        $s          = $this->state();
        $s[$folder] = [
            'installed'    => true,
            'active'       => true,
            'version'      => (string) ($m['version'] ?? '1.0.0'),
            'installed_at' => date('c'),
            'installed_by' => session()->get('user_id'),
        ];
        $this->saveState($s);

        return ['ok' => true, 'message' => "Componente '{$m['name']}' instalado y activado."];
    }

    public function activate(string $folder): array
    {
        return $this->flip($folder, true, 'activado');
    }

    public function deactivate(string $folder): array
    {
        return $this->flip($folder, false, 'desactivado');
    }

    private function flip(string $folder, bool $active, string $verb): array
    {
        $s = $this->state();
        if (empty($s[$folder]['installed'])) {
            return ['ok' => false, 'message' => 'El componente no está instalado.'];
        }
        $s[$folder]['active'] = $active;
        $this->saveState($s);

        return ['ok' => true, 'message' => "Componente {$verb}."];
    }

    /** Actualiza: corre migraciones pendientes del namespace y sube la versión registrada. */
    public function update(string $folder): array
    {
        $m = $this->get($folder);
        if (!$m) {
            return ['ok' => false, 'message' => 'Componente no encontrado.'];
        }
        $this->ensureNamespace($m);
        try {
            $runner = \Config\Services::migrations();
            $runner->setNamespace($m['_namespace']);
            $runner->latest();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Migraciones: ' . $e->getMessage()];
        }
        $this->seedPermissions($m); // nuevos permisos que traiga la versión

        $s                     = $this->state();
        $s[$folder]['version'] = (string) ($m['version'] ?? '1.0.0');
        $this->saveState($s);

        return ['ok' => true, 'message' => "Componente actualizado a v{$s[$folder]['version']}."];
    }

    /**
     * Desinstala. Con $dropData=true revierte migraciones (borra tablas) y quita
     * permisos; sin él, solo desregistra (conserva datos). Con $removeFiles=true
     * además BORRA la carpeta del componente (eliminación total, sin huella).
     *
     * Ojo: al desinstalar se conserva una entrada `installed:false, active:false`
     * en components.json a propósito. El Autoload trata una carpeta SIN entrada
     * como ACTIVA (drop-in), así que borrar la entrada dejaría el componente
     * cargando sus rutas y servicios pese a estar desinstalado. Con $removeFiles
     * la carpeta ya no existe y sí se elimina la entrada.
     */
    public function uninstall(string $folder, bool $dropData, bool $removeFiles = false): array
    {
        $m = $this->get($folder);
        $s = $this->state();

        // Ni carpeta descubierta ni estado previo: nada que desinstalar (evita
        // registrar basura en components.json ante un nombre inválido).
        if (!$m && !isset($s[$folder])) {
            return ['ok' => false, 'message' => "Componente '{$folder}' no encontrado."];
        }

        if ($m && $dropData) {
            $this->ensureNamespace($m);

            try {
                $this->regressNamespace($m['_namespace']);
            } catch (\Throwable $e) {
                return ['ok' => false, 'message' => 'Rollback: ' . $e->getMessage()];
            }
            $this->removePermissions($m);
        }

        if ($removeFiles) {
            $res = $this->removeFolder($folder);
            if (!$res['ok']) {
                // Los datos ya se revirtieron: deja el componente desinstalado igualmente.
                $s[$folder] = ['installed' => false, 'active' => false, 'uninstalled_at' => date('c')];
                $this->saveState($s);

                return $res;
            }
            unset($s[$folder]); // la carpeta ya no existe: sin huella
            $this->saveState($s);

            return ['ok' => true, 'message' => 'Componente eliminado por completo (datos y archivos).'];
        }

        $s[$folder] = [
            'installed'      => false,
            'active'         => false,
            'version'        => $s[$folder]['version'] ?? ($m['version'] ?? null),
            'uninstalled_at' => date('c'),
        ];
        $this->saveState($s);

        return ['ok' => true, 'message' => $dropData ? 'Componente desinstalado (datos eliminados).' : 'Componente desinstalado (datos conservados).'];
    }

    /**
     * Borra recursivamente la carpeta de un componente. Blindado: el nombre no
     * puede traer separadores ni `..`, la ruta resuelta debe quedar DENTRO de
     * components/ y contener un Manifest.php.
     *
     * @return array{ok:bool,message:string}
     */
    private function removeFolder(string $folder): array
    {
        if ($folder === '' || $folder === '.' || $folder === '..' || preg_match('#[/\\\\]#', $folder)) {
            return ['ok' => false, 'message' => 'Nombre de componente inválido.'];
        }

        $base = realpath($this->dir());
        $path = realpath($this->dir() . DIRECTORY_SEPARATOR . $folder);

        if ($base === false || $path === false) {
            return ['ok' => false, 'message' => 'La carpeta del componente no existe.'];
        }
        if ($path === $base || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) {
            return ['ok' => false, 'message' => 'Ruta fuera de components/: operación cancelada.'];
        }
        if (!is_file($path . DIRECTORY_SEPARATOR . 'Manifest.php')) {
            return ['ok' => false, 'message' => 'La carpeta no parece un componente (falta Manifest.php).'];
        }

        if (!$this->rrmdir($path)) {
            return ['ok' => false, 'message' => 'No se pudo borrar la carpeta del componente (¿permisos o archivos en uso?).'];
        }

        self::flushCache(); // la carpeta ya no está: lo memoizado quedó obsoleto

        return ['ok' => true, 'message' => 'Carpeta eliminada.'];
    }

    /** Borrado recursivo. No sigue enlaces simbólicos (los desvincula). */
    private function rrmdir(string $path): bool
    {
        if (is_link($path) || is_file($path)) {
            return @unlink($path);
        }
        if (!is_dir($path)) {
            return true;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!$this->rrmdir($path . DIRECTORY_SEPARATOR . $entry)) {
                return false;
            }
        }

        return @rmdir($path);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Revierte SOLO las migraciones del namespace indicado, en orden inverso, y
     * borra sus filas de la tabla `migrations`.
     *
     * NO se usa `MigrationRunner::regress()`: ese método ignora `setNamespace()`
     * (pone `namespace = null` y recorre TODOS los namespaces por lotes), así que
     * desinstalar un componente con él revertía el esquema COMPLETO de la app.
     */
    private function regressNamespace(string $namespace): void
    {
        $runner = \Config\Services::migrations();
        $runner->setNamespace($namespace);
        $found = $runner->findNamespaceMigrations($namespace);
        if (!$found) {
            return; // el componente no trae migraciones: nada que revertir
        }

        // Viene con índices numéricos: re-indexar por uid (versión) y revertir de la última a la primera.
        $migrations = [];

        foreach ($found as $mig) {
            $migrations[$mig->uid] = $mig;
        }
        krsort($migrations);

        $db    = \Config\Database::connect();
        $table = config('Migrations')->table ?? 'migrations';

        foreach ($migrations as $mig) {
            if (!empty($mig->path) && is_file($mig->path)) {
                include_once $mig->path;
            }
            $class = $mig->class;
            if (!class_exists($class)) {
                continue;
            }
            $instance = new $class();
            if (method_exists($instance, 'down')) {
                $instance->down();
            }
            $db->table($table)->where(['namespace' => $namespace, 'version' => $mig->version])->delete();
        }
    }

    /** Registra el namespace en tiempo de ejecución (para migrar aunque esté inactivo). */
    private function ensureNamespace(array $m): void
    {
        service('autoloader')->addNamespace($m['_namespace'], $m['_path']);
    }

    private function depsSatisfied(array $m, ?array &$missing = null): bool
    {
        $missing = [];
        foreach (($m['depends'] ?? []) as $depCode) {
            $found = false;
            foreach ($this->discover() as $folder => $other) {
                if (($other['code'] ?? '') === $depCode && $this->isInstalled($folder)) {
                    $found = true;
                }
            }
            if (!$found) {
                $missing[] = $depCode;
            }
        }

        return empty($missing);
    }

    /** Siembra permisos del manifest (idempotente) y los concede a Super Admin y Admin. */
    private function seedPermissions(array $m): void
    {
        $perms = $m['permissions'] ?? [];
        if (!$perms) {
            return;
        }
        $db   = \Config\Database::connect();
        $now  = date('Y-m-d H:i:s');
        $code = $m['code'] ?? $m['_folder'];
        $ptab = $db->table('permissions');
        foreach ($perms as $name => $desc) {
            if (!$ptab->getWhere(['name' => $name])->getRow()) {
                $ptab->insert(['name' => $name, 'description' => $desc, 'module' => $code, 'created_at' => $now]);
            }
        }
        /*
         * Los permisos que el componente marca como **solo para Super Admin**.
         *
         * Sin esto, retirarle uno a «Admin» a mano no servía de nada: esta siembra es
         * re-ejecutable —se corre en cada instalación y actualización— y se lo volvía a
         * conceder en silencio. Es opt-in: un Manifest que no declare nada se comporta
         * igual que siempre.
         */
        $soloSuper = array_map('strval', (array) ($m['permissions_super_only'] ?? []));

        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = $db->table('roles')->getWhere(['name' => $roleName])->getRow();
            if (!$role) {
                continue;
            }
            foreach (array_keys($perms) as $name) {
                if ($roleName !== 'Super Admin' && in_array($name, $soloSuper, true)) {
                    continue;
                }
                $p = $db->table('permissions')->getWhere(['name' => $name])->getRow();
                if ($p && !$db->table('role_permissions')->getWhere(['role_id' => $role->id, 'permission_id' => $p->id])->getRow()) {
                    $db->table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $p->id]);
                }
            }
        }
    }

    private function removePermissions(array $m): void
    {
        $perms = array_keys($m['permissions'] ?? []);
        if (!$perms) {
            return;
        }
        $db  = \Config\Database::connect();
        $ids = array_column($db->table('permissions')->select('id')->whereIn('name', $perms)->get()->getResultArray(), 'id');
        if ($ids) {
            $db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
            $db->table('permissions')->whereIn('id', $ids)->delete();
        }
    }
}
