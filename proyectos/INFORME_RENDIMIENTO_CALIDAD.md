# Informe de rendimiento y calidad de código

**Proyecto:** `rupym` (CodeIgniter 4, XAMPP/Windows) · **Fecha:** 2026-08-06
**Alcance:** rendimiento y calidad de código del núcleo (`app/`) y los 6 componentes (`components/`).
Fuera de alcance: seguridad y aislamiento multi-tenant, UX, funcionalidad.

---

## 0. Veredicto en una línea

El código está **bien estructurado y ya optimizado donde duele el SQL** (las cachés por
petición que se añadieron funcionan), pero arrastra **tres deudas de fondo**: el entorno gasta
más tiempo arrancando PHP que ejecutando la app, **no existe ni un solo test propio**, y la
independencia entre componentes se pagó con **~900 líneas de librerías copiadas** que hoy ya
divergen entre sí.

**Lo primero que hay que hacer no toca el repositorio:** activar OPcache. Es una línea en
`php.ini` y vale más que cualquier refactor de esta lista.

---

## 1. Cómo se midió

| Qué | Cómo |
|---|---|
| Volumen de código | `find` + `wc` sobre `app/` y `components/` |
| Arranque de PHP | `Measure-Command` sobre `php.exe`, 2 escenarios |
| OPcache | `function_exists('opcache_get_status')` + `php.ini` |
| Índices | `information_schema` contra `red_bd` (61 tablas) |
| N+1 | `grep` de `->find/->where/->first` dentro de bucles en controladores y librerías |
| Duplicación | `md5sum` + `diff` de las librerías con el mismo nombre |
| Métodos largos | Recorrido `awk` de distancias entre `function` |
| Tests | Inventario de `tests/`, `phpunit.xml.dist` y búsqueda de `CIUnitTestCase` |

**Volumen actual:** 508 archivos PHP · 56.171 líneas (26.291 en `app/`, 29.880 en `components/`).
La base de datos de trabajo está prácticamente vacía (0–275 filas por tabla), así que todo lo
que se diga de índices es **deuda latente**, no un problema que hoy se sienta.

---

## 2. Rendimiento

### 🔴 P0 — OPcache está apagado (el 100 % del problema real de latencia)

**Medición de hoy:**

```
php vacío .................  99 ms
php + autoload + framework  194 ms
opcache ..................  NO cargado
```

`C:\xampp\php\php.ini:964` → `;zend_extension=opcache` (comentado, con todo el bloque
`[opcache]` de las líneas 1800-1870 también comentado).

Sin OPcache, **cada petición recompila el framework entero**: ~95 ms solo de parsear PHP antes
de que la app haga nada. Con 20-25 consultas por pantalla a 7-25 ms de SQL total, el SQL es el
**5 %** del tiempo de respuesta y el arranque el resto. Optimizar consultas mientras esto siga
así es afinar el motor de un coche con el freno de mano puesto.

Síntoma colateral medido: `php-cs-fixer --dry-run` **no termina en 100 segundos** en este equipo.

**Acción (5 minutos, fuera del repositorio):** en `C:\xampp\php\php.ini`

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1      ; en desarrollo
opcache.revalidate_freq=0          ; en desarrollo; en producción: 60 y timestamps=0
```

**Ganancia esperada** (coherente con las mediciones previas del proyecto): páginas de ~390 ms a
~190 ms, `/login` de ~255 ms a ~105 ms. **≈50 % de reducción en todo, sin tocar una línea de código.**

---

### 🟠 P1 — N+1 confirmados en Contabilidad

Tres sitios concretos, todos en la ruta caliente de Comprobantes:

**a) `components/Contabilidad/Controllers/Vouchers.php:45-48` — `itemsWithTaxes()`**

```php
foreach ($items as &$it) {
    $it['taxes'] = $tm->where('item_id', $it['id'])->findAll();   // 1 consulta POR ÍTEM
}
```

**b) `components/Contabilidad/Controllers/Vouchers.php:134-137` — el mismo patrón en `edit()`**

```php
foreach ($items as &$it) {
    $it['other_taxes'] = array_map('intval', array_column($itaxes->where('item_id', $it['id'])->findAll(), 'tax_config_id'));
}
```

Un comprobante de 20 ítems = **21 consultas** donde bastan 2. Es el patrón clásico y la
corrección es mecánica:

```php
$ids  = array_column($items, 'id');
$rows = $ids ? $tm->whereIn('item_id', $ids)->findAll() : [];
$byItem = [];
foreach ($rows as $r) { $byItem[$r['item_id']][] = $r; }
foreach ($items as &$it) { $it['taxes'] = $byItem[$it['id']] ?? []; }
```

**c) `components/Contabilidad/Libraries/CatalogService.php:70-75 y 88-92` — siembras**

```php
foreach (self::DEFAULT_TAXES as ...) {
    if (!$model->where('company_id', $companyId)->where('code', $code)->first()) { ... }
}
```

14 consultas para sembrar 14 impuestos, 25 para el PUC. Se resuelve con **un** `whereIn('code', …)`
previo y comparación en memoria. Es puntual (solo al sembrar), pero es la misma forma de pensar.

**Bien resuelto, como contraste:** `VoucherService::pucName()` (líneas 55-69) sí memoiza el mapa
código→nombre en `$pucMap`, y `SubscriptionModel::getMapForCompanies()` batchea. El patrón
correcto ya existe en el proyecto; solo falta aplicarlo en los tres sitios de arriba.

---

### 🟠 P1 — Índices: falta el que se usa en *todas* las consultas

`deleted_at` **no tiene índice en ninguna de las 61 tablas**, y el soft-delete de CI4 añade
`WHERE deleted_at IS NULL` a **cada** consulta de **cada** modelo. Hoy no se nota porque las
tablas están vacías; con 50.000 comprobantes es un `full scan` por listado.

Lo mismo con `created_at`, que es el `defaultSort` habitual del `DataTable` (ordenar sin índice
= `filesort` de toda la tabla).

**Los índices compuestos que faltan** (el orden importa: primero el filtro de igualdad):

| Tabla | Índice recomendado |
|---|---|
| `cmp_cont_vouchers` | `(company_id, deleted_at, created_at)` · además `(company_id, status)` |
| `cmp_quo_quotes` | `(company_id, deleted_at, created_at)` |
| `cmp_quo_products` | `(company_id, deleted_at)` — crítico: el catálogo pagina sobre esto |
| `cmp_pqr_requests` | `(company_id, deleted_at, created_at)` |
| `cmp_mkt_listings` | `(status, deleted_at)` — el catálogo es global, no filtra por empresa |
| `cmp_dir_entries` | `(tenant_id, deleted_at)` |
| `companies` | `(deleted_at)` y `(parent_id, deleted_at)` |

**Índices simples que faltan y sí importan ya:**

- `api_tokens.company_id` — sin índice, y `ApiAuthFilter` acota por él en cada llamada a la API.
- `cmp_net_profiles.company_id` — sin índice.
- `cmp_mkt_interests.user_id`, `cmp_mkt_listings.user_id`, `cmp_pqr_attachments.user_id` — sin índice.
- `subscriptions.status` y `cmp_cont_payment_requests.status` — sin índice, y se filtra por ellos.

**Cuidado al implementarlo:** el gotcha ya documentado de `fieldExists()` cacheando columnas
aplica igual a los índices. Llamar a `$this->db->resetDataCache()` entre el `ALTER` y la
comprobación, como en `2026-08-06-000001_AddParentIndexToCompanies`.

**Coste:** una migración idempotente por tabla. **Ganancia:** cero hoy, decisiva a partir de
~10.000 filas. Es exactamente el tipo de deuda que conviene pagar **antes** de que haya datos.

---

### 🟡 P2 — El layout hace el trabajo de un controlador

`app/Views/layouts/admin.php` son **30 KB / 559 líneas** con **12 instanciaciones de modelos y
servicios dentro de la vista**:

```
:12   new AppSettingModel()
:73   new UserModel()
:79   new CompanyModel()->isManager()
:84   new TenantGuard()
:213  new ComponentManager()->menuGroups()
:278  new UserModel()
:300  new CompanyModel()->getAccessibleFor()
:301  new SubscriptionModel()
:314  new PlanModel()->find()
:322  new CompanyModel()->tenants()
```

Consecuencias medibles:

- **No se puede cachear ni una parte** del layout: la lógica está entrelazada con el HTML.
- **No se puede testear**: para probar «¿ve el Super Admin el selector de grupo?» hay que
  renderizar la página entera.
- **Se paga en todas las pantallas**, incluidas las que no usan nada de eso.
- Cada `new Model()` en CI4 arrastra construcción de reglas de validación y conexión.

Las cachés por petición (`UserModel::hasPermission`, `TenantGuard`, `AppSettingModel`,
`ComponentManager`) **ya evitan que esto sea un desastre de SQL** — están y funcionan (verificado:
memoización presente en los 5 puntos documentados). El problema que queda no es SQL, es
**arquitectónico**: presentación mezclada con acceso a datos.

**Acción:** un `App\Libraries\Chrome` (o un *view filter* / `BaseController::renderChrome()`) que
calcule **una vez** el paquete del encabezado y la barra lateral —permisos del menú, empresas
accesibles, plan activo, grupos, menús de componentes— y lo pase como array a la vista. El layout
pasa a ser HTML con `foreach`. Beneficio real: testeable, y a partir de ahí **cacheable por
usuario+contexto** (que es donde estaría la ganancia grande de latencia).

---

### 🟡 P2 — Escrituras en peticiones GET (auto-siembra perezosa)

`Vouchers::index()` y `Vouchers::create()` llaman a `VoucherService::ensureTypes($companyId)`,
que **hace INSERT si faltan tipos**. Lo mismo `TypeModel::forCompany()` en PQRSF,
`MarketService::categories()` en Marketplace y `CatalogService::seedTaxes/seedPuc` en
Contabilidad.

Es un patrón cómodo (el componente se auto-configura) pero tiene tres costes:

1. **Cada listado paga una consulta de comprobación** que casi siempre da «ya está sembrado».
2. **Un GET deja de ser idempotente** → imposible poner caché HTTP delante, y dos peticiones
   concurrentes de un usuario nuevo pueden duplicar las semillas (`ensureTypes` ya deduplica por
   nombre, lo cual confirma que el problema se dio en la práctica).
3. Si el proceso se ejecuta por CLI, el trait `Auditable` revienta (`getUserAgent()`), que es la
   nota que ya está en `CLAUDE.md`.

**Acción:** mover la siembra al **momento de la instalación del componente**
(`ComponentManager::install()` ya existe y es el sitio natural) y al **alta de empresa**, y dejar
en la lectura como mucho una comprobación cacheada. La auto-siembra perezosa se queda como red de
seguridad, no como camino habitual.

---

### 🟡 P2 — Búsqueda no *sargable* en `DataTable`

`app/Libraries/DataTable.php:80-85` construye la búsqueda con `like($col, $q)`, que en CI4 genera
`LIKE '%q%'`. Con comodín inicial **ningún índice puede usarse**: es escaneo completo por cada
letra que el usuario teclea, sobre cada columna buscable, unidas por `OR`.

Con las tablas de hoy es gratis. Con un catálogo de «miles de referencias» —que es explícitamente
el escenario de diseño del Catálogo Digital— no lo es.

**Opciones, de menor a mayor esfuerzo:**

1. `like($col, $q, 'after')` (`'q%'`) donde el prefijo baste (códigos, NIT, número de documento) →
   usa índice, coste cero.
2. `FULLTEXT` en las columnas de texto libre (nombre, descripción) con `MATCH … AGAINST`. Rompe la
   portabilidad a los 3 motores, así que iría detrás de una comprobación de driver.
3. Un `search_blob` normalizado (sin acentos, minúsculas) por fila, que además arreglaría que hoy
   buscar «diseño» y «diseno» dé resultados distintos en el listado interno (el catálogo público
   sí normaliza, pero solo en el navegador).

**Lo bueno:** el orden **sí está protegido** (`in_array($sort, $this->sortable, true)` en la línea
88) — no hay inyección por `?sort=`.

---

### 🟢 P3 — Sesión en ficheros sobre Windows

`app/Config/Session.php:24` → `FileHandler` con `savePath = writable/session`.

El handler de ficheros **bloquea el archivo de sesión durante toda la petición**. Cualquier
pantalla que dispare varias peticiones del mismo usuario a la vez —el catálogo público con
`IntersectionObserver` cargando páginas, el autocompletado JSON del cotizador, los «probar
conexión» AJAX— **se serializa**: la segunda espera a que termine la primera, aunque el servidor
tenga CPU de sobra.

**Acción:** en las peticiones de solo lectura que no escriben en sesión, cerrarla pronto
(`session_write_close()` tras leer). A medio plazo, handler de base de datos o Redis. En
desarrollo con un usuario no se nota; en producción con concurrencia real, sí.

---

## 3. Calidad de código

### 🔴 C0 — No existe ni un solo test propio

```
tests/unit/HealthTest.php               ← boilerplate de CI4
tests/database/ExampleDatabaseTest.php  ← boilerplate
tests/session/ExampleSessionTest.php    ← boilerplate
tests/_support/…                        ← boilerplate
```

Cero tests en `components/` (verificado: ninguna clase extiende `CIUnitTestCase` fuera de los
tres ejemplos). Y `phpunit.xml.dist:37` limita la cobertura a `./app` — **los componentes ni
siquiera están en el ámbito de medición**, que son 29.880 líneas, más de la mitad del proyecto.

**Hay que corregir la documentación:** `CLAUDE.md` afirma sobre el snapshot de cliente/vendedor de
las cotizaciones que «*está cubierta por test*». **No lo está.** No hay ningún test de
`QuoteCalculator`, `QuoteService` ni del snapshot. Es la clase de afirmación que, dejada en el
documento que guía el desarrollo, hace que nadie vuelva a mirar ahí.

**Por dónde empezar (orden por riesgo × facilidad):** son todas clases puras, sin HTTP ni sesión,
donde un test cuesta minutos y el fallo es caro:

| Clase | Por qué primero |
|---|---|
| `Servicios\QuoteCalculator` | Dinero. Descuento `%`/`$` topado al subtotal, impuesto sobre base descontada. Réplica literal de un `script.js` — exactamente lo que se rompe sin querer. |
| `Contabilidad\VoucherService::generateEntries` | Partida doble: si no cuadra, el asiento es inválido. Hay reglas (§6.5) y casos de borde (caja vs banco, otros impuestos). |
| `Contabilidad\CashBoxService::availableBalance` | Saldo por reproducción, con arqueo que *fija* en vez de sumar y orden estable. Es un algoritmo, no un CRUD. |
| `Servicios\HtmlSanitizer` | Lista blanca + `on*` + esquemas de `href`. Un test por vector. |
| `Servicios\ProductImporter` | Detección de separador, codificación ANSI/UTF-8 y números `1.250.000`. Es un parser: se test-ea solo. |
| `App\Libraries\TenantGuard` | Ya existe `spark tenant:autotest` con 37 comprobaciones — **convertirlo a PHPUnit** es casi copiar y pegar, y así corre en `composer test`. |

Añadir `components/` a `<source>` en `phpunit.xml.dist` es una línea y debería ir con lo anterior.

---

### 🟠 C1 — ~900 líneas de librerías duplicadas, y ya divergen

El contrato §0.4 dice que un componente no depende de otro, y por eso hay copias. La decisión es
defendible; **el resultado actual no**, porque las copias ya no son copias:

| Librería | Copias | Líneas | `md5` idénticos |
|---|---|---|---|
| `PlanGuard` | **6** | 357 | ninguno |
| `PdfService` | 3 | 153 | ninguno |
| `QrService` | 3 | 116 | ninguno |
| `AttachmentService` | 2 | 251 | ninguno |
| `DemoData` | 3 | — | ninguno |
| `CatalogService` | 3 | — | ninguno |

Los 6 `PlanGuard` hacen **literalmente lo mismo** —resolver `CompanyModel::effectivePlan()`,
mirar una bandera y contar filas contra un cupo— y lo único que cambia es el nombre de la columna
y el modelo que cuenta. El `diff` entre Marketplace y PQRSF son 3 identificadores y comentarios
redactados distinto.

Peor: los comentarios de cabecera de los 6 siguen diciendo «*lee `companies.plan_id` → `plans`*»
cuando el código ya llama a `effectivePlan()` desde que entró el nivel tenant. **Seis sitios donde
corregir el mismo comentario es exactamente el coste que se está pagando.**

**Acción propuesta, sin romper el contrato:** llevar al núcleo lo que ya *es* del núcleo.

- `PlanGuard` → clase base `App\Libraries\PlanGuardBase` configurada por el Manifest (que ya
  declara `plan_flag` y `plan_limits`). El componente hereda o —mejor— ni la escribe:
  `ComponentManager` puede darle una instancia configurada. Ahorro: ~300 líneas y una sola
  definición de «¿el plan lo permite?».
- `PdfService` y `QrService` → **son capacidades de plataforma**, como `AiClient`, `CompanyMailer`
  o `UploadService`, que ya viven en `app/Libraries/` y los componentes usan sin problema. dompdf y
  `chillerlan/php-qrcode` son dependencias de `composer.json` **raíz**, no de cada componente: la
  independencia que protege la copia es ilusoria.
- `AttachmentService` → ídem, parametrizando carpeta, tamaño máximo y tipos.

Lo que **sí** debe seguir copiado es lo que da carácter al componente: `DemoData` y los
`CatalogService`, que son datos semilla propios de cada dominio.

---

### 🟠 C1 — Métodos que hacen demasiado

| Líneas | Método |
|---|---|
| **164** | `Contabilidad\Vouchers::save()` |
| **149** | `SettingsController::update()` |
| **131** | `Servicios\ProductImporter::import()` |
| **128** | `Pqrsf\DemoData::generate()` |
| **103** | `AuthController::processLogin()` |
| **101** | `AuthController::completeLogin()` |
| **100** | `CompanyController::store()` |

`Vouchers::save()` es el caso grave: valida, calcula totales, numera, valida saldo de caja,
persiste ítems, persiste impuestos por ítem, valida el cuadre del asiento o lo auto-genera, y
sincroniza el movimiento de caja. **Ocho responsabilidades en un método privado de un
controlador**, cuando el proyecto ya tiene `VoucherService` para exactamente eso — y de hecho
`VoucherService::createVoucher()` hace una parte de lo mismo por otro camino (el de aprobar una
cuenta de cobro). Son dos rutas hacia el mismo estado, y solo una está en el servicio.

Es también el punto donde la convención propia del proyecto —«*los controladores quedan
delgados*»— se incumple con más claridad.

**Acción:** mover el cuerpo a `VoucherService::saveFromForm()` y dejar el controlador en
validación de entrada + redirección. Es prerrequisito para poder testear el asiento.

---

### 🟡 C2 — Vistas gigantes con JavaScript en línea

| Líneas | Vista |
|---|---|
| 679 | `Servicios/Views/public/catalog.php` (32 KB) |
| 559 | `app/Views/layouts/admin.php` (30 KB) |
| 441 | `Pqrsf/Views/requests/show.php` (25 KB) |
| 328 | `Servicios/Views/public/card.php` |
| 322 | `Contabilidad/Views/vouchers/form.php` |

El JS va **en línea dentro del PHP**. Para las públicas autosuficientes (§7.3 del contrato:
tarjeta digital, vitrina, formularios PQRSF) **es una decisión correcta y hay que mantenerla** —
esas páginas deben funcionar al llevarse la carpeta a otra plataforma.

Para las **internas** no hay justificación: `vouchers/form.php` con 322 líneas de lógica de
cálculo en línea (el «auto-generar» del asiento, que replica §6.5 en el cliente) es código de
negocio duplicado entre PHP y JS, sin caché de navegador, sin posibilidad de CSP estricta y sin
forma de testearlo. `admin.php`, `settings/index.php` y `profile/index.php` están igual.

**Acción:** extraer el JS de las vistas **internas** a `public/js/*.js` con `data-*` para los
parámetros. Se cachea, se puede endurecer la CSP, y el cálculo del asiento pasa a ser un archivo
que se puede comparar con `generateEntries()` del servidor.

---

### 🟡 C2 — Cosas menores pero que se acumulan

- **`app/Views/welcome_message.php` (17 KB) y `Config/Mimes.php` (15 KB)** siguen siendo los de la
  plantilla de CI4. El `welcome_message` no lo usa nadie; borrarlo quita ruido de los inventarios.
- **`firebase/php-jwt` está en `require`** de `composer.json` pero **no se usa**: `JwtService` es
  HS256 nativo por decisión explícita. Solo lo arrastra `thenetworg/oauth2-azure` como
  transitiva → debería moverse fuera de `require` directo, o documentarse en el propio
  `composer.json` por qué está.
- **`aws/aws-sdk-php` completo** para usar S3 y SES. Es el paquete más pesado del `vendor/`;
  `aws/aws-sdk-php` permite instalar solo los servicios necesarios y sin OPcache cada MB de
  autoload se paga en el arranque.
- **`composer test` no tiene `cs`**: el estilo se comprueba a mano. Añadir
  `"cs": "php-cs-fixer fix --dry-run --diff"` a `scripts` cuesta una línea (aunque hoy no termina
  en tiempo razonable — otra víctima de OPcache).

---

## 4. Plan de acción priorizado

| # | Acción | Esfuerzo | Impacto | Riesgo |
|---|---|---|---|---|
| 1 | **Activar OPcache** en `php.ini` | 5 min | 🔥 ~50 % menos latencia en todo | ninguno |
| 2 | Corregir el N+1 de `Vouchers` (2 sitios) | 30 min | alto en comprobantes largos | bajo |
| 3 | Migración de índices `(company_id, deleted_at, created_at)` + los simples que faltan | 2 h | nulo hoy, decisivo con datos | bajo |
| 4 | Tests de `QuoteCalculator`, `VoucherService::generateEntries`, `CashBoxService`, `HtmlSanitizer` + `components/` en `phpunit.xml.dist` | 1-2 días | red de seguridad que hoy no existe | ninguno |
| 5 | Corregir en `CLAUDE.md` la afirmación de que el snapshot está cubierto por test | 5 min | evita decisiones sobre premisa falsa | ninguno |
| 6 | Unificar `PlanGuard` (6→1) y subir `PdfService`/`QrService`/`AttachmentService` a `app/Libraries/` | 1 día | −900 líneas, un solo sitio que tocar | medio (revisar §0.4 del contrato) |
| 7 | Extraer `Vouchers::save()` a `VoucherService` | medio día | testeable; unifica las 2 rutas de creación | medio |
| 8 | `App\Libraries\Chrome` para el layout | 1 día | testeable y luego cacheable | medio |
| 9 | Mover la auto-siembra a la instalación del componente | medio día | GET idempotentes | bajo |
| 10 | JS fuera de las vistas **internas** | 1 día | caché + CSP + fin de la duplicación PHP/JS | bajo |
| 11 | `session_write_close()` en lecturas; a futuro handler en BD | 2 h | concurrencia real | bajo |
| 12 | Búsqueda *sargable* en `DataTable` | 1 día | necesario para el catálogo grande | medio |

**Si solo se hace una cosa: la número 1.** Si se hacen tres: 1, 2 y 4.

---

## 5. Lo que está bien y conviene no tocar

Para que el informe no parezca una lista de defectos:

- **Las cachés por petición funcionan y están bien puestas** (`UserModel`, `TenantGuard`,
  `CompanyModel::effectivePlan`, `AppSettingModel`, `ComponentManager`), con `flush*()` explícito
  y llamado en las mutaciones. Es el trabajo que de verdad bajó las consultas de 50 a 26.
- **`DataTable` valida el orden contra una lista blanca** — no hay inyección por `?sort=`.
- **`SubscriptionModel::getMapForCompanies()`** es el patrón correcto de batching, ya aplicado en
  el sitio más caliente (el selector del encabezado).
- **Portabilidad de las migraciones** (sin `ENUM`, sin `JSON`, todo por Forge) está respetada de
  forma consistente en las 74 migraciones.
- **`ComponentManager::planFormGroups()`** eliminó tres puntos donde un componente obligaba a
  editar el núcleo. Es la dirección correcta y es exactamente lo que falta hacer con `PlanGuard`.
- **El hook `BENCH=1`** de `Config/Events.php` está bien pensado (coste cero sin la variable) y es
  la herramienta con la que habría que verificar cada punto de esta lista después de aplicarlo.
