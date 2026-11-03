# Contrato de Desarrollo de Componentes

> **Qué es este documento.** El "contrato" que **debe** cumplir todo componente para integrarse al núcleo
> (la plataforma `cuentas` en CodeIgniter 4). Un componente es una **aplicación/módulo independiente** que
> se agrega **soltando una carpeta** en `components/`, sin tocar el código, las tablas, las rutas ni los
> permisos del núcleo. Si tu componente cumple este contrato, el núcleo lo **auto-carga y habilita** solo.
>
> **Revisado contra el código el 2026-09-10.** Las versiones anteriores describían piezas que el núcleo nunca
> llegó a implementar (publicación de assets, eventos de ciclo de vida, validación de `requires_core`) y un
> procedimiento de planes que ya no aplica (editar `plans/_form.php`). Donde este documento y el código
> discrepen, manda el código: `app/Libraries/ComponentManager.php` y `app/Config/Autoload.php`.

---

## 0. Principios (no negociables)

1. **No modificarás el núcleo.** Nada de editar `app/`, `system/` ni las tablas base. Toda tu funcionalidad
   vive dentro de la carpeta de tu componente.
2. **Namespace propio.** Tu código vive bajo `Components\<TuComponente>\…`. Nada de clases en el namespace `App\`.
3. **Prefijo de tablas propio.** Todas tus tablas empiezan con `cmp_<code>_`. Nunca crees ni alteres una
   tabla del núcleo. **Única excepción:** añadir **tus propias columnas** a `plans` (§11).
4. **Aislamiento.** No dependas de detalles internos de otros componentes; relaciónate con ellos solo por
   sus servicios públicos, y con el núcleo por sus tablas base vía **FK** (nunca alterándolas).
   Si necesitas una utilidad que el núcleo tiene (o que tú añadirías al núcleo), **lleva tu propia copia**
   dentro de la carpeta (precedente: `QrService`, `PdfService`, `ImageOptimizer`, `HtmlSanitizer`) — ver §15.
5. **Portabilidad.** Tu esquema debe correr en **MySQL/MariaDB, PostgreSQL y SQL Server** (ver §8).

---

## 1. Cómo se habilita un componente (auto-carga)

El núcleo escanea la carpeta `components/`. **Cada subcarpeta que contenga un `Manifest.php` se registra
automáticamente** como el namespace PSR-4 `Components\<NombreDeLaCarpeta>` (lo hace el constructor de
`app/Config/Autoload.php`, único enganche de infraestructura del núcleo). A partir de ahí, CI4 **auto-descubre**
— sin que registres nada —:

- `Config/Routes.php` → tus rutas
- `Config/Services.php` → tus servicios (`service('miServicio')`)
- `Config/Events.php` → tus manejadores de eventos (ver §5: el núcleo no dispara eventos propios)
- `Config/Filters.php` → tus filtros
- `Language/` → tus traducciones
- `Commands/` → tus comandos `spark`

El estado de cada componente vive en `writable/components.json` (`installed` / `active` / `version`):

- **Una carpeta SIN entrada en ese archivo se carga como ACTIVA** (drop-in: soltarla la habilita). Sus rutas
  responden, pero sus tablas y permisos **no existen** hasta que se instale (§13).
- Un componente **desactivado** o **desinstalado** no registra su namespace: sus rutas, servicios, menús e
  idiomas desaparecen y sus datos quedan intactos.
- Desinstalar deja a propósito una entrada `installed:false, active:false`; si se borrara, el Autoload volvería a
  tratar la carpeta como activa.

> El nombre de la carpeta define el namespace: usa **PascalCase** (`Contabilidad`, `Encuestas`), sin espacios
> ni guiones.

---

## 2. Estructura de carpeta

```
components/
  Contabilidad/                 ← = namespace Components\Contabilidad, code "cont"
    Manifest.php                ← OBLIGATORIO (descriptor; su presencia habilita el componente)
    Config/
      Routes.php                ← rutas (prefijo /contabilidad, filtros del núcleo)
      Services.php              ← servicios (opcional)
      Events.php                ← manejadores de eventos (opcional)
      Filters.php               ← filtros propios (opcional)
    Controllers/                ← Components\Contabilidad\Controllers\…
      Asset.php                 ← sirve tus css/js/img (solo si tienes assets, ver §7)
    Models/                     ← Components\Contabilidad\Models\…
    Entities/                   ← (opcional)
    Database/
      Migrations/               ← migraciones propias (namespaced), incluida la que añade columnas a `plans`
      Seeds/                    ← seeders propios (opcional)
    Libraries/                  ← servicios, reglas de negocio, PlanGuard
    Views/                      ← vistas (se referencian como 'Components\Contabilidad\Views\…')
      public/                   ← vistas públicas autosuficientes (§7.3)
    Language/{es,en}/           ← traducciones (OBLIGATORIO, §7.2)
    Commands/                   ← `spark <code>:contrato`, `<code>:autotest`, `<code>:seed` (recomendado)
    Helpers/                    ← helpers (opcional)
    assets/                     ← css/js/img/vendor — NO se publican: los sirve Controllers/Asset.php
```

Mínimo viable: `Manifest.php` + `Config/Routes.php` + `Controllers/` + `Language/{es,en}/`.

> **No existe carpeta `Install/`.** Los permisos y menús se leen **del Manifest**; ningún código del núcleo
> lee `Install/permisos.php` ni `Install/menus.php`.

---

## 3. El `Manifest.php` (contrato de metadatos)

Devuelve un **array literal**. Campos:

```php
<?php
return [
    // --- Identidad ---------------------------------------------------------------------------------
    'code'          => 'cont',                 // OBLIGATORIO. único, 3–8 minúsculas; raíz de tablas, permisos y rutas
    'name'          => 'Contabilidad',         // OBLIGATORIO. nombre visible (respaldo si no hay traducción)
    'name_key'      => 'Cont.compName',        // clave de idioma del nombre (menú, panel, formulario de planes)
    'version'       => '1.0.0',                // OBLIGATORIO. SemVer
    'description'   => 'Comprobantes y cuentas de cobro.',
    'author'        => 'Tu Nombre',
    'icon'          => 'icon-receipt',         // icono del GRUPO en el menú y en el panel (debe existir en icons.css)
    'scope'         => 'company',              // 'company' (por defecto) o 'tenant' (ver CONTRATO_COMPONENTES_TENANT.md)

    // --- Dependencias ------------------------------------------------------------------------------
    'requires_core' => '1.0.0',                // informativo: el panel lo muestra, el gestor NO lo valida
    'depends'       => [],                     // ['otros_codes'] que deben estar INSTALADOS (sí se valida al instalar)

    // --- Esquema, permisos, menús, rutas -----------------------------------------------------------
    'table_prefix'  => 'cmp_cont_',            // OBLIGATORIO. prefijo de TODAS tus tablas
    'permissions'   => [                       // se siembran al instalar (§9). Verbos en INGLÉS del núcleo.
        'cont.view'   => 'Ver contabilidad',
        'cont.create' => 'Crear comprobantes',
        'cont.edit'   => 'Editar comprobantes',
        'cont.delete' => 'Eliminar comprobantes',
    ],
    'menus'         => [                       // se leen en cada petición (§10); no se siembran
        ['label' => 'Comprobantes', 'label_key' => 'Cont.navVouchers', 'icon' => 'icon-receipt',
         'route' => 'contabilidad', 'permission' => 'cont.view', 'order' => 50],
    ],
    'routes'        => ['contabilidad'],       // prefijo(s) de ruta que usas (documental: el gestor no comprueba colisiones)

    // --- Plan (OPCIONAL, solo si tu componente es un servicio que un plan incluye o limita, §11) ---
    'plan_flag'     => 'service_enabled',      // columna booleana de `plans`; en 0 → el núcleo oculta tu menú
    'plan_limits'   => [                       // cupos contados en TU tabla; los pinta el Panel Empresa
        ['column' => 'max_vouchers', 'label' => 'Comprobantes', 'label_key' => 'Cont.limVouchers',
         'table' => 'cmp_cont_vouchers', 'company_column' => 'company_id'],
    ],
    // 'plan_fields' => [ … ],                 // solo si hay cupos que NO se cuentan por empresa (ver §11)
];
```

**Traducción de los rótulos del Manifest.** El Manifest se lee antes de que haya locale, así que sus textos se
escriben en el idioma del componente y funcionan como **respaldo**. Para que el menú, el panel y el formulario de
planes cambien de idioma, declara **la clave al lado** de cada rótulo: `name_key` junto a `name`, `label_key` en
cada ítem de `menus`, `plan_limits` y `plan_fields`. `ComponentManager::t()` resuelve la clave y **cae al
literal** si no existe (un componente inactivo no tiene su archivo de idioma cargado). Las **descripciones de los
permisos** no se traducen: se guardan tal cual en la tabla `permissions`.

**`plan_limits`** lo lee `ComponentManager::planStatus()` y lo pinta el **Panel Empresa** (`/dashboard` con una
empresa activa): una barra por cupo, con el consumo real contado en tu tabla —respetando tu soft-delete si lo
tienes— frente al tope del plan. Si no lo declaras, tu componente sale igual en el panel, solo que como «activo»
o «no incluido» según el `plan_flag`. Decláralo únicamente si tu cupo es **por empresa**: Networking lo omite a
propósito porque el suyo es por usuario y sus tablas no llevan `company_id`.

**Convención `code`:** 3–8 letras, minúsculas, único en toda la plataforma. Es la raíz de tu prefijo de
tablas (`cmp_<code>_`), de tus permisos (`<code>.accion`) y de tu prefijo de rutas. **No lo cambies una vez
instalado:** de él cuelgan tablas y permisos ya sembrados (Servicios cambió de nombre y de rutas, pero conservó
`code => 'quo'` por eso).

**Campos que el núcleo NO lee** (no los declares esperando un efecto): `settings` (no hay pantalla ni API que
lo consuma; guarda tu configuración en una tabla propia `cmp_<code>_settings`) y `parent` en los menús.

---

## 4. Rutas

`Config/Routes.php` del componente (se auto-descubre). **Siempre** bajo tu prefijo y con tu namespace:

```php
<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */

// Admin (requiere sesión + permiso del componente)
$routes->group('contabilidad', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/',                'Vouchers::index',     ['filter' => 'permission:cont.view']);
    $routes->get('nuevo',            'Vouchers::create',    ['filter' => 'permission:cont.create']);
    $routes->post('guardar',         'Vouchers::store',     ['filter' => 'permission:cont.create']);
    $routes->post('eliminar/(:num)', 'Vouchers::delete/$1', ['filter' => 'permission:cont.delete']);
});

// Assets del componente (§7): lista blanca fija en el controlador.
// Decláralos ANTES de cualquier comodín público (:segment), o el nombre del archivo se tomaría por un token.
$routes->get('contabilidad-publico/recurso/(:segment)', 'Asset::serve/$1', ['namespace' => 'Components\Contabilidad\Controllers']);

// Público (sin auth) — sepáralo claramente
$routes->group('contabilidad-publico', ['namespace' => 'Components\Contabilidad\Controllers'], static function ($routes) {
    $routes->get('verificar/(:segment)', 'Verify::show/$1');
});
```

Reglas:
- **Prefijo obligatorio** = tu `code`/route declarada (evita colisiones con el núcleo y otros componentes; el
  gestor **no** las detecta por ti).
- Reusa los filtros del núcleo: **`auth`** (sesión) y **`permission:<perm>`** (RBAC). No reinventes auth. Si tu
  componente es de ámbito tenant, añade **`tenant`** (ver el anexo).
- Separa **admin / público / API**. Las rutas API van bajo `api/v1/<code>/…` y usan el filtro `apiauth`.
- Los **borrados** son **POST** con CSRF (usa el partial `partials/delete_button` del núcleo), nunca `<a>` GET.
- Una ruta pública que identifica un registro usa un **token aleatorio**, no el id: el id se puede recorrer.

---

## 5. Controladores, servicios, eventos

- **Controladores** internos extienden `App\Controllers\BaseController` (locale, helpers `form`/`url`/`media`).
  Los **públicos** pueden extender `CodeIgniter\Controller` (un visitante no tiene sesión ni empresa activa),
  pero entonces el locale no se resuelve solo: hazlo tú desde `session('language')`.
- **Servicios**: declara `Config/Services.php` (`class Services extends BaseService`) y resuélvelo con
  `service('miServicio')`. Se auto-descubre.
- **Eventos: el núcleo NO dispara eventos propios.** No existen `component.installed`, `component.activated`,
  `component.uninstalling`, `login`, `dashboard.render` ni `menu.build`: engancharte a ellos no hace nada. En
  `Config/Events.php` solo puedes usar los eventos **nativos de CI4** (`pre_system`, `post_controller_constructor`,
  `post_system`, `DBQuery`…). Lo que harías «al instalar» va en su sitio:
  - estructura y datos que deben existir siempre → **migraciones** idempotentes;
  - datos de arranque opcionales → un comando **`spark <code>:seed`** idempotente (precedente:
    `paginas:seed`, `formularios:seed`) o un botón en tu panel, **nunca** una escritura escondida en un GET;
  - menús y cupos del panel → el **Manifest** (el núcleo los lee en cada petición).

---

## 6. Modelos, migraciones y seeds

- **Modelos** bajo `Components\<Comp>\Models`, con `$table = 'cmp_cont_vouchers'` (prefijo). Sigue el patrón
  del núcleo: `useTimestamps`, soft-deletes donde aplique, reglas de validación en el modelo, y usa el trait
  `App\Traits\Auditable` si quieres auditoría (ojo: usa `getUserAgent()`, que no existe en CLI).
- **Migraciones** bajo `Components\<Comp>\Database\Migrations`. El gestor las ejecuta **solo para tu namespace**
  al instalar/actualizar (equivale a `spark migrate -n "Components\Contabilidad"`). Cada migración debe ser
  **idempotente** (`if ($this->db->tableExists('cmp_cont_x')) return;`) y tener `down()`.
- **Revertir: nunca con `spark migrate:rollback` ni `MigrationRunner::regress()`.** Ese método ignora
  `setNamespace()` y recorre **todos** los namespaces: revierte el esquema completo de la aplicación. Para
  revertir un componente usa `spark components:uninstall <Carpeta> --drop-data`, que llama a
  `ComponentManager::regressNamespace()` (ejecuta tus `down()` en orden inverso y borra solo tus filas de
  `migrations`).
- Si una migración añade una columna y luego pregunta por ella (o por otra), llama a
  **`$this->db->resetDataCache()`** entre medias: `fieldExists()` cachea los nombres de columna.
- **Mover una migración ya aplicada** a otro namespace obliga a actualizar `namespace` **y** `class` en la tabla
  `migrations`, o CI4 la ejecuta otra vez como nueva.
- **FK al núcleo permitidas** (nunca alteres esas tablas): `users(id)`, `companies(id)`, `roles(id)`.
- **Un soft-delete NO dispara `ON DELETE CASCADE`.** Si tu tabla usa soft-delete y tiene hijas, límpialas a mano
  al borrar.
- **Multi-tenant:** si tus datos pertenecen a una empresa, **incluye `company_id`** y fíltralo siempre por la
  empresa activa (`session('active_company_id')`). Nunca mezcles datos entre empresas, y **nunca dejes el filtro
  de empresa dentro de un `OR`** (un `orLike()` suelto tras el `where('company_id')` enseña datos de otras).

---

## 7. Vistas, assets e idiomas

- **Vistas:** en `Views/`; se referencian con el namespace: `view('Components\Contabilidad\Views\vouchers\index')`.
  Las internas reusan el layout y la UI del núcleo (`layouts/admin`, Tailwind, `partials/datatable`,
  `partials/delete_button`, iconos `icon-*`, helper `media_url()`).
  > Tailwind solo incluye las utilidades que encuentra al compilar: tras añadir clases nuevas en tus vistas hay
  > que correr `composer tailwind` (el `@source "../../components"` ya recoge tu carpeta).
- **Assets: los sirve tu propio controlador; NO se publican.** La copia a `public/components/<code>/` que
  describían versiones anteriores de este contrato **nunca se implementó**: `ComponentManager` no copia nada y
  enlazar ahí da 404. Pon tus archivos en `assets/` y sírvelos con un `Controllers/Asset.php` con **lista
  blanca fija** (el nombre llega por URL: sin lista, `../../.env` sería descargable), `Content-Type` explícito y
  `ETag`. Referencia: `components/Mapas/Controllers/Asset.php`. En la vista:
  `base_url('mapa/recurso/viewer.js')`. **Sin CDN** (política del núcleo: todo self-hosted).
- **Idiomas (OBLIGATORIO, ver §7.2):** `Language/es/…`, `Language/en/…`; usa `lang('<Archivo>.clave')`.

### 7.1 Formularios: orden y agrupación (OBLIGATORIO)

Un formulario largo con campos sueltos hace que el usuario **no sepa dónde registrar cada cosa**. Reglas:

1. **Agrupa por bloques con borde y título.** Usa el componente del núcleo `.form-section` (definido en
   `resources/css/app.css`), nunca un `<h6>` suelto entre campos:

   ```html
   <fieldset class="form-section">
       <legend class="form-section-title">Datos del plan</legend>
       <p class="form-section-hint">Para qué sirve este bloque, en una línea.</p>
       …campos…
   </fieldset>
   ```

2. **Ordena por decisión, no por tabla.** La secuencia sigue cómo piensa el usuario, no el orden de las
   columnas en la BD. Patrón recomendado: *identificación → contenido/presentación → visibilidad y
   privacidad → publicación/estado*. Ejemplo real: en «Mi perfil de red» primero se decide **si** aparezco,
   luego **cómo** me presento y por último **qué** datos de contacto expongo.
3. **Cada bloque lleva una línea de ayuda** (`.form-section-hint`) que explique qué se registra ahí, y cada
   campo no obvio su `.form-hint` (p. ej. «0 = sin límite»).
4. **Marca lo obligatorio** con `<span class="text-danger">*</span>` en el `<label>`, y usa `placeholder` con
   un ejemplo real, no con la repetición de la etiqueta.
5. **Un servicio = un recuadro.** Los cupos de tu componente en el formulario de planes ya salen en su **propio
   recuadro con tu nombre**: lo genera el núcleo desde el Manifest (§11), no lo escribes tú.
6. **Agrupa los checkbox con su explicación** (`.form-check` + texto de ayuda debajo), nunca sueltos al final.

### 7.2 Idiomas (OBLIGATORIO)

El núcleo resuelve el locale en cada request (`es` por defecto, `en` soportado). Tu componente **no puede**
traer texto de cara al usuario incrustado en el código:

- Crea `Language/es/<Archivo>.php` y `Language/en/<Archivo>.php` (mismo nombre de archivo y **el mismo juego de
  claves en ambos**). CI4 los auto-descubre por tu namespace; se usan como `lang('<Archivo>.clave')`.
- Traduce **todo lo visible**: títulos, etiquetas, botones, placeholders, textos de ayuda, estados/badges,
  mensajes de confirmación (`confirm(...)`, escapando con `esc(..., 'js')`), vacíos («no hay registros»), los
  **mensajes de las reglas de negocio** que devuelvan tus servicios y los rótulos del Manifest (`*_key`, §3).
- Los mensajes con datos van con marcadores de posición: `lang('Net.planQuotaLeft', [$quota])`.
- **No llames a `lang()` en el constructor de un modelo** (p. ej. para armar mensajes de validación): corre antes
  de que el `BaseController` fije el locale y deja toda la petición en el idioma por defecto. En
  `$validationMessages` pon la **clave a secas** (`'required' => 'Cont.vrName'`); CI4 la traduce al pintarla.
- Antes de entregar, comprueba que ambos idiomas tienen las mismas claves y que no queda ninguna clave usada
  sin definir.

### 7.3 Vistas públicas: autosuficientes (OBLIGATORIO)

Las pantallas **internas** usan el sistema de diseño del núcleo (Tailwind compilado, `icons.css`, tokens): viven
dentro de la plataforma y deben verse como ella.

Las vistas **públicas** —lo que se comparte con un tercero y lo que uno querría llevarse a otra plataforma— no:
tienen que poder renderizarse llevándose **solo la carpeta del componente**.

- Layout propio dentro del componente, con **toda la hoja de estilos en línea** (nada de `tailwind.css`,
  `icons.css` ni `fonts.css` del núcleo).
- **Iconos SVG en línea** en un partial del componente (p. ej. `Views/public/_icons.php`), no la clase `.icon`
  del núcleo.
- **Tipografía del sistema**; ninguna fuente que descargar. Cero CDN, como en todo el proyecto.
- Tematiza con **variables CSS propias** (`--dc-*`) y `@media (prefers-color-scheme: dark)`, no con los tokens
  del núcleo.
- Los colores que vengan de datos (marca de la empresa) se **validan y se emiten crudos**: `esc($hex,'css')`
  produce `\23 …` y `esc($hex,'attr')` `&#x23;…`, y ninguno de los dos es un color válido.
- Un 404 público **no distingue** entre «no existe», «sin publicar» y «el plan no lo incluye»: distinguirlos
  convierte la URL en una sonda de lo contratado.
- Prueba de fuego: borra del HTML servido cualquier `<link rel="stylesheet">` y la página debe seguir bien.
  Ejemplo de referencia: `components/Servicios/Views/public/_card_layout.php` (tarjeta digital).

---

## 8. Portabilidad a 3 motores (OBLIGATORIO)

El núcleo se despliega en **MySQL/MariaDB, PostgreSQL y SQL Server**. Tu componente **debe** también. Reglas:

**Usa siempre Forge (migraciones) y Query Builder (consultas). No escribas SQL crudo específico de un motor.**

| Prohibido | Usa en su lugar |
|---|---|
| `ENUM` | `VARCHAR(n)` + validación en el modelo |
| `JSON` (tipo nativo) | `TEXT` + `json_encode/decode` en el modelo |
| `SET`, `YEAR`, tipos exclusivos de MySQL | tipos estándar (`VARCHAR`, `INT`, `DECIMAL`, `TEXT`, `DATETIME`) |
| Backticks, `LIMIT x,y`, `IFNULL`, `GROUP_CONCAT`, `RAND()`, `NOW()` en SQL crudo | Query Builder (`->limit()->offset()`, `COALESCE`, `->orderBy('...','RANDOM')`, fechas en PHP) |
| `->query("SELECT … raw MySQL …")` | `->table()->select()->where()…` |

Tipos que **Forge traduce solo** por motor (puedes usarlos): `INT`/`BIGINT` (+`auto_increment`), `VARCHAR`,
`TEXT`, `DECIMAL(p,s)`, `TINYINT` (→ SMALLINT en PG), `DATETIME` (→ TIMESTAMP en PG), `unsigned` (se ignora
donde no aplica). Claves foráneas e índices: decláralos por Forge (`addForeignKey`, `addKey`), no en SQL crudo.

**Dinero** `DECIMAL(15,2)`, **tasas** `DECIMAL(7,4)`, **banderas** `TINYINT(1)`, **fechas** `DATETIME`.

---

## 9. Permisos

- Declara tus permisos en el `Manifest` (`permissions`) con la convención **`<code>.<accion>`**, usando los
  **verbos en inglés del núcleo** (igual que `users.view`, `users.create`…): `cont.view`, `cont.create`,
  `cont.edit`, `cont.delete`, y según necesites `cont.configure`, `cont.export`, `cont.import`.
- El gestor los **siembra** en la tabla `permissions` del núcleo al instalar (con `module` = tu `code`), los
  **concede a Super Admin y Admin**, añade los nuevos al actualizar y los **retira** al desinstalar con
  `--drop-data`. No los siembres tú. Protege cada acción con `permission:<code>.<accion>` en las rutas.
- No inventes tu propio sistema de auth/roles: usa el RBAC del núcleo (`UserModel::hasPermission()`).
- **Un permiso por tipo de acción, y el correcto en cada ruta.** Criterio: `view` para consultar (incluye los
  listados y las fichas de detalle), `create` para dar de alta (**duplicar es crear**), `edit` para modificar
  **y para las acciones que cambian el estado de un registro ajeno** (aprobar, responder, enviar un mensaje),
  `delete` para bajas y cancelaciones. No protejas un POST destructivo con `view`.
- **Ninguna ruta sin `permission:`** dentro de tu grupo, ni siquiera las que "solo muestran" algo.
- **El permiso no sustituye la comprobación de pertenencia.** Tener `net.edit` no autoriza a responder la
  solicitud de otro: el servicio debe verificar además que el registro es de la empresa activa o del usuario en
  sesión. Prueba explícitamente el caso «cambio el id en el POST».

---

## 10. Menús

- Declara ítems en el `Manifest` (`menus`): `label` + `label_key`, `icon` (clase `icon-*` que **exista** en
  `public/css/icons.css` — un icono inexistente no da error, simplemente no se pinta), `route`, `permission` y
  `order`. No hay submenús: `parent` no se lee.
- El núcleo los lee **en cada petición** (`ComponentManager::menuGroups()`) y los agrupa en un `<details>`
  desplegable bajo el **`name`/`name_key` e `icon` del componente**, ordenados por `order`. Un grupo solo se
  renderiza si: (1) el componente está **activo**, (2) su **ámbito** coincide con el contexto (los de empresa no
  salen dentro de un grupo, ni al revés), y (3) el **plan del contexto** tiene tu `plan_flag` activo. Cada ítem,
  además, exige su `permission`. En contexto **Global** (sin empresa) o si la empresa **no tiene plan**, se muestra.
- **No todo va al menú.** Pantallas secundarias (categorías, «nuevo X») pueden entrar desde un botón del
  listado; sus rutas siguen protegidas igual.

---

## 11. Integración con el núcleo

- **Usuarios/empresas/roles:** relaciónate por **FK** a `users`, `companies`, `roles`. Para extender info de un
  usuario/empresa, **crea tu propia tabla** `cmp_<code>_…` con la FK; **no** agregues columnas a las tablas base.
- **Empresa activa:** respeta `session('active_company_id')` y `CompanyModel::getAccessibleFor()` para el
  aislamiento multi-empresa.
- **Relaciones polimórficas:** si necesitas asociarte a "algo" del núcleo, usa una tabla puente propia con
  `(tipo, id)` en vez de tocar el núcleo.
- **Canales de la empresa:** para enviar con la identidad de la empresa activa usa `CompanyMailer`,
  `CompanyWhatsApp` y `CompanySms` del núcleo.

### 11.1 Planes: habilitar el servicio y limitar cupos

Solo si tu componente es un **servicio que un plan puede incluir o no**. Un componente que no deba depender del
plan **omite** todo esto: su gating queda por activación + permisos.

**Lo que entregas** (todo dentro de tu carpeta):

1. **La migración propia** que añade tus columnas a `plans`: una bandera `<algo>_enabled` (`TINYINT`, default 1)
   y, para cupos, `max_<algo>` (`INT`, default 0 = ilimitado). Reglas: **solo columnas propias**, comprueba
   `tableExists('plans')` al entrar (en `up()` y en `down()`), `fieldExists()` antes de cada `addColumn`,
   `resetDataCache()` tras cada `ALTER`, y un `down()` que elimina solo las tuyas. Así `--drop-data` se las lleva
   contigo. Plantilla: `components/Mapas/Database/Migrations/2026-09-02-100005_AddMapLimitsToPlans.php`.
2. **`plan_flag`** en el Manifest → el núcleo oculta tu menú a las empresas cuyo plan la tenga en 0.
3. **`plan_limits`** (cupos que se cuentan por empresa en tu tabla) y/o **`plan_fields`** (solo para el
   formulario: cupos que no se cuentan por empresa, como `max_connections` de Networking, que es por usuario).
   Con esto el **formulario de planes se genera solo** (`ComponentManager::planFormGroups()`), un recuadro por
   componente, y `PlanModel` acepta tus columnas (`planColumns()`).
   > **Ojo:** si declaras `plan_fields`, **sustituye** a `plan_limits` en el formulario y en los campos que se
   > guardan. Si tienes los dos, repite en `plan_fields` las columnas de `plan_limits` o no se podrán editar.
   > Cada entrada admite `column`, `label`, `label_key` y (en `plan_fields`) `hint`.
4. **Un `PlanGuard`** (`Components\<Comp>\Libraries\PlanGuard`) con `serviceEnabled($companyId): bool` y
   `…Left($companyId): ?int` (cupo restante; `null` = ilimitado). Tres reglas:
   - El plan lo resuelve **`CompanyModel::effectivePlan($id)`** (sabe si la empresa cuelga de un tenant), llamado
     tras un `method_exists()` con caída a `companies.plan_id → plans`: en un núcleo anterior a los tenants ese
     método no existe (§15).
   - **Sin plan → permitido e ilimitado. Una columna ausente también es «permitido»** (`!array_key_exists(...)
     → true`): significa «esta instalación no gradúa este servicio», no «está prohibido». Con `!empty()` a secas
     el Mailer quedó apagado para todos cuando faltó su migración.
   - Se aplica **en el servicio/controlador**, en **todas** las vías que consumen cupo (crear, duplicar, importar,
     aceptar, volver a publicar…). Ocultar el botón no impide un POST directo. En tus listados muestra además
     **aviso + cupo** y oculta los botones de alta.
   Plantilla: `components/Mapas/Libraries/PlanGuard.php`.

**Lo que NO haces:** tocar `app/Views/plans/_form.php`, `PlanModel::$allowedFields` ni `PlanController::collect()`.
Versiones anteriores de este contrato lo exigían; desde que el formulario sale del Manifest, editar esos archivos
es romper la regla §0.1. Solo se pintan los componentes **activos** y las columnas que **existen** en `plans`; las
de componentes desactivados se conservan, no se ponen a 0.

### 11.2 Adjuntos (archivos)

Guárdalos **fuera de `public/`** (en `writable/uploads/<code>/`), con una tabla **polimórfica** propia
`cmp_<code>_attachments (owner_type, owner_id, original_name, file_path, size, …)`. La **descarga va por
controlador**, gated por permiso y **aislada por empresa** (verifica que el dueño del adjunto pertenezca a
`active_company_id`). Valida **tipo** (por `getMimeType()`, no por extensión) **y tamaño** (p. ej. 5–8 MB). Para
adjuntar en el **alta**, sube el archivo en el mismo formulario (`enctype="multipart/form-data"`) y guárdalo tras
insertar (con `getInsertID()`).

### 11.3 Compatibilidad futura

Depende solo de las APIs públicas documentadas (modelos, servicios, helpers, filtros del núcleo), no de detalles
internos, y llámalas por su firma más vieja (§15).

---

## 12. Seguridad

- **CSRF** está activo globalmente: todo POST lleva `<?= csrf_field() ?>`. La API (`api/v1/*`) va por JWT.
- **Permisos** en cada ruta/acción/vista sensible. Nunca expongas una acción sin su `permission:`.
- **Valida** toda entrada (reglas en el modelo). **Escapa** toda salida (`esc()`).
- **No** ejecutes código no confiable, no incluyas archivos por ruta dinámica, valida archivos subidos
  (tipo/tamaño), y guarda secretos por el `AppSettingModel` (cifrado en reposo del núcleo).
- Tu componente **no puede** alterar tablas críticas del núcleo. Solo lee/relaciona por FK (salvo tus columnas en
  `plans`, §11.1).

---

## 13. Ciclo de vida y versionado

Todo pasa por `App\Libraries\ComponentManager`, desde el panel `/components` (permiso `components.manage`) o por
consola:

| Acción | Consola | Qué hace de verdad |
|---|---|---|
| **Instalar** | `spark components:install <Carpeta>` | Comprueba que las `depends` estén instaladas, corre tus migraciones (solo tu namespace), siembra tus permisos (y los concede a Super Admin/Admin) y te marca `installed` + `active`. **Re-ejecutable**: sirve para reparar si la BD se recreó. |
| **Activar / Desactivar** | panel | Cambia `active`. Desactivado = sin rutas, menús, servicios ni idiomas; **datos intactos**. |
| **Actualizar** | panel | Corre las migraciones pendientes, siembra los permisos nuevos y registra la `version` del Manifest. |
| **Desinstalar** | `components:uninstall <Carpeta>` | Sin opciones, **conserva** datos y permisos. |
| | `… --drop-data` | Revierte **solo** tus migraciones (`regressNamespace()`, incluida la de `plans`) y quita tus permisos. |
| | `… --purge` | Eliminación total: implica `--drop-data` y **borra la carpeta**. `--force` omite la confirmación. |

**Lo que el gestor NO hace** (versiones anteriores de este contrato decían lo contrario): no valida
`requires_core`, no comprueba colisiones de prefijo ni de rutas, no publica assets, no siembra menús (se leen del
Manifest en cada petición), no dispara eventos y no ofrece exportar antes de desinstalar. Si tus datos merecen
exportarse, ofrece tú la exportación en tu panel.

- **Actualizar:** sube el `version` del `Manifest` y agrega **nuevas** migraciones incrementales (nunca edites
  una migración ya publicada).
- **SemVer** en `version`. Las migraciones son **inmutables** una vez publicadas.

---

## 14. Checklist de conformidad

- [ ] Carpeta en `components/<PascalCase>/` con `Manifest.php` (con `code`, `name`, `version`, `table_prefix`,
      `icon`, y `name_key`/`label_key` en los rótulos).
- [ ] Todo el código bajo `Components\<Comp>\…` (ninguna clase en `App\`), y ninguna dependencia de código que
      tú hayas añadido al núcleo (§15).
- [ ] Todas las tablas con prefijo `cmp_<code>_`; ninguna tabla del núcleo alterada (salvo tus columnas en `plans`).
- [ ] Migraciones **idempotentes**, con `down()`, portables (sin ENUM/JSON/SQL crudo), FKs por Forge.
- [ ] Rutas bajo tu prefijo, con `auth`/`permission:` del núcleo; borrados por POST + CSRF; rutas públicas
      por token aleatorio, no por id.
- [ ] Permisos `<code>.<accion>` en inglés declarados en el Manifest, **el verbo correcto en cada ruta** y
      ninguna ruta sin `permission:`; además, comprobación de **pertenencia** en el servicio (probado alterando
      el id del POST).
- [ ] Menús con `permission` e `icon` existente en `public/css/icons.css`.
- [ ] Multi-tenant: `company_id` + filtro por empresa activa donde aplique (y nunca dentro de un `OR`). Si tu
      componente es **deliberadamente global**, decláralo y justifícalo en el Manifest.
- [ ] **Formularios agrupados** en `.form-section` con título y ayuda, ordenados por decisión y con lo
      obligatorio marcado (§7.1).
- [ ] **i18n completo**: `Language/es` y `Language/en` con las **mismas claves**, cero texto visible incrustado
      —incluidos placeholders, confirmaciones y mensajes de los servicios— y ninguna clave usada sin definir (§7.2).
- [ ] Si es **servicio de plan**, lo de §11.1: migración propia (con `tableExists`/`fieldExists`/`resetDataCache`
      y `down()`), `plan_flag`, `plan_limits`/`plan_fields`, y `PlanGuard` defensivo aplicado **en el servicio**
      en todas las vías que consumen cupo. **Sin tocar `app/`.**
- [ ] **Adjuntos** (si aplica): fuera de `public/`, tabla polimórfica, descarga gated por permiso y aislada por
      empresa, validación de tipo/tamaño.
- [ ] Assets servidos por tu `Controllers/Asset.php` con lista blanca; sin CDN.
- [ ] Vistas internas con la UI del núcleo; vistas públicas **autosuficientes** (§7.3).
- [ ] `spark <code>:contrato` pasa sin incumplimientos (§15.5).
- [ ] Probado en MySQL (mínimo) y revisado contra las reglas de portabilidad.

---

## 15. Llevar un componente a OTRA instalación de la plataforma

Un componente que cumple este contrato tiene que poder **soltarse en cualquier instalación del núcleo**, y no
solo en aquella donde se escribió. Lo que hay que cuidar:

1. **No dependas de código que TÚ acabas de añadir al núcleo.** Es el §0.1 visto desde el otro lado: si tu
   componente estrena una clase en `app/Libraries/`, la instalación de destino no la tiene y revienta con
   «class not found». Si necesitas una utilidad nueva, **llévala dentro de tu carpeta** (precedente:
   `QrService` e `ImageOptimizer`, que Páginas duplica a propósito). El núcleo puede tener su copia para lo
   suyo; tú no la usas.
2. **Llama a las APIs del núcleo por su firma más vieja.** Si un método del núcleo ganó un parámetro nuevo,
   no lo uses: lo que quieres es que el comportamiento **degrade solo** allí donde ese parámetro no existe.
   Ejemplos: Páginas llama a `UploadService::storeImage()` con tres argumentos y optimiza la imagen por su
   cuenta; Mapas llama a `effectivePlan()` solo tras un `method_exists()`.
3. **La diferencia entre plataformas la resuelve el NÚCLEO, no tú.** Hay instalaciones **con tenants** y
   otras **sin ellos**. Tu componente declara su ámbito (`'scope' => 'company'` por defecto, o `'tenant'`) y
   el núcleo decide: el de la instalación sin tenants **omite** los componentes de ámbito tenant en el
   formulario de planes en vez de ofrecer columnas que no existen. Un componente de ámbito **empresa**
   funciona en las dos sin cambiar una línea; uno de ámbito **tenant** necesita, por definición, una
   instalación que los tenga (usa el filtro `tenant` y `session('active_tenant_id')`).
4. **El formulario de planes no se toca nunca.** Los recuadros de servicios los genera
   `ComponentManager::planFormGroups()` leyendo cada Manifest, y solo ofrece **columnas que existan de verdad**
   en `plans`. Un núcleo que aún tenga esos recuadros escritos a mano (versiones antiguas) no puede mostrar un
   componente instalado después **y además revienta al guardar** si alguno de los que lista no tiene sus
   migraciones corridas: hay que portarle `planFormGroups()`, `planColumns()`, el bucle de la vista, el
   `collect()` del `PlanController` y el constructor de `PlanModel`.
5. **Comprueba antes de entregar** con `spark <code>:contrato`: audita el componente contra este checklist
   (escaneando el código **sin comentarios**, vía `token_get_all`, para no contar falsos positivos) e imprime la
   **superficie del núcleo** que necesita — clases, métodos, vistas, filtros, tablas, helpers y sesión—. Esa
   lista es exactamente lo que la instalación de destino tiene que ofrecer. Plantillas:
   `components/Mapas/Commands/MapasContrato.php` (la más completa) y `components/Paginas/Commands/PaginasContrato.php`.

---

> **Referencias vivas** (componentes reales, ya que no existe un componente de ejemplo `Hello`):
> - **`components/Marketplace/`** — el más sencillo de leer: ámbito empresa, CRUD con `DataTable`, `PlanGuard`,
>   migración propia en `plans`, datos demo e i18n completo.
> - **`components/Mapas/`** — `Controllers/Asset.php` (assets con lista blanca), `Controllers/BaseMap.php`
>   (empresa activa, pertenencia y JSON con el token CSRF rotado), `PlanGuard` defensivo, migración de `plans`
>   con `resetDataCache()`, y los comandos `mapas:contrato` y `mapas:autotest`.
> - **`components/Directorio/`** — ámbito **tenant** (ver `CONTRATO_COMPONENTES_TENANT.md`).
> - **`components/Servicios/Views/public/_card_layout.php`** — vista pública autosuficiente.
