# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es

Plataforma **CodeIgniter 4** multi-empresa y multi-tenant: autenticación multi-método (password, magic link,
OAuth/Azure, LDAP, 2FA), RBAC (roles → permisos), auditoría, planes y suscripciones, API REST con JWT, theming en
runtime e i18n es/en. Encima corre un **sistema de componentes drop-in** (`components/`): módulos de negocio que se
instalan soltando una carpeta, sin tocar `app/`.

- `system/` y `vendor/` son de terceros: **no se editan**.
- Todo el código propio está en `app/` (núcleo) y `components/` (módulos).
- La documentación de diseño está en español, en `proyectos/`. Los contratos de componentes están en
  `proyectos/CONTRATO_COMPONENTES.md` y `proyectos/CONTRATO_COMPONENTES_TENANT.md`.
- Esta instalación (`pms`) es una **copia de la plataforma «cuentas»**: el `.env` todavía menciona
  `/cuentas/`, y buena parte de `writable/` vino con ella (ver «Estado de esta instalación»).

## Reglas de trabajo (obligatorias)

### 1. Idioma: responder siempre en español

Todas las respuestas, explicaciones, planes, resúmenes y preguntas al usuario van **en español**. Lo mismo vale para
los comentarios en el código, los mensajes de validación y la documentación que se añada (`proyectos/`, este
archivo), que es el idioma del resto del proyecto. Los identificadores (clases, métodos, columnas, rutas) siguen la
convención que ya tenga el código a su alrededor. Los textos de interfaz nunca se incrustan: van por `lang()` con
sus claves en `es` **y** `en`.

### 2. Interfaz gráfica: analizar antes y mantenerla de forma estricta

Toda funcionalidad o componente nuevo debe verse y comportarse **como si siempre hubiera sido parte de la
plataforma**. No se inventa un lenguaje visual propio por pantalla. El orden de trabajo es:

1. **Analizar primero, programar después.**
   - Antes de escribir una vista, abre **al menos una pantalla equivalente que ya exista** y replica su estructura:
     - listado: `app/Views/users/` o `companies/`;
     - formulario: `app/Views/plans/_form.php`;
     - detalle o panel: el de algún componente;
     - editor a pantalla completa: Páginas, Formularios o Mailer.
   - Revisa las clases disponibles en `@layer components` de `resources/css/app.css`: `.card`, `.card-header`,
     `.card-body`, `.btn*`, `.form-*`, `.form-section`, `.badge-*`, `.alert-*`, `.table-card`, `.table`, `.tabs`,
     `.tab-link` y `.tab-panel`.
   - La especificación de estilo es la de `.agent/rules/interfaz-grafica*.md`.
   - El catálogo de clases con ejemplos es `proyectos/guia_componentes_ui.md`.
   - Si algo contradice lo implementado, manda el código actual.
2. **Reutilizar, no reinventar.**
   - Layout `layouts/admin`.
   - Listados con `DataTable` + `partials/datatable` dentro de una `card`, con la acción principal en la cabecera.
   - Borrados con `partials/delete_button`.
   - Formularios agrupados en `.form-section`, en este orden: decisión → datos → contacto → publicación.
   - Adjuntos con `partials/attachments`.
   - Teléfonos con `data-phone` e imágenes con `data-preview`.
   - Solo si no existe una pieza equivalente se crea una nueva, y entonces va a `@layer components` como clase
     reutilizable. No se crea como utilidades sueltas repetidas en cada vista.
3. **Reglas duras de estilo:**
   - **Colores:** ni hex ni rgb en las vistas; solo tokens (`primary`, `surface`, `ink`, `muted`, `line`,
     `success`, `danger`…).
   - **Modo oscuro:** debe verse bien en `[data-theme="dark"]`.
   - **Responsive:** tiene que funcionar en móvil a unos 390 px, sin desbordes horizontales.
   - **Iconos:** solo los que existen en `public/css/icons.css`. Compruébalo, porque uno inexistente no pinta nada.
   - **Recursos externos:** cero CDN.
   - **Bootstrap está prohibido en cualquier forma.** La plataforma es solo Tailwind:
     - ni el framework;
     - ni sus clases: `row`, `col-*`, `d-none`, `form-control`, `form-select-sm`, `table-responsive`,
       `btn-outline-primary`…;
     - ni Bootstrap Icons (`bi bi-*`);
     - ni su JavaScript (`data-bs-*`).
   - `.btn`, `.badge`, `.card`, `.alert` o `.list-group` son **clases propias** de `app.css`, hechas con Tailwind,
     y **solo existen las variantes definidas allí**. Una variante inventada al estilo Bootstrap no da error,
     simplemente no aplica estilo.
   - **Vistas públicas:** autosuficientes (§7.3 del contrato).
4. **Verificar antes de dar por terminado.**
   - Ejecuta `composer tailwind` después de tocar clases.
   - Abre la pantalla en un navegador real, en claro **y** oscuro y en escritorio **y** móvil.
   - Compárala con la pantalla de referencia. Si algo se aparta del patrón, se corrige o se justifica
     explícitamente en la respuesta.
   - Si se añade un patrón visual nuevo que otros deban seguir, se documenta en este archivo.

## Entorno local (XAMPP en Windows)

- `php` y `composer` **no están en el PATH**. Usa `C:\xampp\php\php.exe` (PHP 8.2.12), y Composer como phar:
  `C:\xampp\php\php.exe composer.phar <cmd>` (el `composer.phar` está en la raíz).
- El document root es la raíz del proyecto. `.htaccess` reescribe todas las peticiones hacia `public/`.
- **BD de trabajo: la que diga `database.default.database` en `.env`, hoy `pms_db`** (MySQLi, `root`, sin
  contraseña).
- El mismo MySQL aloja otras apps del ecosistema (`red_bd`, `rupin_db`, `cuentas_db`…). `red_bd` es la de
  «cuentas», con más de 90 tablas: **no es la de este proyecto**.
- `baseURL` se auto-detecta en el constructor de `app/Config/App.php`. Está normalizado para Windows, porque
  `dirname('/index.php')` devuelve `'\'` y metía `%5C` en todos los enlaces absolutos. Si tocas esa detección,
  verifica `base_url()` con `spark serve`.
- En esta máquina quedan procesos `php -S` viejos. Si `spark serve --port N` cae sobre un puerto ocupado, el
  servidor nuevo no arranca y responde el viejo, y parece que un arreglo no surte efecto. Elige un puerto libre
  (`Get-NetTCPConnection -State Listen`).
- OPcache está apagado en `C:\xampp\php\php.ini`. Es la causa de unos ~390 ms de arranque por petición;
  activarlo reduce los tiempos a la mitad.

### Estado de esta instalación (verificado 2026-09-11)

- **`writable/components.json` fue heredado y no refleja la BD.**
  - Marca 10 componentes como `installed/active` (Copropiedad está desinstalado).
  - Pero `pms_db` solo tiene las **19 tablas del núcleo**: todas las migraciones están en el namespace `App`, no
    hay ninguna tabla `cmp_*` y `plans` no tiene columnas de componentes.
  - El Autoload registra igualmente los componentes activos, así que sus rutas y menús aparecen, y fallarán por
    tablas inexistentes.
  - Para desplegar su esquema, ejecuta `spark components:install <Carpeta>` por cada uno. Es re-ejecutable y
    sirve precisamente para reparar una BD recreada.
- `writable/installed.flag` existe, así que el asistente `/install` está bloqueado. Bórralo para volver a correrlo.
- **LeadFlow sí está instalado y migrado en `pms_db`.** Para probarlo se creó la empresa **«Firma Demo LeadFlow»**
  (id 5): dueño `admin`, plan Profesional, suscripción activa de un año y datos de ejemplo.
  - Hizo falta la suscripción: `switch-company` rechaza una empresa que no la tenga vigente.
  - Para quitarla: `DELETE` de sus filas en `subscriptions` y `company_users`, y de la empresa en `companies`. Las
    tablas `cmp_lfl_*` caen en cascada.

## Comandos

Todos se ejecutan con `C:\xampp\php\php.exe` delante (por ejemplo `C:\xampp\php\php.exe spark serve`).

```powershell
php spark serve                           # dev server en http://localhost:8080
php spark migrate                         # migraciones del núcleo (App)
php spark db:seed DatabaseSeeder          # datos base: roles, permisos+grants, settings, planes, sectores, admin/admin123

php spark components:list
php spark components:install <Carpeta>    # migra su namespace + siembra permisos + marca activo (idempotente)
php spark components:uninstall <Carpeta> [--drop-data] [--purge] [--force]
php spark migrate -n "Components\<Carpeta>"   # solo las migraciones de un componente

php composer.phar tailwind                # recompila public/css/tailwind.css (minificado)
php composer.phar tailwind:watch

vendor\bin\phpunit                        # = composer test
vendor\bin\phpunit --filter testMetodo tests/unit/HealthTest.php
vendor\bin\php-cs-fixer fix --dry-run --diff <ruta>
```

- **Hay que recompilar Tailwind después de tocar clases** en cualquier vista, también las de componentes
  (`@source "../../components"` las recoge). La salida `public/css/tailwind.css` se versiona.
- **Tests:** solo están los ejemplos boilerplate de CI4 (`tests/unit`, `tests/database`, `tests/session`). El grupo
  `tests` usa SQLite `:memory:`. No hay cobertura real de la app: no des por probada ninguna funcionalidad.
- **php-cs-fixer:** no hay `.php-cs-fixer.dist.php` en la raíz, así que sin ruta ni config aplica las reglas por
  defecto y no el estándar de CodeIgniter (`codeigniter/coding-standard` está en require-dev). Limítalo a los
  archivos que tocas.
- **La verificación real vive en comandos spark de autotest/contrato**, no en PHPUnit:
  - `tenant:autotest` (núcleo).
  - `paginas:autotest|contrato|seed`, `formularios:autotest|contrato|seed`, `mapas:autotest|contrato` y
    `copropiedad:autotest|contrato`. Los de Copropiedad solo aparecen si está activo.
  - Los `*:autotest` crean filas con prefijo `ZZTEST` y las borran siempre.
  - Los `*:contrato` auditan el componente contra el contrato (escanean el código sin comentarios vía
    `token_get_all`).
- **Perfilado:** con `BENCH=1` en el entorno (ver `app/Config/Events.php`) cada petición escribe en
  `writable/bench.log` sus consultas, las repetidas y el tiempo.

## Arquitectura

### Flujo de petición y acceso

El routing es **explícito** en `app/Config/Routes.php` (auto-routing apagado). Filtros relevantes
(`app/Config/Filters.php`):

- **`install`** (global): sin `writable/installed.flag` todo va a `/install`; con el flag, `/install` queda
  bloqueado. La lógica del asistente vive en `App\Libraries\Installer` y funciona sin BD ni sesión.
- **`auth`**: exige sesión y además **fuerza el contexto de empresa**. Un usuario no-super sin empresa válida
  recibe una auto-seleccionada; sin ninguna empresa solo puede ir a `/profile`.
- **`permission:<modulo.accion>`**: `UserModel::hasPermission()`. Registra el intento fallido en `audit_logs` y
  redirige a `/dashboard`.
- **`tenant`**: contexto de grupo + vínculo real + vigencia del grupo.
- **`apiauth`**: JWT.
- **`csrf`**: activo globalmente salvo `api/v1/*`.

**Una funcionalidad protegida nueva lleva tres cosas:** la ruta dentro del grupo `auth`, el filtro
`permission:x.y` y **el permiso sembrado** (seeder o migración). Los borrados son siempre **POST** con CSRF, vía el
partial `partials/delete_button`; nunca un `<a href>`.

### RBAC

`users ──< user_roles >── roles ──< role_permissions >── permissions`.

- Los permisos efectivos son la unión de todos los roles del usuario.
- Toda comprobación pasa por `UserModel::hasPermission()` (con caché por petición). No consultes las tablas pivote
  en otro sitio.
- `active_role_id` en sesión es una pista de UI, **no una frontera de autorización**.

### Contextos: Global / Empresa / Grupo (multi-tenant)

- **Tres contextos excluyentes en sesión**, y fijar uno limpia el otro:
  - **Global**: solo Super Admin.
  - **Empresa**: `active_company_id`, vía `/switch-company/(:num)`.
  - **Grupo**: `active_tenant_id`, vía `/switch-tenant/(:num)`.
- **El aislamiento de datos de todo el sistema es `active_company_id`.** Los componentes filtran por él y nunca
  por un id que venga del POST.
- **Pertenencia a empresa:** dueño (`companies.owner_id`) o miembro de la pivote `company_users` (`is_admin`).
  **No existe `users.company_id`**. Un usuario puede administrar varias empresas.
- **Tenant (grupo de empresas).**
  - No tiene tabla propia: es una fila de `companies` con `type='tenant'`, y cada hija apunta a ella con
    `parent_id`.
  - Solo hay **un nivel de anidación**.
  - El administrador del grupo es un **vínculo** (dueño o admin-miembro de la fila-tenant), **no un rol**. Los
    roles son globales, y un rol con permisos de empresas daría poder sobre todas las de la plataforma.
- **`App\Libraries\TenantGuard`** es la única fuente de verdad del alcance. Se engancha solo en
  `CompanyModel::getAccessibleFor()` y `userCanAccess()`; por eso los componentes de ámbito empresa no saben nada
  de tenants.
- **`CompanyModel::effectivePlan($id)`** es el único punto por el que se resuelve el plan.
- **`CompanyModel::isUsable()`** exige que la suscripción esté vigente, la propia y la del grupo.
- El sidebar (`app/Views/layouts/admin.php`) oculta la administración de plataforma cuando el Super Admin entra en
  una empresa o grupo (`$sbPlatform`). Es **presentación**: las rutas siguen respondiendo.

### Planes y suscripciones

- `plans.scope` es `company` o `tenant`. `subscriptions` guarda el estado (`trial|active|expired|cancelled`) y
  `end_date`, con auto-marcado a `expired`.
- La **lista blanca** de planes que un tenant puede repartir entre sus empresas está en `tenant_plans`.
- **Las columnas de servicio de cada componente en `plans` las añade el propio componente** con su migración y
  las declara en su `Manifest.php`:
  - `plan_flag`: interruptor del servicio.
  - `plan_limits`: cupos contados por empresa o grupo; alimentan el panel de consumo.
  - `plan_fields`: campos que solo aparecen en el formulario.
- A partir de esas declaraciones:
  - `ComponentManager::planFormGroups()` genera el formulario de planes.
  - `PlanController::collect()` guarda solo lo que declara el Manifest.
  - `PlanModel` amplía sus `allowedFields` desde `planColumns()`.
  - **Añadir un componente no obliga a tocar el formulario de planes.**
- Cada componente hace valer su plan en su propio `PlanGuard`, **en el controlador o servicio** (no solo
  escondiendo botones).
- **Una columna de plan que no existe significa «permitido»**, no «prohibido».

### Componentes (arquitectura de plugins)

- **Descubrimiento.** `app/Config/Autoload.php` escanea `components/` y registra como PSR-4
  `Components\<Carpeta>` cada carpeta que tenga `Manifest.php`, **solo si está activa** según
  `writable/components.json`. Una carpeta sin entrada cuenta como activa (drop-in). A partir de ahí CI4
  auto-descubre su `Config/Routes.php`, sus servicios, eventos y filtros.
- **Ciclo de vida: `App\Libraries\ComponentManager`.**
  - `install`, `activate`/`deactivate` (conservan datos), `update`, `uninstall($folder, $dropData, $removeFiles)`,
    `menuGroups()`, `planStatus()` y `t()`.
  - Panel en `/components` (permiso `components.manage`, solo Super Admin).
  - Desinstalar deja la entrada `installed:false` **a propósito**: si se borrara, el Autoload trataría la carpeta
    como activa.
- **Convenciones:** namespace `Components\<Comp>`, tablas `cmp_<code>_*`, permisos `<code>.<accion>` y FK a las
  tablas del núcleo, que **nunca se redefinen**.
- **Migraciones:**
  - Van por namespace, son **idempotentes** y comprueban `tableExists('plans')` antes de añadirle sus columnas.
  - Todas viven en la carpeta del componente, incluida la que añade sus columnas a `plans`.
  - Al mover una migración ya aplicada a otro namespace hay que actualizar `namespace` **y** `class` en la tabla
    `migrations`.
- **Lo que el contrato describe pero el núcleo NO implementa:**
  - La publicación de assets a `public/components/<code>/`: cada componente sirve los suyos con un controlador
    `Asset` y una **lista blanca fija**.
  - Los eventos de ciclo de vida: las plantillas de arranque se siembran con `spark <comp>:seed`.
- **Portabilidad:**
  - Un componente **no puede depender de código que él mismo añadió al núcleo**; lleva su propia copia de
    `QrService`, `PdfService`, `HtmlSanitizer`, `ImageOptimizer`…
  - Llama al núcleo por su firma más antigua.
- **Vistas públicas autosuficientes** (§7.3): CSS y SVG en línea, tipografía del sistema, sin `tailwind.css`,
  `icons.css` ni CDN. Las pantallas internas sí usan el sistema de diseño del núcleo.
- **Textos del Manifest.** Llevan su clave de idioma al lado (`name_key`, `label_key`) y se resuelven con
  `ComponentManager::t()`, que cae al literal si el componente está inactivo y su archivo de idioma no se puede
  cargar.
- **Skill:** para crear, portar o auditar componentes usa la skill **`componentes-ci4`**.

| Carpeta | code | Rutas | Ámbito | Notas / verificación |
|---|---|---|---|---|
| Contabilidad | `cont` | `/contabilidad` | empresa | Comprobantes con asiento de partida doble, PUC e impuestos, cuentas de cobro públicas, tesorería, PDF y QR. Sin i18n salvo los rótulos del Manifest. |
| Servicios | `quo` | `/servicios` | empresa | Cotizaciones, catálogo digital, tarjeta digital del vendedor. El code sigue siendo `quo` y hay redirecciones 302 desde `/cotizaciones`. |
| Marketplace | `mkt` | `/marketplace` | empresa (catálogo común) | |
| Pqrsf | `pqr` | `/pqrsf` | empresa | La IA solo redacta borradores: la respuesta al ciudadano la aprueba una persona. |
| Networking | `net` | `/networking` | **global** | Excepción consciente: sus tablas no llevan `company_id`. |
| Paginas | `pag` | `/paginas`, `/pagina` | empresa | `paginas:autotest`, `paginas:contrato`, `paginas:seed` |
| Formularios | `frm` | `/formularios`, `/formulario` | empresa | `formularios:autotest`, `formularios:contrato`, `formularios:seed`. Sin IA a propósito (lo verifica el auditor). |
| Mailer | `mailer` | `/mailer`, `/mailer-publico` | empresa | `mailer:dispatch`. Envío por su propio `SesMailer`. |
| Mapas | `map` | `/mapas`, `/mapa` | empresa | `mapas:autotest`, `mapas:contrato` |
| Copropiedad | `cop` | `/copropiedad` | empresa | `copropiedad:autotest`, `copropiedad:contrato`. **Desinstalado aquí.** |
| Directorio | `dir` | `/directorio`, `/directorio-publico` | **tenant** | Piloto de ámbito tenant: tablas con `tenant_id` y filtros `['auth','tenant']`. |
| LeadFlow | `lfl` | `/leadflow`, `/leadflow-publico` | empresa | Captación, conversión y **gestión de expedientes** de firmas jurídicas. Docketwise es opcional. `leadflow:autotest`, `leadflow:contrato`, `leadflow:tick`, `leadflow:expedientes`. Ver su sección. |
| LegalCase | `lgc` | `/legalcase`, `/legalcase-portal` | empresa | Asuntos jurídicos, procesos, plazos, documentos y **LegalCase Connect** (portal del cliente con actualizaciones aprobadas). Independiente (`depends: []`). `lgc:autotest`, `lgc:contrato`, `lgc:tick`. Ver su sección. |

**Patrones que se repiten entre componentes** (léelos en uno antes de replicarlos en otro):

- **Constructores por árbol JSON** (Paginas, Formularios, Mailer). Lo guardado es un árbol JSON, no HTML.
  - Un `*Registry` es la única definición de tipos y propiedades; de él beben la paleta, el inspector, el
    renderizador y el saneador.
  - El saneador **reconstruye** el árbol contra el registro en lugar de limpiarlo.
  - El `Renderer` del servidor es la única autoridad sobre la salida.
  - Añadir un tipo = una entrada en el registro + un `case` en el renderizador.
- **Enlaces públicos:** token aleatorio de 32 bytes y nunca el id. El 404 no distingue entre «no existe»,
  «borrador» y «plan sin el servicio».
- **Archivos privados** en `writable/uploads/<comp>/`, fuera de `public/`. Se sirven por un controlador que
  comprueba el permiso **y** la empresa.
- **Snapshots en documentos emitidos:** editar un maestro no altera lo ya emitido.
- **Numeración consecutiva transaccional** (`PREFIJO-000001`).
- **Datos demo** solo si el módulo de esa empresa está vacío, comprobado en el servicio.

### Componente LeadFlow Legal (`components/LeadFlow`)

Implementa el MVP del §32 de `proyectos/insumos/leadflow_legal_docketwise.md`: captación, pipeline Kanban,
precalificación, scoring, actividades, agenda, timeline y conversión Lead → Contact + Matter en Docketwise.

- **Multiempresa:** el «tenant» del documento es la **empresa** de la plataforma (ámbito `company`).
- **Función comercial por firma:** tabla `cmp_lfl_members`, con los roles supervisor, asesor, abogado y auditor.
  - No son roles del núcleo, que son globales.
  - Los permisos `lfl.*` deciden **qué** se puede hacer; `Libraries/Access::scope()` decide **sobre qué leads**.
  - Toda consulta de leads pasa por `Access`.
- **Docketwise va detrás de `Libraries/Integrations/IntegrationAdapter`,** con dos adaptadores:
  - el **simulado**, que guarda una cuenta falsa por firma en `cmp_lfl_sim_records`;
  - el **HTTP**, escrito contra la documentación v1 y **sin probar**: no hay sandbox y el acceso se pide a
    dev@docketwise.com.
- **El modo** (`simulated`/`live`) y la app OAuth son **globales**: tabla `cmp_lfl_providers`, pantalla
  `/leadflow/docketwise/plataforma`, **solo Super Admin**. Cada firma conecta su cuenta por OAuth y sus tokens se
  guardan cifrados con `Libraries/Crypto`.
- **Límites de la API v1 que explican el diseño:**
  - No busca contactos por email ni teléfono: se usa la caché `cmp_lfl_ext_contacts`, que se sincroniza bajo
    demanda.
  - No permite asignar el tipo de Matter: va en el título y la descripción.
  - No hay webhooks.
- **Conversión idempotente,** en dos niveles:
  - `cmp_lfl_conversions.lead_id` es UNIQUE;
  - cada paso consulta y guarda su id externo en `cmp_lfl_mappings` antes del siguiente, así que un reintento
    continúa sin duplicar.
  - Solo viajan a Docketwise los documentos **autorizados** y las notas transferibles.
- **Formularios dinámicos:** motor propio (`FormEngine`); no depende del componente Formularios.
  - El árbol se reconstruye al guardar.
  - La lógica condicional se revalida en el servidor, y lo que llega para un campo oculto se descarta.
- **Automatizaciones (§19, fase 2):** `DISPARADOR → CONDICIONES → ACCIONES` en `cmp_lfl_automations`, con la
  bitácora `cmp_lfl_automation_runs`. Pantallas en `/leadflow/automatizaciones` (`lfl.configure`; las ejecuciones
  se consultan con `lfl.view`).
  - **`AutomationRegistry` es la ÚNICA definición** de los 9 disparadores, los campos, los 7 operadores y las 8
    acciones: de ahí beben el editor, el saneador y el motor, así que **añadir una acción es una entrada ahí y un
    `case` en `AutomationEngine`** (mismo criterio que `FieldRegistry` en Formularios y `BlockRegistry` en Páginas).
  - **Lo que llega del navegador no se limpia, se reconstruye**: una etapa o un usuario de OTRA firma no llegan a
    guardarse, y una acción que no podría hacer nada (mover a ninguna etapa, una nota vacía) se descarta en vez de
    quedar como una trampa silenciosa.
  - **Las dispara el SERVICIO, nunca una pantalla** (`LeadService::create()/moveStage()`,
    `ScoringService::recalculate()`, `AppointmentService`, `SubmissionService`), así que un lead que entra por el
    formulario público, por la importación o a mano dispara exactamente lo mismo. `activity_overdue` es el único
    que lo provoca el reloj: va en `leadflow:tick`.
  - **`SubmissionService` nació de esto:** las respuestas de formulario se guardaban en dos controladores, y el
    disparador «formulario completado» tenía que salir de un solo sitio o una tercera vía se quedaría sin
    automatizar sin que nadie lo notara.
  - **Tres frenos contra los bucles** (una acción puede disparar otro evento): profundidad máxima 3, una
    automatización no se repite sobre el mismo lead dentro de la misma petición, y `once_per_lead` lo hace valer
    **entre peticiones** por el UNIQUE `(automation_id, ref)` de la bitácora — `ref` es `lead:<id>`,
    `stage:<lead>:<etapa>` o `activity:<id>`, y va a NULL cuando la automatización puede repetirse (un UNIQUE
    admite varios NULL, así que las dos formas conviven en el mismo índice).
  - **Un fallo de una acción no aborta las demás ni la operación del usuario**: se anota en la ejecución. Una
    automatización no puede impedir que alguien guarde un lead.
  - El motor actúa con `Access::system()`: ve y escribe dentro de esa empresa y solo de esa, sin suplantar a nadie
    (`userId = 0`, así la línea de tiempo lo registra como del sistema).
  - **Datos demo:** `DemoData::runAutomations()` siembra 4 ejemplos, uno por disparador (lead creado, formulario
    completado, score alcanzado y no-show), solo si la firma no tiene ninguna. Botón en el estado vacío de
    `/leadflow/automatizaciones`, y también se siembran dentro de los datos demo completos.
    - **Nacen como plantillas para editar**, y la única que habla con el exterior —el correo de bienvenida al
      prospecto— **nace desactivada**: cargar datos de ejemplo no puede acabar mandándole un correo con texto de
      relleno a un prospecto real.
    - Pasan por el **mismo saneador** que el editor, así que los datos demo no entran por una puerta más ancha
      que la del usuario (lo comprueba el autotest).
  - **`DemoData::run()` apaga el motor mientras siembra** (`AutomationEngine::disable()`, con `finally`): los
    leads de ejemplo son fabricados y no deben disparar las automatizaciones reales de la firma ni mandar correos
    a direcciones inventadas. Verificado: el demo completo deja 12 leads y **0 ejecuciones**.
- **Sin Redis ni colas:** `php spark leadflow:tick` recalcula los scores por tiempo, dispara las automatizaciones
  de actividad vencida y renueva tokens. Se programa con cron o el Programador de tareas.
- **LA RAÍZ DEL SITIO ES SUYA, y es la ÚNICA excepción a «no se toca el núcleo».**
  - `app/Config/Routes.php` sirve `/` con `Components\LeadFlow\Controllers\Directory::home` **si la clase
    existe**, y con `AuthController::login` si no. El acceso vive en `/login`, que es a donde manda el
    filtro `auth`.
  - Hace falta que esté en `app/`: CI4 impide a propósito que una ruta auto-descubierta pise una del app
    (`RouteCollection::create()`, «The app routes should always be the source of truth»), así que el
    componente **no puede** quedarse la raíz por su cuenta.
  - La comprobación de clase no es decorativa: el Autoload solo registra los componentes **activos**, así
    que desactivar LeadFlow devuelve la raíz al login sin tocar nada.
  - De la raíz cuelgan `buscar`, `area/{slug}`, `sitemap.xml` y `portada`. Las direcciones viejas
    (`/abogados*`) siguen vivas como **301**: hay enlaces repartidos y perfiles indexados.
- **Portada de la página de inicio:** es **global**, no de una firma. Vive en `cmp_lfl_platform`
  (clave/valor del componente, no `app_settings`), el archivo en `writable/uploads/lfl/platform/` y lo
  sirve `Directory::cover` en `/portada` con caché larga y un `?v=` con la fecha del archivo. Se sube
  desde `/leadflow/directorio/plataforma`, solo Super Admin.
  - `PlatformModel` expone **`value()` y `put()`**, no `get()`/`set()`: `CodeIgniter\Model` ya declara
    `set()` y una firma distinta rompe la clase al cargarla.
  - `Response::setCache()` y no `setHeader('Cache-Control', …)`: la respuesta de CI4 nace con `no-store`
    y solo `setCache()` lo retira. Las directivas sin valor (`public`, `immutable`) van **sin clave**.
- **«Equipo» (`/leadflow/directorio`) es UNA pantalla, no dos.** Absorbió «Configuración → Equipo».
  - `cmp_lfl_members` y `cmp_lfl_profiles` se indexan por **lo mismo**, `(company_id, user_id)`, y
    `ProfileService` ya exigía que un perfil de abogado tuviera usuario y que no hubiera dos del mismo
    usuario. Eran dos mitades del mismo registro en dos pantallas que no se hablaban.
  - **Y ya había descuadrado los datos**: había un perfil publicado llamado «Jaime Zuluaga» apuntando al
    usuario del administrador. Como `InquiryService` asigna el lead con `profile['user_id']`, las consultas
    que entraban por esa ficha pública se le asignaban a quien no era. Corregido.
  - **La lista es de PERFILES, no de personas**, y la función comercial solo se lee en ella. Lo que es de
    la persona —**función comercial** y **usuario de Docketwise**— se edita en el **formulario del
    perfil** (`Profiles::saveMember()`), porque eran dos controles por fila y convertían el listado en
    una rejilla. El perfil de la **firma** (`kind='firm'`, sin usuario) va en un botón de la cabecera.
  - `saveMember()` vive en el controlador y no en `ProfileService` a propósito: son dos tablas con
    semánticas distintas —la ficha pública y qué leads ve esa persona— y mezclarlas haría que publicar
    un perfil pudiera cambiar permisos de visibilidad. Lo único que las une es el usuario.
  - **Ojo con `MemberModel::setFor()`:** un rol nulo o inválido **borra la fila del miembro**, y con ella
    el `docketwise_user_id`. Es su comportamiento de siempre —«sin función» significa quitar la
    función—, pero conviene saberlo antes de pasarle lo que llegue del formulario.
  - Un usuario **sin perfil público** no tiene hoy dónde recibir función comercial: la única puerta es
    el formulario del perfil. Afecta a quien no sale en el directorio (un auditor, un paralegal).
  - `Settings::team` se conserva como **301** a la pantalla nueva: el manual la citaba y hay enlaces
    guardados; un 404 se lee como que la funcionalidad desapareció.
- **«Moderación» (`/leadflow/moderacion`) es UNA bandeja de plataforma.** Fusionó «Verificación» y
  «Denuncias», que revisaban el mismo objeto —el perfil— con el mismo revisor.
  - Dos pestañas (`partials/_moderation_tabs`). La cola de verificación gana una columna con el **recuento**
    de denuncias pendientes de cada perfil, que es el dato que más pesa para decidir y que antes obligaba a
    acordarse de ir a la otra pantalla.
  - **El recuento no es el contenido:** la pestaña de denuncias solo se le enseña al Super Admin. Un
    Verificador necesita saber que hay algo que mirar, no leer lo que un ciudadano escribió sobre una firma.
  - Filtro `permission:lfl.verify` —Super Admin y el rol «Verificador»—; cerrar una denuncia sigue exigiendo
    Super Admin dentro del controlador. **Ningún permiso cambió.**
  - `/leadflow/verificacion` y `/leadflow/denuncias` redirigen con **301**, y llevan su `permission:` porque
    el auditor del contrato exige que toda ruta interna lo tenga, redirección incluida.
- **La verificación no se puede conceder desde la pantalla de la firma.** `Profiles::verify()` lo dice:
  *«Sin `owned()`: la verificación es de la plataforma y cruza empresas a propósito»*. Si el botón viviera
  ahí, la firma se verificaría a sí misma y la insignia dejaría de significar nada.
- **Catálogo de funcionalidades del manual:** `Libraries/HelpCatalog.php` es la **única definición** de
  las 33 funcionalidades que lista Configuración → Ayuda. Añadir una es una entrada ahí y dos claves de
  idioma. `leadflow:contrato` §10 comprueba que las rutas que enlaza existan, que los permisos sean los
  del Manifest y que no falte ninguna clave en es o en.
- **Expedientes (`/leadflow/expedientes`): la gestión del caso DENTRO de la plataforma.**
  - **Amplía el alcance del insumo a propósito.** El §3 de `leadflow_legal_docketwise.md` le asigna los
    Matters a Docketwise y la línea 40 dice que «no pretende reemplazarlo». Se añadió porque el diseño daba
    por hecho que toda firma usa Docketwise: `ConversionService::problems()` exigía `isConnected()` y la
    etapa `kind='converted'` solo la escribía la conversión, así que **una firma sin Docketwise dejaba sus
    leads en «Aprobado» para siempre**. El expediente es la salida que no depende de nadie.
  - **Docketwise es ahora opcional.** `problems()` ya no bloquea por `status='converted'`: bloquea solo si
    hay una conversión con `status='done'`. Un lead con expediente se puede sincronizar después.
  - **Un expediente por lead** (`UNIQUE(lead_id)`, como `cmp_lfl_conversions` y `cmp_lfl_reviews`). Un
    segundo trámite del mismo cliente es un segundo lead, y así la atribución y el scoring siguen contando
    una venta por intención. **No hay entidad «cliente».**
  - **Los hijos NO llevan `matter_id`:** actividades, documentos, notas, consultas y línea de tiempo cuelgan
    de `lead_id` y la relación es 1:1, así que el expediente los deriva. Una columna propia sería
    funcionalmente dependiente y obligaría a cinco escritores a acordarse de rellenarla.
  - **La línea de tiempo es la del lead**, con los tipos `matter`, `fee` y `payment` añadidos a
    `Meta::TIMELINE_ICONS`. El historial del caso es la continuación del comercial; partirlo daría dos
    medios historiales. `Timeline::log()` sigue siendo la única puerta de escritura.
  - **Al abrir, el lead queda exactamente en el estado terminal de siempre** (etapa `converted`,
    `status='converted'`): ni el pipeline, ni el Kanban, ni `ReportService` cambian una consulta. Esa
    transición vive en **`LeadService::markConverted()`**, que usan la apertura y la conversión.
  - **El consecutivo** (`EXP-000001`) vive en `cmp_lfl_settings.matter_sequence` y se incrementa con una
    expresión evaluada en el servidor dentro de `transStart()`, igual que `VoucherService::nextNumber()` de
    Contabilidad. Un intento fallido **no gasta número**, porque va dentro de la transacción del insert.
  - **El snapshot congela lo que se imprime** (servicio, tipo, abogado, cliente, moneda), con FK y copia a
    la vez, como `QuoteService::clientSnapshot()`. El título prefiere `service_name` y no
    `matter_type_name`: en esta instalación dos servicios comparten el tipo «Profesionales» de Docketwise.
  - **Etapas procesales propias** (`cmp_lfl_matter_stages`, `kind` ∈ `open|filed|waiting|closed|cancelled`),
    distintas del pipeline: **un expediente SÍ retrocede** (una solicitud de evidencia lo devuelve a
    recolección documental), mientras que `LeadService::advanceTo()` solo avanza. Cerrar exige motivo de
    `MatterModel::CLOSE_REASONS`, como perder un lead exige razón.
  - **Abrir lleva `lfl.convert`**, que ya significaba «convertir un lead ganado en cliente»: el expediente
    es esa conversión y Docketwise solo era el destino. El resto usa los verbos de siempre.
  - **El alcance se hereda del lead:** `BaseLfl::matter()` resuelve el expediente por empresa y además pasa
    el lead por `LeadService::visible()`. Las consultas hacen JOIN a `cmp_lfl_leads` y llaman
    `Access::scope()` con la tabla por defecto, igual que `Activities` y `Appointments`.
  - **`cmp_lfl_mappings.entity_type='matter'` es el Matter de DOCKETWISE, y su `local_id` es el id del
    LEAD**, no del expediente. No se migra a propósito: `stepMatter()` lee ese mapeo y de él depende la
    idempotencia de las conversiones ya hechas; cambiarlo a medias crearía un segundo Matter en la cuenta
    real de un cliente.
  - **El segmento `caso` estaba ocupado** (`leads/caso/(:num)` y `firma/(:segment)/caso` son USCIS), por eso
    la ruta es `expedientes`.
  - `spark leadflow:expedientes --backfill [--company=N] [--apply]` abre expedientes de los leads ya
    ganados. **Ensayo por omisión**; hace falta `--apply` para escribir. Pasa por `MatterService::open()`
    con `Access::system()`, así que numera, congela y registra igual que el camino normal.
  - **Checklist documental: plantilla + instancia.** `cmp_lfl_service_requirements` es la plantilla por
    servicio; al abrir el expediente se **copia** a `cmp_lfl_matter_requirements`, que lleva el estado.
    - Se copia y no se referencia por el mismo motivo que el snapshot: la firma afina su plantilla cada
      dos meses y un caso ya abierto no puede cambiar de requisitos porque alguien editó el catálogo.
      `requirement_id` es `SET NULL`, así que borrar la plantilla no vacía el checklist de un caso vivo.
    - Los cinco estados son `pending|received|approved|rejected|waived`. **`waived` es el que justifica
      la tabla**: es una decisión del abogado sobre ese caso y no se puede deducir cruzando documentos.
      Solo `approved` y `waived` cuentan como resueltos (`MatterRequirementModel::SETTLED`), y el
      progreso cuenta **solo los obligatorios**.
    - `applies_to` (`client|spouse|dependent|employer|other`) existe porque media lista no es del
      solicitante; sin él «Pasaporte» aparece tres veces y no se sabe de quién es cuál.
    - **No se tocó `cmp_lfl_document_types`**, que sigue siendo el catálogo plano que usan la subida y la
      conversión. La plantilla vive **dentro del editor del servicio**, con rutas POST propias.
    - **`DocumentService::store()` RECHAZA un archivo duplicado** (`ok:false`, `id:0`); no devuelve el
      existente. `Requirements::upload()` mira el hash antes y **enlaza** el documento ya subido, porque
      un mismo PDF puede cubrir dos requisitos y si no el segundo no podría marcarse nunca.
  - **`MatterSteps`** replica el patrón declarativo de `ConversionSteps`: 5 pasos, niveles `ok|warn|error`
    y un evaluador del que beben la pestaña de resumen (vía `partials/_diagnostic`), la guarda de
    radicación y la de cierre. **Añadir un requisito es una entrada ahí.** Radicar solo lo bloquea un
    `error` —hoy, no tener responsable—: los documentos que falten avisan, porque hay trámites que se
    radican con lo que haya.
  - **Tareas y plazos: son las actividades del lead**, con un tipo nuevo `deadline`. No hay tabla
    propia, porque una segunda lista dejaría `/leadflow/actividades` enseñando la mitad del trabajo y
    obligaría a un segundo disparador de vencidas. `next_deadline_at` toma el mínimo entre la actividad
    `deadline` y el requisito con fecha, por `MatterService::refreshDeadline()`, y `leadflow:tick` lo
    repasa una vez por pasada **sin avisar de nada**: de eso ya se encarga `activity_overdue`.
    - **`ActivityModel` duplica la lista de tipos** en `TYPES` y en el `in_list` de `$validationRules`,
      porque `in_list` solo admite un literal. Añadir un tipo exige tocar los dos, o el modelo lo rechaza
      al guardar. Está avisado con un comentario.
  - **Honorarios y pagos.** `cmp_lfl_matter_fees.kind` separa `fee` (ingreso de la firma) de `filing`
    (tasa oficial), `third_party` y `expense`: sin esa distinción el estado de cuenta miente sobre quién
    cobra qué. Los pagos son tabla y no una columna porque un caso se paga a plazos.
    - **No es facturación**: sin impuestos, sin asiento, sin consecutivo fiscal, y **ni una FK ni una
      lectura de `cmp_cont_*`** (contrato §0.4). El estado de cuenta es HTML imprimible, no un PDF.
    - `refreshTotals()` **recalcula desde las filas**, no aplica deltas. Y sus `totalFor()` llevan un
      `where('deleted_at', null)` explícito: `get()` va directo al builder y **se salta el filtro de
      soft-delete** que CI4 sí aplica en `find()`/`findAll()` — sin él, anular un pago no bajaba lo
      cobrado. Lo cazó el autotest.
  - **Pendiente:** el `plan_field` `lfl_docketwise_enabled` y las acciones de automatización
    `open_matter` y `advance_matter_stage` (los tres disparadores ya están declarados).
- **Fuera del MVP:** IA, WhatsApp Business API, calendarios, Meta/Google Ads, API REST pública y webhooks.

### Componente LegalCase (`components/LegalCase`)

Implementa el MVP (P0) del *Documento Maestro LegalCase v1.0*: asunto ≠ proceso, plazos manuales validados,
documentos clasificados y LegalCase Connect. **Independiente**: no lee ni escribe tablas de otros componentes;
convive con los expedientes de LeadFlow sin tocarlos.

- **Acceso por asunto (`Libraries/Access`).** Los permisos `lgc.*` deciden *qué*; `Access` decide *sobre qué
  asuntos*: `visibility=firm` lo ve todo el despacho, `team` solo el responsable, el equipo
  (`cmp_lgc_matter_team`) y quien administra la empresa. Toda lista pasa por `scopeMatters()` y toda ficha por
  `BaseLgc::findMatter()/findChild()`, que resuelven por empresa **y** visibilidad (anti-IDOR).
- **`BaseLgc::_remap()`** hace valer empresa activa y `PlanGuard` en *todas* las acciones internas.
- **Actualizaciones al cliente (`UpdateService`):** `draft → pending_review → approved → published →
  superseded|revoked`. Política por despacho (`cmp_lgc_settings.approval_policy`): `self` (el autor publica y la
  auto-aprobación queda registrada) o `reviewer` (aprueba otra persona). Lo publicado no se edita: se crea
  versión (`supersedes_id`). «Sin novedades» exige fuente y fecha verificadas. Publicar exige confirmar destinatarios.
- **Avisos (`Outbox`):** cola `cmp_lgc_deliveries` con `idem_key` UNIQUE (publicar dos veces no duplica). El
  correo es **mínimo** («ingrese al portal»), sin contenido del caso. Falla → `failed` y `lgc:tick` reintenta
  (máx. 5). Sin consentimiento o cuenta inactiva → `skipped`.
- **Portal (`Libraries/Portal` + `ClientView`):** cuentas **propias** (`cmp_lgc_portal_accounts`), no `users`
  del núcleo. Acceso por enlace de un solo uso `selector.verificador` (en BD solo el SHA-256); el GET muestra un
  botón y el POST consume (los escáneres de correo no gastan el enlace). Sesión aparte (`lgc_portal`) revalidada
  en cada petición, caducidad por inactividad 1 h. `ClientView` es la **única** puerta de datos del portal y
  devuelve listas blancas de campos (nunca `strategy`, `description`, `objectives` ni `title` interno). Solo
  `client`/`representative` pueden tener acceso, y es **por asunto** (`participants.portal_access`).
- **Vistas del portal y correos: autosuficientes** (CSS/SVG en línea, `--lc-*`, modo oscuro por
  `prefers-color-scheme`).
- **Plazos:** registro manual, nacen `to_validate`; política `dual` exige dos personas distintas en los críticos.
  El componente **no calcula términos judiciales**.
- **Documentos:** `writable/uploads/lgc/<empresa>/`, nombre aleatorio, MIME por contenido, SHA-256 y
  deduplicación por asunto, `classification` (`internal|privileged|review|shared`); solo `shared` llega al portal.
  Antivirus: usa `clamdscan/clamscan` si existe en el servidor.
- **Bitácora `cmp_lgc_audit`:** solo inserción (el modelo lanza excepción en update/delete).
- **Esquema:** los hijos del asunto llevan `company_id` indexado pero una sola cascada (desde el asunto) y las
  referencias a `users` van sin FK, para no crear caminos de cascada múltiples (SQL Server los rechaza).
- **Versión 1.1 — controles jurídicos** (salieron de una prueba por roles con criterio legal):
  - **Tres niveles en `Access`:** acceso a la empresa (miembro, o soporte vigente concedido por el despacho:
    `cmp_lgc_support_grants`, solo lectura, auditado; **el Super Admin y el distribuidor NO entran por defecto**),
    ver el asunto, y **trabajarlo** (`canWorkOn`: responsable, equipo salvo `observer`, o administrador).
    `BaseLgc::findMatter()` exige `canWorkOn` en todo POST. `isInsider()` reserva hechos, objetivos, estrategia
    y documentos `privileged` al equipo.
  - **`lgc.share`** (nuevo): compartir documentos, gestionar el acceso al portal y mensajes sin revisión. Sin él,
    la respuesta queda `pending_review` (hilo en `review`) hasta que la apruebe otra persona (`mensajes/revisar`).
  - **Conflicto de interés (`ConflictService`):** al crear asunto, cambiar cliente y añadir parte; mismo contacto,
    documento o nombre normalizado en el lado contrario de otro asunto (cerrados incluidos). Con coincidencias,
    `Result::fail` con `data['conflicts']` y se exige `conflict_ack` + `conflict_note`; evidencia en
    `cmp_lgc_conflict_checks`.
  - **Secreto entre coclientes:** hilos y solicitudes con `account_id`; `is_shared` lo decide el despacho;
    `ClientView::threadsQuery()/requestsQuery()` y `Outbox::notifyMatter(..., $onlyAccounts)`.
  - **Ley 1581:** autorización en el contacto (`consent_*`), aviso de privacidad versionado que el portal exige
    aceptar (`Portal::needsPrivacy()/acceptPrivacy()`), solicitudes de derechos (`threads.kind=data_rights`,
    10/15 días hábiles sin festivos).
  - **Poder del representante** (`participants.authority_*`): sin documento y vigencia no hay portal.
  - **Cierre** con pendientes confirmados; **eliminar** solo vacío o pasada la retención y sin `legal_hold`;
    **reasignación** de trabajo; **alertas internas** de plazos 7/3/1/0 días y de mensajes/entregas
    (`Outbox::notifyStaff`, `kind=staff_*`, sin contenido del caso).
- **Versión 1.2 — gestión de la práctica:**
  - **`Libraries/Calendar`:** festivos de Colombia calculados (fijos, Ley Emiliani y móviles según la Pascua),
    modo `judicial` (además vacancia 20-dic → 10-ene) o `common`, y días propios en `cmp_lgc_calendar_days`.
    El plazo con `starts_on` + `term_days` (hábiles o calendario) **propone** la fecha, deja el cómputo en
    `basis` y sigue `to_validate`. También fija el plazo de las solicitudes de datos personales.
  - **Audiencias (`cmp_lgc_hearings`, `HearingService`):** modalidad, lugar o enlace solo https, tarea de
    preparación, compartir con `lgc.share`; reprogramar re-avisa (`ref_type` con hash de fecha/lugar). El portal
    recibe lista blanca sin `internal_notes`. Alerta interna 36 h antes.
  - **Versiones de documentos:** `version / previous_id / is_current`; el portal y la ficha muestran solo la
    vigente; una nueva versión anula las firmas pendientes de la anterior.
  - **Firma electrónica (`SignatureService`, `cmp_lgc_signatures`):** proveedor `simple` con evidencia (hash del
    documento comprobado al firmar, nombre, documento, fecha, IP, agente y `evidence_hash`); constancia en
    `firmas/evidencia/{id}`. No es firma digital certificada: otro proveedor va por `provider`.
  - **Contrato de servicios (`cmp_lgc_engagements`):** alcance, exclusiones, honorarios, vigencia y documento;
    se envía a firma y pasa a `signed` solo; firmado no se edita.
  - **Capacidad legal** (`contacts.legal_capacity`): un menor o incapaz no accede al portal y su asunto pasa a
    sensible y restringido; actúa el `guardian` con documento acreditado (`ParticipantModel::AUTHORITY_ROLES`).
  - **Derechos del titular (`DataSubjectService`):** exportación JSON y supresión (anonimiza) solo si no hay
    asunto vigente, dentro de la retención o con bloqueo legal.
- **Verificado en PostgreSQL 16** (instalación, `lgc:autotest` 138/138, `lgc:contrato` 42/42, navegador real
  claro/oscuro/390 px). El desborde horizontal a 390 px que se ve en las pantallas internas es de la barra
  superior del núcleo (también ocurre en `/dashboard`).
- **`view()` conserva datos entre llamadas** (`saveData = true`): a `partials/delete_button` se le pasan siempre
  `class`, `icon` y `text`, o heredaría los de la llamada anterior.
- **Fuera del alcance:** IA, WhatsApp, vigilancia judicial, sincronización con calendarios externos, citas, pagos,
  facturación electrónica, firma digital certificada y festivos de jurisdicciones distintas de Colombia.

### Otras piezas del núcleo

- **Auditoría.** El trait `App\Traits\Auditable` se engancha a los callbacks del modelo: rellena `*_by` y guarda
  snapshots en `audit_logs`, filtrando campos sensibles.
  - Usa `getUserAgent()`, que solo existe en HTTP, así que **los modelos auditados no se pueden insertar por
    CLI**. En comandos, usa el Query Builder.
- **Autenticación.** El flujo lo orquesta `AuthController`: login, 2FA, recuperación y registro.
  - Bloqueo tras 5 intentos fallidos, durante 15 minutos (lógica en `UserModel`).
  - Modelos de apoyo: `AuthProviderModel`, `MagicLinkModel`, `TwoFactorAuthModel`, `ApiTokenModel`.
- **API REST `/api/v1/*`.**
  - JWT HS256 nativo en `App\Libraries\JwtService`. No se usa `firebase/php-jwt`, que solo queda como
    dependencia transitiva.
  - Cada token está ligado a una empresa y se revoca borrando su `jti` de `api_tokens`.
  - Los controladores están en `app/Controllers/Api/` y protegen cada acción con `$this->guard('perm')`.
  - Gestor en `/apitokens` y documentación con playground en `/apidocs`.
- **Configuración e integraciones.**
  - `app_settings` guarda pares clave/valor.
  - **La pantalla de Configuración es un único `<form>`**: un POST parcial borra las claves que no envía.
  - Los secretos se cifran en reposo con el marcador `enc:v1:`. Al añadir uno nuevo, súmalo a
    `AppSettingModel::SECRET_KEYS`.
  - Servicios en `app/Libraries/`: `StorageService` (S3/R2), `SesService`, `WhatsAppService` y `AiClient`
    (Anthropic, Gemini y OpenAI, definidos en `AiClient::PROVIDERS`).
  - Las rutas de imagen se resuelven con **`media_url()`**, nunca con `base_url()` directo.
- **Canales por empresa:** tabla `company_channels` + `CompanyChannelModel` (cifrado).
  - `CompanyMailer`, `CompanyWhatsApp` y `CompanySms` envían con la identidad de la empresa.
  - El correo cae al emisor de la plataforma si la empresa no tiene SMTP propio.
- **Cachés por petición**, con su `flush*()` cuando algo cambia dentro de la misma petición: `UserModel`,
  `TenantGuard` (estática; hay que vaciarla también en CLI), `CompanyModel::effectivePlan()`, `AppSettingModel` y
  `ComponentManager`. **Si mutas y vuelves a leer en la misma petición, llama al `flush` correspondiente.**
- **Migraciones del núcleo.**
  - `app/Database/Migrations/` es un baseline consolidado e idempotente. **No edites el baseline**: crea una
    migración nueva.
  - Portabilidad MySQL/PostgreSQL/SQL Server: sin `ENUM` (`VARCHAR` + validación en el modelo), sin `JSON`
    nativo (`TEXT` + `json_encode`) y todo por Forge o Query Builder.

### UI, theming e i18n

- **Tailwind v4.**
  - Fuente en `resources/css/app.css`: tokens en `@theme`, dark mode vía `[data-theme="dark"]` y componentes en
    `@layer components`.
  - **Nunca hardcodees hex/rgb**: usa los tokens. `/theme.css` (`ThemeController`) sobrescribe `--color-primary*`
    en runtime.
  - Estilo «Executive Clean»: Inter, superficies planas, tarjetas blancas sobre un fondo gris
    (`.agent/rules/interfaz-grafica*.md`).
  - Catálogo de clases con ejemplos: `proyectos/guia_componentes_ui.md`.
  - Procedimiento obligatorio: «Reglas de trabajo → 2».
- **Cero CDN.**
  - La tipografía Inter (`public/css/fonts.css` + `public/fonts/inter/`) e intl-tel-input (`public/vendor/iti/`)
    están auto-hospedados.
  - **Iconos:** SVG Lucide servidos por CSS `mask` desde `public/css/icons.css`. Se usan como
    `<i class="icon icon-pencil"></i>`.
  - Para añadir uno, agrega al final del archivo
    `.icon-<alias>{--i:url("data:image/svg+xml;base64,…")}`.
  - **Un `icon-*` inexistente no da error: simplemente no pinta nada.**
- **Ayudas de formulario:** `<input data-phone>` normaliza el teléfono a dígitos con indicativo y sin `+`
  (formato wa.me). `<input type="file" data-preview>` muestra una miniatura.
- **Listados:** `App\Libraries\DataTable` + `partials/datatable` (búsqueda, orden y paginación server-side). Úsalos
  en lugar de un `<table>` estático. Los formularios agrupan campos en `.form-section`.
- **i18n.**
  - `BaseController` resuelve el locale en este orden: sesión → `users.preferred_language` → `es`.
  - Usa `lang('Archivo.clave')`. Los archivos `Language/{es,en}` de cada componente deben ser **simétricos**.
  - Los mensajes de validación de los modelos van en español.
- **Controladores:** extienden `BaseController` y se mantienen delgados; la lógica va a modelos o servicios
  (`Libraries/`). Los modelos devuelven arrays y usan timestamps y soft-delete (patrón de `UserModel`).

## Gotchas transversales

- **`MigrationRunner::regress()` ignora `setNamespace()`** y revierte **todo** el esquema. Para acotar a un
  namespace usa `ComponentManager::regressNamespace()`.
- **`is_unique[...,id,{id}]` falla en `update()`** si el campo va en `$data` sin cambiar. Valida en el
  controlador con el id real y envuelve el update con `skipValidation(true)` / `skipValidation(false)`.
- **`fieldExists()` cachea las columnas.** Si en la misma migración añades una columna y luego un índice sobre
  ella, llama a `$this->db->resetDataCache()` entre las dos cosas.
- **Un soft-delete no dispara `ON DELETE CASCADE`**: las pivotes y los hijos se limpian o desvinculan a mano.
- **`DataTable::query()` + `orLike()`:** el grupo de búsqueda se añade después del callback, y un `orLike` suelto
  deja el filtro de empresa dentro de un OR, lo que filtra datos entre empresas. **El filtro de empresa nunca
  puede quedar dentro de un OR.**
- **El token CSRF rota en cada POST** (`regenerate = true`).
  - Las respuestas JSON deben devolver `csrf_hash()`.
  - El JS debe reponer **siempre** todos los `input[name=csrf_test_name]` tras cada POST por fetch.
  - Si el editor recibe un 403, pide un token nuevo y reintenta una vez.
- **`getJSON()` lanza una excepción** si el cuerpo no es JSON. Mira el `Content-Type` antes de llamarlo.
- **Un `lang()` en el constructor de un modelo** instanciado desde el constructor de un controlador fija el
  idioma por defecto para toda la petición. En `$validationMessages` pon la clave a secas.
- **Un `?>` dentro de un comentario `//` sale de modo PHP.** Si hace falta mencionarlo, usa `/* */`.
- **`esc($hex,'css'|'attr')` produce colores inválidos.** Valida el hex con una regex y emítelo crudo.
- **`[hidden]` pierde contra cualquier clase con `display`:** declara `[hidden]{display:none!important}`.
- **El preflight de Tailwind quita el centrado de `<dialog>`**: añade `m-auto`.
- **Hojas públicas incrustadas en pantallas con Tailwind:** declara explícitas las propiedades que el preflight
  resetea, como `list-style` o `img{display:block}`.
- **Un `<style>` sin cerrar deja el `<body>` vacío.** `curl` no lo detecta; hace falta parsear el DOM.
- **Un array vacío de PHP se serializa como `[]`:** en JS, `style.fill` pasa a ser `Array.prototype.fill`.
  Emite `(object)` para los mapas de propiedades.
- **Pruebas en navegador:**
  - Edge headless reporta `prefers-reduced-motion: reduce` por defecto.
  - `el.click()` se salta los eventos de puntero; lo que se toca con el dedo se prueba con clics de ratón reales.
  - Las suites de navegador que citan notas antiguas no están en el repositorio.
- **`throttler` con una IP en la clave:** el `::1` de IPv6 lleva caracteres reservados, así que hashea la IP.
- **Exportaciones CSV:** neutraliza las celdas que empiezan por `=`, `+`, `-` o `@` (inyección de fórmulas).
- **`getInsertID()` es de la instancia que insertó:** `(new XModel())->getInsertID()` devuelve 0.
- **El título del encabezado del layout admin es `$title`** (por defecto «Dashboard»). Si el controlador no lo pasa,
  todas las pantallas del componente se llaman «Dashboard».
- **`switch-company` exige una suscripción vigente.** Una empresa sin suscripción no se puede seleccionar, y el
  usuario se queda en contexto Global sin ningún aviso visible en las pantallas del componente.
- **Edge sin interfaz no baja de unos 500 px de ancho de ventana:** una captura a 390 px sale recortada, no
  maquetada a 390. Para revisar responsive, usa 520 px o emulación de dispositivo.
