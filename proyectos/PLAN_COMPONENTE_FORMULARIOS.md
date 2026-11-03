# Plan — Componente Formularios (constructor de formularios personalizados)

> Origen: análisis de la app autónoma `proyectos/form` («Formulario HV», React 19 + Vite + Tailwind v4,
> generada en Google AI Studio). Este documento contesta dos cosas: **qué hay realmente en esa app** y
> **cómo se convierte en un componente drop-in de la plataforma** sin tocar el núcleo.
>
> Contrato aplicable: `proyectos/CONTRATO_COMPONENTES.md` (ámbito **empresa**, no tenant).

---

## 0. Resumen en cuatro frases

La app fuente es un **constructor visual de formularios de una sola sesión**: todo vive en
`localStorage`, no hay backend, no hay usuarios y no hay más que **un** formulario a la vez. Lo que sí
tiene —y es lo valioso— es un **modelo de datos de formulario bien pensado** (19 tipos de campo, páginas
multi-paso, lógica condicional, anchos de columna, tema) y **cuatro pantallas** que ya definen el
producto: constructor, vista previa, respuestas y personalización. El componente que se propone
(`Formularios`, code **`frm`**) porta ese modelo a la plataforma **cambiando la frontera de confianza**:
allí el navegador decide y guarda; aquí el navegador propone y **el servidor decide, valida y guarda**.
La pieza que hace posible el port sin inventar nada es que **Páginas ya resolvió este problema** para
bloques (registro de tipos + árbol JSON + saneado por reconstrucción + renderizador único + editor
vanilla), y ese patrón se reusa entero.

---

## 1. Análisis de la app fuente (`proyectos/form`)

### 1.1 Lo que es

| | |
|---|---|
| Stack | React 19, TypeScript 5.8, Vite 6, Tailwind v4, `lucide-react`, `canvas-confetti` |
| Backend | **Ninguno**. `express` está en `package.json` pero no se usa |
| Persistencia | `localStorage` (`formcraft_enterprise_schema_v3` y `..._subs_v3`) |
| Alcance | **Un solo formulario**. La clave de storage es global: no hay listado ni «mis formularios» |
| Código | 26 archivos, ~190 KB, 9 componentes React + `types.ts` + `formUtils.ts` + 4 plantillas |

`@google/genai` y `GEMINI_API_KEY` están declarados (herencia de AI Studio, que marca la app con
`MAJOR_CAPABILITY_SERVER_SIDE_GEMINI_API` en `metadata.json`) pero **no se importan en ningún sitio**:
la app no usa IA. `motion` tampoco se usa. Nada de eso se porta — ver §2.8.

### 1.2 El modelo de datos (esto es lo que se porta)

`src/types.ts`, 111 líneas, es el activo real del proyecto:

```
FormSchema { id, title, createdAt, updatedAt, pages[], fields[], settings }
  FormPage    { id, title, description }
  FormField   { id, pageId, type, label, placeholder, helperText, defaultValue,
                required, options[], min, max, step, width, validationRegex,
                validationErrorMsg, conditionalRule, fileConfig, ratingConfig,
                headingLevel, content, alertType }
  FormSettings{ title, description, submitButtonText, successTitle, successMessage,
                redirectUrl, notifyEmail, allowMultipleSubmissions, theme }
  FormTheme   { primaryColor, accentColor, borderRadius, backgroundStyle,
                logoUrl, companyName, showProgressBar, cardStyle }
FormSubmission{ id, formId, submittedAt, data{fieldId→valor}, device, completionTimeSeconds }
```

**19 tipos de campo** (`FieldType`), agrupados como los pinta la paleta:

- *Texto* — `text`, `textarea`, `email`, `number`, `phone`
- *Selección* — `select`, `radio`, `checkbox` (múltiple), `switch`
- *Avanzados* — `rating` (estrella/corazón/pulgar, N máximo), `nps` (0–10 con semáforo),
  `slider` (min/max/step), `date`, `time`, `file`, `signature` (canvas)
- *Estructura* — `heading` (h1/h2/h3), `paragraph` (aviso info/warning/success/note), `divider`

**Lógica condicional**: `conditionalRule { enabled, fieldId, operator, value }` con 7 operadores
(`equals`, `not_equals`, `contains`, `is_checked`, `is_not_checked`, `greater_than`, `less_than`),
evaluada en `evaluateConditionalRule()`. Un campo oculto **no se valida** — decisión correcta y que hay
que conservar.

**Anchos**: `width` `'100' | '50' | '33'`, resuelto con `flex-wrap` + `calc()`.

### 1.3 Las cuatro pantallas

1. **Constructor** (`Palette` + `Canvas` + `PropertiesPanel`) — paleta de 19 tipos con arrastrar-y-soltar
   (`dataTransfer` con el tipo MIME propio `application/formcraft-field-type`), lienzo que pinta el campo
   con el **mismo renderizador** que la vista previa, barra flotante por campo (mover, ancho, duplicar,
   borrar) e inspector de **4 pestañas**: General / Validación / Diseño / Lógica. Cuando no hay campo
   seleccionado, el inspector se convierte en el **gestor de páginas** (crear, renombrar, reordenar,
   duplicar con sus campos, borrar reasignando huérfanos a la primera).
2. **Vista previa** — el formulario real, con dos modos (**asistente multi-paso** con stepper y validación
   por paso, o **página continua**) y tres anchos de dispositivo. Al enviar, guarda la respuesta y lanza
   confeti.
3. **Respuestas** — 4 KPIs, tabla con buscador, detalle en modal, exportación **CSV** y **JSON**, y un
   botón «simular respuestas».
4. **Personalizar** — gestor de páginas otra vez, paleta monocromática, estilo de fondo, barra de
   progreso, textos de envío y de éxito.

Más: **deshacer/rehacer** de 30 pasos con atajos, **4 plantillas corporativas** (CSAT+NPS 2 páginas,
RRHH 3 páginas, cotización B2B, mesa de ayuda TI) y un modal **Compartir/Exportar** con HTML embebible,
JSON de respaldo (con importación) y un «enlace directo».

### 1.4 Defectos encontrados (importan, porque marcan qué NO copiar)

| # | Qué | Dónde | Consecuencia |
|---|---|---|---|
| 1 | La prop del renderizador se llama `isEditMode`, pero quien la usa pasa **`isDesignMode`** | `Canvas.tsx:416`, `LivePreviewView.tsx:486` vs `FieldRenderer.tsx:25` | **El bloqueo de diseño nunca se aplica**: en el lienzo los campos son escribibles y la firma se puede dibujar mientras se maqueta |
| 2 | Identificadores corrompidos con caracteres CJK: `activePageIndex面对`, `duplicateField最佳`, `FormField的的`, `pageId的的`, `isSelected生意` | `Canvas.tsx:55,73,77,129,140,268` | Compila (son identificadores válidos) pero el archivo es ilegible y frágil |
| 3 | `validationRegex`, `min` y `max` se **guardan y no se evalúan nunca** | `LivePreviewView.tsx:70-106` valida solo `required` y el formato de correo | La pestaña «Validación» promete algo que no ocurre |
| 4 | El adjunto guarda **el nombre del archivo**, no el archivo; la firma guarda la cadena literal `'signed_signature_data'` | `FieldRenderer.tsx:519,114` | Los dos campos «Legal/Doc» son decorativos |
| 5 | `generateHTMLSnippet()` interpola etiquetas, contenidos y opciones **sin escapar** | `formUtils.ts:285-377` | El HTML exportado es un XSS a la espera |
| 6 | Métricas inventadas: tasa de finalización fija `'98.2%'`; enlace de compartir apuntando a `forms.enterprise-cloud.internal` | `SubmissionsView.tsx:207`, `ShareExportModal.tsx:36` | Maqueta presentada como dato |
| 7 | `SubmissionsView` recibe `onBackToEditor` que su interfaz no declara | `App.tsx:420` | `npm run lint` (`tsc --noEmit`) falla |
| 8 | `backgroundStyle` ofrece `'boxed'` en la UI, que no existe en el tipo (`clean\|card\|gradient\|minimal\|corporate-blue`) | `SettingsAndThemeView.tsx:257` | Opción que no hace nada |
| 9 | `borderRadius`, `logoUrl`, `cardStyle`, `redirectUrl`, `notifyEmail` existen en el tipo y **no tienen ni UI ni efecto** | `types.ts:71-92` | Campos muertos |
| 10 | Un único formulario por navegador, sin nombres ni listado | `App.tsx:15` | No es multiusuario ni multi-formulario |

**Ninguno de estos defectos se porta.** Los puntos 3, 4 y 5 dejan de ser opcionales al pasar a servidor:
la validación se rehace entera, los archivos se guardan de verdad y la salida la produce un renderizador
que escapa todo.

---

## 2. Decisiones de arquitectura (y por qué)

### 2.1 El formulario ES un árbol JSON, no HTML guardado

Igual que una página de `Paginas`. El esquema (páginas + campos + ajustes) se guarda como JSON en TEXT
(§8 del contrato: nada de JSON nativo, por portabilidad) y el HTML se genera en cada visita con
`Libraries\Renderer`. Consecuencia práctica: un cambio de maquetación, de escapado o de tema se aplica a
todo lo publicado **sin migrar datos**, y el mismo árbol alimenta el lienzo del editor, la página
pública, el PDF y el exportador.

### 2.2 La frontera de seguridad es `FormService::sanitizeTree()`

El árbol que llega del editor **no se limpia: se reconstruye** campo por campo contra `FieldRegistry`.
Un tipo inventado, una propiedad de más, un `headingLevel = 99`, un operador de lógica desconocido o 400
opciones en un desplegable se quedan fuera. Topes duros: **200 campos**, **20 páginas**, **100 opciones
por campo**, **10 reglas por campo**. Es literalmente el patrón de `PageService::sanitizeTree()`, que ya
está probado en producción.

### 2.3 El servidor revalida TODO al recibir una respuesta

Este es el cambio de fondo respecto a la app fuente. `Libraries\Validator` recorre el **esquema
publicado** (no lo que mande el navegador) y comprueba, por campo visible:

- obligatoriedad, con el mensaje propio del campo si lo tiene;
- tipo (correo, número, fecha, hora);
- `min`/`max`/`step` en numéricos y deslizadores;
- `validationRegex` — **compilada de forma defensiva** (ver §7);
- que el valor de un `select`/`radio`/`checkbox` **exista entre las opciones declaradas** (si no, se
  descarta: es la vía por la que se cuelan valores inventados);
- **visibilidad condicional evaluada en el servidor**: un campo oculto no se exige, y el valor que
  llegue para un campo oculto **se descarta** en vez de guardarse.

`Libraries\LogicEngine` es la única implementación de los 7 operadores; el JS del formulario público la
replica para la experiencia en vivo, pero **la verdad es la del servidor** (mismo criterio que
`Renderer::color()` / `hexA()` en Páginas: la regla del navegador y la del servidor tienen que ser la
misma, y si divergen manda la del servidor).

### 2.4 La respuesta congela la etiqueta

`cmp_frm_responses.labels` guarda una **instantánea** `fieldId → etiqueta` y `form_version` el número de
versión del formulario cuando se respondió. Editar «¿Cómo nos calificarías?» después **no reescribe** lo
ya recibido, ni la exportación de hace un mes cambia de encabezados. Es la misma promesa que el snapshot
de cliente/vendedor en una cotización de `Servicios` y que el mapa etiqueta→valor de las submissions de
`Paginas`.

### 2.5 Nada de React

La plataforma no tiene build de Node para el JS de aplicación. El editor es **JavaScript vanilla** en
`assets/js/editor.js`, servido por un controlador `Asset` con lista blanca fija y ETag —exactamente como
`Paginas/Controllers/Asset.php`, porque la publicación a `public/components/<code>/` que describe el
contrato §2 **nunca se implementó en el núcleo**—. Iconos: los `icon-*` del núcleo dentro del panel, SVG
en línea en las vistas públicas.

### 2.6 Frontera con el componente Páginas

`Paginas` ya tiene un bloque `form` (5 tipos de campo, sin lógica, sin páginas) y su tabla
`cmp_pag_submissions`. **No se toca ni se migra**: hay páginas publicadas que lo usan y el contrato
prohíbe que un componente escriba en las tablas de otro. La frontera queda así:

| | Bloque `form` de Páginas | Componente Formularios |
|---|---|---|
| Para qué | Un «contáctanos» dentro de una página | Encuestas, inscripciones, solicitudes, auditorías |
| Campos | 5 tipos, sin validación fina | 19 tipos, validación, lógica, multi-paso |
| Respuestas | Bandeja simple | Analítica, exportación, comprobante, adjuntos |

**Acoplamiento cero**: el componente entrega una **URL pública** y un **fragmento `<iframe>`** que se
pega en el bloque «HTML avanzado» de Páginas. No se declara `depends`, no se añade un bloque a Páginas y
ninguno de los dos necesita al otro para instalarse. (Si más adelante se quiere un bloque nativo, va en
Páginas y guardado tras un `class_exists`, degradando solo — regla §15.2.)

### 2.7 Lo que se copia del núcleo en vez de importarlo (§0.4 / §15.1)

`QrService`, `PdfService`, `Captcha` y `AttachmentService` se llevan **copia propia** dentro de la
carpeta. Ya hay precedente doble: Páginas duplica `QrService` e `ImageOptimizer`, PQRSF duplica
`QrService`/`PdfService`. Del núcleo se usa solo su **superficie estable**: `UploadService::storeImage()`
con **tres argumentos**, `CompanyMailer` / `CompanyWhatsApp` / `CompanySms`, `DataTable` +
`partials/datatable`, `partials/delete_button`, `media_url()` y `CompanyModel::effectivePlan()` **tras un
`method_exists`** (llegó con los tenants; sin esa guarda el `PlanGuard` muere con «Call to undefined
method» en una instalación antigua).

`App\Libraries\AiClient` **no está en esa lista y no lo estará** — ver §2.8.

### 2.8 Sin IA. Ni ahora ni como extensión

Decisión firme del componente, no un alcance diferido: **`Formularios` no llama a ningún modelo de
lenguaje**. Ni Gemini, ni Claude, ni OpenAI, ni el `AiClient` del núcleo. Ni para generar un formulario a
partir de una descripción, ni para resumir respuestas abiertas, ni para clasificar, ni para sugerir
campos. Qué implica, en concreto:

- **Ninguna dependencia de red saliente.** El componente funciona igual con la máquina desconectada de
  internet; lo único que sale del servidor son los envíos por los canales de la empresa (correo,
  WhatsApp, SMS) que el administrador configure a propósito.
- **Ninguna clave de API** en `.env`, en `app_settings` ni en la configuración del componente. No hay
  nada que rotar, que cifrar ni que fugar.
- **Ningún dato de respuesta sale de la plataforma.** Un formulario recoge lo que la gente escribe —datos
  personales, quejas, documentos de identidad, firmas—; mandarlos a un tercero para «resumirlos» es un
  tratamiento que nadie autorizó al responder. La sección de consentimiento del formulario público puede
  afirmar sin letra pequeña que los datos no se comparten.
- **Ninguna cuota, ningún coste variable, ningún fallo prestado.** PQRSF necesita blindarse contra que la
  IA falle porque la usa; aquí ese modo de fallo no existe.
- El campo `ai_*` no aparece en ninguna tabla, y `spark formularios:contrato` **comprueba que no haya
  ninguna referencia a `AiClient`, `anthropic`, `gemini` ni `openai`** en toda la carpeta (escaneando el
  código sin comentarios, como el auditor de Páginas).

Si algún día se quisiera, sería **otro componente** que leyera las respuestas por la API, no un añadido
a este.

---

## 3. Identidad del componente

| | |
|---|---|
| Carpeta | `components/Formularios/` |
| Namespace | `Components\Formularios\` |
| `code` | **`frm`** |
| Prefijo de tablas | `cmp_frm_` |
| Rutas | `formularios` (panel) · `formulario` (público + assets del editor) |
| Ámbito | `company` — cada formulario pertenece a la empresa activa |
| Uploads | `writable/uploads/formularios/<empresa>/` (**fuera de `public/`**) |
| Permisos | `frm.view`, `frm.create`, `frm.edit`, `frm.delete`, `frm.configure`, `frm.export` |
| Plan | `forms_enabled` + `max_forms` + `max_responses` |

`frm` no colisiona con ninguno de los ocho codes existentes (`cont`, `quo`, `mkt`, `pqr`, `net`, `pag`,
`cop`, `dir`) ni el prefijo `formulario` con las rutas ya declaradas.

**Criterio de permisos** (el error más repetido del contrato): `view` para listar y consultar; `create`
para dar de alta y duplicar; `edit` para editar el esquema, **publicar/cerrar** y cambiar el estado de
una respuesta; `delete` para bajas; `configure` para plantillas y ajustes de la empresa; `export` para
descargar el conjunto de respuestas (bajarse todo el histórico es más que verlo en pantalla).

### `Manifest.php` propuesto

```php
'code'          => 'frm',
'name'          => 'Formularios',
'version'       => '1.0.0',
'table_prefix'  => 'cmp_frm_',
'scope'         => 'company',
'icon'          => 'icon-list-check',
'plan_flag'     => 'forms_enabled',
'plan_limits'   => [
    ['column' => 'max_forms',     'label' => 'Formularios', 'table' => 'cmp_frm_forms'],
    ['column' => 'max_responses', 'label' => 'Respuestas',  'table' => 'cmp_frm_responses'],
],
'menus'         => [
    ['label' => 'Formularios',  'route' => 'formularios',                'permission' => 'frm.view',      'order' => 70],
    ['label' => 'Respuestas',   'route' => 'formularios/respuestas',     'permission' => 'frm.view',      'order' => 71],
    ['label' => 'Plantillas',   'route' => 'formularios/plantillas',     'permission' => 'frm.configure', 'order' => 72],
    ['label' => 'Configuración','route' => 'formularios/configuracion',  'permission' => 'frm.configure', 'order' => 73],
],
'routes'        => ['formularios', 'formulario'],
```

> Los iconos tienen que **existir de verdad** en `public/css/icons.css`: un `icon-` inexistente no da
> error, simplemente no pinta nada. Verificar `icon-list-check`, `icon-inbox`/`icon-envelope`,
> `icon-list-columns-reverse` y `icon-gear` antes de fijarlos.

---

## 4. Datos — 7 tablas (portables a los 3 motores, §8)

Sin `ENUM` (VARCHAR + validación en el modelo), sin JSON nativo (TEXT + `json_encode/decode` vía un trait
`JsonColumns` como el de Páginas), todo por Forge, migraciones idempotentes.

### 4.1 `cmp_frm_forms`
`id`, `company_id` (FK `companies`, CASCADE), `title`, `slug` (único **por empresa**),
`public_token` VARCHAR(64) **único** (32 bytes aleatorios en hex — **nunca el id**, para que no se
enumeren los formularios de todas las empresas), `status` (`draft|published|closed`),
`schema` TEXT (el árbol en edición), `published_schema` TEXT (**lo que ven los que responden**),
`version` INT (sube al publicar), `settings` TEXT (tema, textos, notificaciones, consentimiento),
`password_hash` (opcional, `password_hash()` + `throttler`), `opens_at`/`closes_at`,
`max_responses` INT (`0` = sin tope), `allow_multiple` TINYINT, `views` INT,
`created_by`/`updated_by`, timestamps + `deleted_at`.

> **Dos esquemas y no uno** porque editar un formulario vivo mientras alguien lo está respondiendo no
> puede cambiarle las preguntas a mitad. Se edita `schema`; «Publicar» lo copia a `published_schema` y
> sube `version`.

### 4.2 `cmp_frm_responses`
`id`, `form_id` (FK CASCADE), `company_id`, `token` VARCHAR(64) único (el enlace del comprobante),
`reference` VARCHAR(20) (`FRM-000001`, numeración **transaccional** como los comprobantes y los
radicados), `form_version` INT, `status` (`partial|complete`), `data` TEXT (JSON `fieldId → valor`,
fuente de verdad), `labels` TEXT (instantánea `fieldId → etiqueta`), `user_id` (NULL si es público),
`ip`, `user_agent`, `device` (`desktop|tablet|mobile`, derivado en el servidor),
`started_at`, `completed_at`, `duration_seconds`, timestamps + `deleted_at`.

### 4.3 `cmp_frm_answers` — el índice para reportar
`id`, `response_id` (FK CASCADE), `form_id`, `company_id`, `field_id` VARCHAR(64), `field_type`,
`option_value` VARCHAR(190) (para agrupar), `value_text` TEXT, `value_num` DECIMAL(14,4), `value_date`
DATETIME. Se **reconstruye** en cada guardado desde `responses.data`.

> Es la única desnormalización del diseño y se justifica sola: con las respuestas solo en JSON, contar
> «cuántos eligieron la opción B» o promediar un NPS obliga a decodificar cada fila en PHP —lo que hacen
> hoy Páginas y PQRSF, que manejan decenas de registros, no miles—. Con esta tabla el `ReportService`
> hace `GROUP BY` portable. `data` sigue siendo la verdad; `answers` es índice derivado y desechable.

### 4.4 `cmp_frm_attachments`
`id`, `response_id` (FK CASCADE), `form_id`, `company_id`, `field_id`, `original_name`, `stored_name`,
`mime`, `size`, `created_at`. Archivos **privados** en `writable/uploads/formularios/<empresa>/`,
descarga por controlador con permiso **y** doble comprobación de empresa (la fila y su dueño), lista
blanca por `getMimeType()` y tope de 8 MB. Mismo patrón que Copropiedad y PQRSF.

### 4.5 `cmp_frm_templates`
`id`, `company_id` (**NULL = plantilla de arranque, global**), `code` (único para sembrar idempotente),
`name`, `description`, `category`, `schema` TEXT, `is_active`, timestamps.

### 4.6 `cmp_frm_settings` (1 por empresa)
`company_id`, `notify_email`, `captcha_enabled`, `require_consent`, `policy_text`, `policy_version`,
`retention_days` (0 = sin purga), `default_theme` TEXT.

### 4.7 Migración de plan (propia, §11)
`AddFormsLimitsToPlans`: comprueba `tableExists('plans')`, y con `fieldExists()` por columna añade
`forms_enabled` TINYINT default 1, `max_forms` INT default 0 y `max_responses` INT default 0. Su `down()`
se lleva **solo esas tres**. Con el núcleo actual **no se toca `app/Views/plans/_form.php`**: los
recuadros los genera `ComponentManager::planFormGroups()` desde el Manifest.

> ⚠️ Si en la misma migración se añade una columna y luego se pregunta por ella, `fieldExists()` devuelve
> la lista **de antes** del `ALTER`: hay que llamar a `$this->db->resetDataCache()` entre medias (así se
> perdió en silencio el índice `companies_parent_id`).

### Limpieza que el soft-delete NO hace

Un soft-delete **no dispara `ON DELETE CASCADE`**. Borrar un formulario tiene que arrastrar a mano sus
respuestas, sus `answers` y sus adjuntos (y borrar los archivos del disco); borrar una respuesta, sus
`answers` y sus adjuntos. Se hace en `FormService::deleteForm()` / `ResponseService::deleteResponse()`,
nunca en el controlador. Precedentes: `Boards::delete()` en Directorio y `RegistryService` en Copropiedad.

---

## 5. Piezas de código

```
components/Formularios/
  Manifest.php
  Config/Routes.php
  Commands/            FormulariosAutotest.php · FormulariosContrato.php · FormulariosSeed.php
  Controllers/         Forms.php · Editor.php · Responses.php · Templates.php · Settings.php
                       PublicForm.php · Asset.php
  Database/Migrations/ 7 migraciones (6 tablas + plan)
  Language/{es,en}/    Frm.php  (simétricos)
  Libraries/           FieldRegistry.php ★ · FormService.php ★ · Renderer.php ★ · Validator.php ★
                       LogicEngine.php · ResponseService.php · ReportService.php · ExportService.php
                       AttachmentService.php · Captcha.php · QrService.php · PdfService.php
                       PlanGuard.php · TemplateLibrary.php · DemoData.php
  Models/              FormModel · ResponseModel · AnswerModel · AttachmentModel
                       TemplateModel · SettingModel · JsonColumns (trait)
  Views/               index · editor · _editor_layout · responses · response_show · report
                       templates · settings
                       public/{form,done,receipt,_layout,_styles,_icons,not_found}.php
                       pdf/{response,form}.php
  assets/              js/editor.js · js/form.js · css/editor.css
```

★ = las cuatro piezas que sostienen el diseño.

**`FieldRegistry`** es la **única definición** de qué tipos de campo existen y qué propiedades tiene cada
uno. De ahí beben las cinco piezas —paleta e inspector del editor (vía `forJs()`, ya traducido),
`Renderer`, `Validator`, `ExportService` y el saneador—. **Añadir un tipo de campo es una entrada aquí y
un `case` en `Renderer`**, nada más.

**Propiedades comunes a todo campo** (el equivalente a los *block supports* de Páginas, declaradas una vez
en `COMMON_FIELDS`): `label`, `helperText`, `required`, `width`, `defaultValue`, `conditionalRule`,
`anchor`. Así una propiedad nueva la heredan los 19 tipos sin tocarlos uno a uno.

---

## 6. Pantallas y rutas

### Panel (grupo `['auth']`, cada ruta con su `permission:frm.*`)

| Ruta | Permiso | Qué |
|---|---|---|
| `GET formularios` | `frm.view` | Listado con `DataTable` (estado, respuestas, última, cupo del plan) |
| `POST formularios/nuevo` | `frm.create` | Alta **solo POST** (mutación, con CSRF), entra directo al editor |
| `GET formularios/editor/{id}` | `frm.edit` | **Editor a pantalla completa**, layout propio |
| `POST formularios/editor/{id}` | `frm.edit` | Guardar el árbol (JSON + `X-CSRF-TOKEN`); devuelve el árbol **ya saneado** |
| `POST formularios/editor/{id}/ajustes` | `frm.edit` | Ajustes del formulario (pestaña «Formulario» del inspector) |
| `GET formularios/token` | `frm.edit` | Token CSRF fresco para el reintento del editor (§7) |
| `POST formularios/publicar/{id}` · `/cerrar/{id}` | `frm.edit` | Publicar (copia a `published_schema`, sube `version`) / cerrar |
| `POST formularios/duplicar/{id}` | `frm.create` | Duplicar (consume cupo) |
| `POST formularios/eliminar/{id}` | `frm.delete` | Baja (partial `delete_button`, **nunca un `<a href>`**) |
| `GET formularios/{id}/respuestas` · `/respuesta/{rid}` | `frm.view` | Listado y detalle |
| `GET formularios/{id}/informe` | `frm.view` | Analítica por campo |
| `GET formularios/{id}/exportar/{csv\|json}` | `frm.export` | Exportación |
| `GET formularios/adjunto/{id}` | `frm.view` | Descarga privada, con doble comprobación de empresa |
| `formularios/plantillas…` · `formularios/configuracion` | `frm.configure` | Plantillas y ajustes |

### Público (grupo aparte, **sin `auth`**, CSRF activo)

| Ruta | Qué |
|---|---|
| `GET formulario/{token}` | Responder. Solo si `published`, dentro de ventana, con cupo y **con el plan habilitado** |
| `POST formulario/{token}` | Enviar (captcha + honeypot + `throttler` + validación completa) |
| `GET formulario/r/{responseToken}` | Comprobante de la respuesta (+ QR) |
| `GET formulario/r/{responseToken}/pdf` | Comprobante en PDF |
| `GET formulario/recurso/{archivo}` | Assets del editor y del formulario, **lista blanca fija** |

El 404 público **no distingue** «no existe», «está en borrador», «cerrado» y «el plan no lo incluye»: si
lo distinguiera, sería una sonda de lo que tiene contratado cada empresa (misma decisión que Páginas).

### El editor

Va **a pantalla completa** con lienzo propio (`Views/_editor_layout.php`), como el de Páginas y por el
mismo motivo: con la barra lateral y el encabezado del panel, las tres columnas dejan para el formulario
una tira demasiado estrecha para juzgar una maquetación. El layout **sí** carga el sistema de diseño del
núcleo (la autosuficiencia del §7.3 es solo para vistas públicas).

- **Izquierda** — dos pestañas: *Campos* (los 19 en una rejilla, ordenados por afinidad) y *Estructura*
  (cascada página → campos, con arrastrar para reordenar y clic para seleccionar; es la vía para llegar a
  un campo enterrado).
- **Centro** — lienzo con zonas de soltado intercaladas, que **incrusta la misma hoja de estilos que el
  formulario público** (`Views/public/_styles.php`), así lo que se ve al maquetar es lo que se publica.
- **Derecha** — inspector con las 4 pestañas de la app fuente (General · Validación · Diseño · Lógica)
  más una quinta, **Formulario**, con título, dirección, ventana de fechas, tope de respuestas,
  contraseña, notificaciones, textos de éxito y tema. **Un solo botón de guardar** en la barra: guarda
  primero los ajustes y después el árbol (la lección de la v3.8.2 de Páginas, donde había dos y el título
  se perdía).
- **Las dos columnas se pliegan**, con la preferencia en `localStorage`.

---

## 7. Riesgos y gotchas concretos (con su antídoto)

| Riesgo | Antídoto |
|---|---|
| **El token CSRF rota en cada POST** y el editor lo lleva en memoria: basta otra pestaña para que todos los guardados den 403 con HTML | `enviar()` pide un token fresco a `GET formularios/token` y **reintenta una vez**; `leeJson()` no revienta si el cuerpo no es JSON. Copiado de Páginas |
| **Las claves numéricas de un `select` se vuelven enteros**: `in_array('2', array_keys(...), true)` es falso siempre | `array_map('strval', …)` en `FieldRegistry::optionValues()` y en el saneador. Es el bug que hacía que un título de nivel 1 saliera como h2 en Páginas |
| **El reset de Tailwind entra en el lienzo** (`ul{list-style:none}`, `img{display:block}`) | Toda propiedad que Tailwind resetee se declara explícita en `_styles.php` |
| **Regex del usuario**: patrón inválido o catastrófico | Al **guardar**, validar con `@preg_match($re, '') !== false` y rechazar; al **validar**, tope de longitud de entrada y `preg_last_error()` comprobado. En el navegador, `new RegExp` dentro de `try/catch` |
| **La firma y el adjunto llegan del navegador** | La firma se decodifica del data-URI, se comprueba que sea PNG y se guarda como archivo; el adjunto pasa por lista blanca de `getMimeType()` y tope de tamaño. Ambos **fuera de `public/`** |
| **El formulario público es un imán de spam** | Captcha propio (GD, hash en sesión, un solo uso) + honeypot + sello de tiempo firmado con HMAC + `throttler` (5/min por token) + `closes_at` + `max_responses` |
| **Ocultar el botón no es control** | Plan, cupo, ventana y pertenencia se aplican **en el servicio**. Probar explícitamente «cambio el id/el token en el POST» |
| **`MigrationRunner::regress()` ignora `setNamespace()`** | Nunca llamarlo; el desinstalado va por `ComponentManager::regressNamespace()` |
| **El trait `Auditable` usa `getUserAgent()`** | Los datos demo y los autotest insertan por Query Builder, no por el modelo auditado |
| **`esc($hex,'css')` produce colores inválidos** | El color de marca se valida con regex y se emite **crudo**; la tinta se decide por luminancia |
| **Un `?>` dentro de un comentario `//` sale de modo PHP** | Si hay que mencionar un cierre de etiqueta, va en bloque `/* */` |
| **Miles de respuestas** | El listado pagina en servidor (`DataTable`), la analítica agrega en SQL sobre `cmp_frm_answers` y el PDF de respuestas va acotado (`PDF_MAX`) |

## 8. Estado: IMPLEMENTADO (v1.0.0, 2026-08-31)

Las seis fases están construidas, instaladas y probadas en esta instalación. Lo que sigue en §8.b es el
roadmap tal como se planteó; queda como registro de por dónde se fue.

**Comprobación reproducible:**

```powershell
C:\xampp\php\php.exe spark components:install Formularios
C:\xampp\php\php.exe spark formularios:seed        # 4 plantillas de arranque
C:\xampp\php\php.exe spark formularios:autotest    # 265 comprobaciones, 0 fallos
C:\xampp\php\php.exe spark formularios:contrato    # 988 comprobaciones, 0 incumplimientos
```

**Lo que se verificó sirviendo de verdad** (no solo con pruebas de unidad): alta con la presentación
elegida, editor cargando registro y esquema, guardado por JSON con el token de CSRF ya rotado,
publicación, formulario público en las dos presentaciones, envío con el captcha mal puesto (rechazado y
conservando lo escrito), envío válido, adjunto y firma guardados como archivos reales fuera de
`public/`, descarga con permiso y empresa comprobados, comprobante web y PDF, informe con NPS y
promedios calculados, exportación CSV con BOM y etiquetas congeladas, cierre del formulario, plan
apagado y cupo agotado, aislamiento entre empresas, y desinstalación con `--drop-data` retirando
**exactamente** sus 6 tablas y sus 3 columnas de `plans` (36 → 33) sin tocar las de los otros ocho
componentes.

### Doce defectos que encontraron las pruebas

Se listan porque cada uno explica una decisión del código y ninguno se habría visto leyendo:

1. **El ancho caía en una clave inexistente.** El patrón «comprobar con `?? defecto` y luego leer la
   clave» lee algo que puede no existir: el valor por defecto pasaba la comprobación y el acceso
   posterior reventaba. Estaba cuatro veces (ancho, icono de calificación, nivel de encabezado y tono de
   la nota). De ahí sale `Renderer::pick()`.
2. **La clave del throttler moría en IPv6.** `::1` lleva dos puntos, que son caracteres reservados en
   las claves de caché de CI4: el POST público daba 500 antes de validar nada. La dirección va hasheada.
3. **Los ids cortos se descartaban en silencio.** El mínimo de 4 caracteres tiraba `p_rh1`, y con la
   página se iban sus campos a la primera: tres de las cuatro plantillas de arranque perdían sus pasos.
   Lo que hace seguro un id es su juego de caracteres, no su longitud.
4. **En «todo en una página» las páginas siguientes quedaban ocultas.** El renderizador las escondía
   siempre y el JavaScript solo las muestra cuando hay asistente: media encuesta era inalcanzable.
5. **El radicado salía del autoincremento global.** La primera respuesta de un formulario nuevo se
   numeraba `FRM-000027`. Ahora el consecutivo es por formulario y arranca en 1.
6. **`esc($x,'attr')` convertía `f[campo]` en `f&#x5B;campo&#x5D;`.** Funciona (el navegador decodifica
   las entidades), pero deja un HTML que no se puede auditar de un vistazo. El id se valida y se emite
   crudo, igual que el color de marca.

7. **Un `</style>` olvidado dejaba la página entera en blanco.** El contenido de `<style>` es CDATA: sin
   su cierre, el navegador se traga como CSS todo lo que viene después y **el `<body>` llega vacío al
   DOM**. Afectaba al formulario público *y* al editor, que incrusta la misma hoja, y se veía como «una
   pantalla en gris». Lo reportó el usuario abriéndolo en un navegador.

8. **Publicar perdía la publicación por el token de CSRF.** «Publicar» guarda primero (dos POST, que
   **rotan** el token) y después enviaba el formulario con el que había pintado el servidor: 403 «The
   action you requested is not allowed» en `Security.php:262`. Ahora se repone el token vigente antes de
   enviar. Verificado contra el servidor real: con el token viejo **403**, con el nuevo **303**.

9. **Dos campos al 1/2 no compartían fila en el lienzo.** El ancho se aplicaba bien, pero entre cada dos
   campos se pintaba una **zona de soltado a todo el ancho** (`flex: 1 1 100%`) que los empujaba a filas
   distintas. En la página publicada —donde no hay zonas de soltado— sí funcionaba, así que mis
   comprobaciones sobre el HTML servido no lo veían. Ahora el destino lo decide el propio campo según por
   qué mitad se suelte (con un filete en ese lado) y solo queda una zona al final.

10. **Publicar seguía fallando cuando no había cambios pendientes.** El arreglo del punto 8 reponía el
    token, pero el atajo «si no hay nada que guardar, deja pasar el envío» se saltaba esa reposición — y
    para entonces los guardados anteriores ya lo habían rotado. Ahora se repone siempre.

11. **El diálogo «Nuevo formulario» salía pegado a la esquina.** Dos causas encadenadas. La primera:
    **nunca corrí `composer tailwind`**, así que las utilidades que solo usa este componente
    (`sm:grid-cols-2`, `backdrop:bg-black/40`, `max-h-[85vh]`…) no estaban en el CSS compilado —el
    `@source "../../components"` sí las recoge, pero hay que reconstruir, y está escrito en CLAUDE.md—.
    La segunda, la de fondo: el *preflight* de Tailwind pone `margin: 0` a **todo**, y eso anula el
    `margin: auto` con el que el navegador centra un `<dialog>` modal. Medido: sin `m-auto`, la caja
    queda en `top=0 left=0`; con ella, centrada.

12. **En el paso 1 se veían «Anterior» y «Enviar».** El servidor los marca con `hidden`, pero
    `.ff-btn{display:inline-flex}` **gana** al `[hidden]` del navegador, porque una regla de autor
    prevalece sobre la del agente de usuario. Ahora las dos hojas declaran
    `[hidden]{display:none !important}`. En el editor pasaba lo mismo con el aviso de «ponle nombre»,
    que usa la clase `.alert` del núcleo.


**El séptimo enseña algo que los demás no**: lo encontró él y no yo, porque **verifiqué los bytes en vez
del renderizado**. Todas mis comprobaciones eran `grep` sobre la respuesta de `curl`, y el marcado *estaba*
ahí —`data-ff-form`, los nombres de los campos, los dos pasos—: lo que faltaba era que alguien lo parseara.
El noveno remató la lección: el ancho se guardaba bien y el HTML era correcto, pero **el layout** no hacía
lo que decía. De ahí los tres niveles de verificación de abajo.

**Del 1 al 8 tienen comprobación automática** en `formularios:autotest` y `formularios:contrato`, así que
no pueden volver sin avisar. **Del 9 al 12 no**: son de disposición y de navegador, y comprobarlos exige
medir el layout y pulsar botones de verdad —lo que hace el nivel 3 de aquí abajo, que vive fuera del
repositorio porque meterle un navegador al componente rompería su regla de cero dependencias—. Quedan
descritos para poder repetirlos a mano.

**Los cinco últimos son todos de lo mismo**: el servidor emitía lo correcto y el navegador hacía otra
cosa —el `<body>` que no se parseaba, el token ya rotado, la fila que no se repartía, el diálogo sin
centrar y los botones que no se ocultaban—. Ninguno se ve en los bytes. Por eso la regla de trabajo ahora
es: **si el cambio se ve en pantalla, se mira en un navegador antes de darlo por hecho**, y si toca
clases de Tailwind, **se recompila** (`composer tailwind`) — está escrito en CLAUDE.md y me lo salté.

### Cómo se verifica lo que se ve (y no solo lo que se sirve)

Tres fallos seguidos —la pantalla en gris, el 403 al publicar y la fila que no se repartía— tenían algo en
común: **ninguno se veía en los bytes**. El HTML era correcto y `curl` los daba por buenos. Desde el
noveno, la verificación de pantalla es de tres niveles y está en el guion de trabajo:

1. **Bytes** (`curl` + `grep`): que la ruta responda y el marcado esté.
2. **DOM** (jsdom sobre el HTML servido, ejecutando `editor.js`): que la página **parsee** y que el
   JavaScript pinte lo que debe. Aquí se cazó el `</style>` que dejaba el `<body>` vacío.
3. **Navegador de verdad** (puppeteer-core sobre el Edge del sistema): que el **layout** sea el que se
   espera —`getBoundingClientRect()` de dos campos al 1/2 tiene que dar el mismo `top`— y que los botones
   hagan lo suyo de punta a punta, publicación incluida. Sin esto no se puede afirmar que algo «se ve
   bien»: solo que se emitió bien.

⚠️ Dos trampas del entorno que costaron tiempo: **jsdom con `fetch` sin simular alcanza el XAMPP del
puerto 80** y escribe de verdad en la base; y **un puerto ocupado por un `php -S` viejo responde con OTRO
proyecto** (`/formularios` daba 404 servido por `htdocs/inmobiliaria`), así que hay que comprobar que
quien contesta es esta aplicación antes de creerse un fallo.
servido, que además ejecuta `editor.js` y cuenta lo que pinta), y `formularios:contrato` comprueba el
**equilibrio de `<style>` y `<script>`** en todas las vistas —comprobación que se verificó quitando el
cierre a mano para ver que falla, porque una prueba que no puede fallar no prueba nada—.

Los ocho tienen ahora su comprobación automática, así que no pueden volver sin avisar.

### Paridad con el lienzo de la app de origen

Repasado botón por botón contra `Canvas.tsx`, `PropertiesPanel.tsx` y `Navbar.tsx`, faltaban ocho cosas
que sí tenía la app base. Están todas, y probadas en un DOM real (jsdom ejecutando `editor.js`):

| Lo que tenía la app base | Estado |
|---|---|
| Ancho por campo (100/50/33) **en la barra del campo** | ✅ `1/1` · `1/2` · `1/3`, que es lo que permite **varios campos por fila** |
| Número de campos por página y en total | ✅ en cada pestaña y bajo el lienzo |
| Renombrar la página y su descripción | ✅ junto al lienzo, no escondido en una pestaña |
| Reordenar páginas | ✅ mover a izquierda / derecha |
| **Duplicar página con sus campos** | ✅ |
| «Ver todas» las páginas a la vez | ✅ con el número de paso en cada campo |
| Página del campo desde el inspector | ✅ con «crear una nueva y mover el campo» |
| Título y descripción del formulario en el lienzo | ✅ |
| Deshacer / rehacer (30 pasos, Ctrl+Z / Ctrl+Y) | ✅ con apunte retrasado 400 ms |
| Vaciar el formulario | ✅ |
| Exportar / importar el esquema JSON | ✅ diálogo «Código», copiar y pegar el árbol |
| Vista previa por dispositivo | ✅ abre la página **real** a 820 / 390 px |

**Lo que sigue fuera, a propósito:** el confeti al enviar (una dependencia para un guiño), el «simular
respuestas» por formulario —hay datos demo a nivel de componente, que crean dos formularios y catorce
respuestas— y los ajustes de tema `borderRadius`, `cardStyle` y `backgroundStyle`, que en el original
**no tenían ni interfaz ni efecto** (defecto 9 del §1.4): portarlos habría sido portar campos muertos.

## 8.b Roadmap por fases (como se planteó)


Cada fase es **entregable y verificable por sí sola**. El orden no es negociable: cada una fija
decisiones que la siguiente da por hechas (el orden del contrato: Manifest → migraciones → modelos →
rutas → servicios → vistas e i18n).

### Fase 0 — Andamiaje (½ día)
Manifest, las 7 migraciones, modelos con `JsonColumns`, rutas con sus permisos, `Language/{es,en}/Frm.php`
arrancado, CRUD mínimo del formulario (título, dirección, estado) con `DataTable`.
**Verificación:** `spark components:install Formularios` deja el menú, los permisos sembrados y el listado
en pie; `components:uninstall Formularios --drop-data` se lleva **sus 6 tablas y sus 3 columnas de
`plans`** y ninguna otra.

### Fase 1 — El vertical que ya sirve (2–3 días)
`FieldRegistry` con los 19 tipos, `FormService::sanitizeTree()`, `Renderer`, `LogicEngine`, `Validator`,
`ResponseService`, formulario público autosuficiente y respuesta guardada. **Sin editor todavía**: el
esquema se carga desde una plantilla o pegando JSON.
**Verificación:** un formulario sembrado se responde desde el móvil y la respuesta queda completa,
validada en servidor, con su referencia `FRM-000001`.

### Fase 2 — Editor (3–4 días)
`_editor_layout`, paleta, lienzo con arrastrar-y-soltar, inspector generado desde el registro, cascada de
estructura, deshacer/rehacer, guardado sin recargar con reintento de CSRF, «Publicar».
**Verificación:** montar de cero la plantilla CSAT+NPS sin escribir JSON.

### Fase 3 — Multi-paso y lógica (1–2 días)
Gestor de páginas (crear, renombrar, reordenar, duplicar con campos, borrar reasignando huérfanos),
asistente con stepper y validación por paso, pestaña Lógica con los 7 operadores, anchos 100/50/33.
**Verificación:** la plantilla de RRHH (3 páginas) con un campo que solo aparece si la respuesta anterior
es «Sí», comprobando que oculto **no se exige** y que un valor inyectado para él **se descarta**.

### Fase 4 — Lo que la app fuente no llegó a hacer (2 días)
Adjuntos reales, firma guardada como PNG, captcha + honeypot + throttler, consentimiento con sello
(`consent_at`, `ip`, `policy_version`), aviso al responsable y acuse al que responde por
`CompanyMailer`/`CompanyWhatsApp`/`CompanySms` — con la regla de PQRSF: **un canal caído no deshace el
guardado de la respuesta**.
**Verificación:** subir un PDF de 7 MB y otro de 9 (rechazado); comprobar que el archivo **no** es
accesible por URL directa y sí por el controlador, y que un usuario de otra empresa recibe 404.

### Fase 5 — Respuestas, informe y exportación (2 días)
Listado con `DataTable`, detalle, `cmp_frm_answers` reconstruido, informe por tipo de campo (conteos,
NPS, media de calificación, tiempo medio — **calculados, no inventados**), CSV (con BOM y `;`, que es lo
que espera el Excel en español) y JSON, comprobante web + PDF + QR.
**Verificación:** exportar 500 respuestas simuladas y abrirlas en Excel con tildes correctas; cambiar
después una etiqueta del formulario y comprobar que la exportación anterior **no cambia**.

### Fase 6 — Cierre del contrato (1–2 días)
Las 4 plantillas de arranque portadas de la app fuente (`TemplateLibrary` + `spark formularios:seed`,
idempotente por `code`), `DemoData` desde el listado, `PlanGuard` aplicado en **todas** las vías que
consumen cupo (nueva, duplicar, usar plantilla, responder), `spark formularios:autotest` y
`spark formularios:contrato`.
**Verificación:** el auditor del contrato en 0 incumplimientos —incluida la comprobación de **cero IA**
del §2.8— y el autotest en verde, creando sus filas con prefijo `ZZTEST` y **borrándolas siempre**,
también si falla (`finally`).

**Total estimado: 12–16 días** de trabajo enfocado. Las fases 0–1 ya dan un producto útil (formularios
públicos que se responden y se guardan); el editor es lo que lo vuelve autoservicio.

---

## 9. Fuera de alcance de la v1 (dicho a propósito)

- **Bloque nativo dentro de Páginas.** v1 se embebe con `<iframe>`; acoplar los dos componentes es una
  decisión que conviene tomar cuando los dos estén asentados.
- **Respuestas parciales reanudables.** La columna `status` las contempla, pero guardar el progreso a
  mitad exige una sesión anónima y una política de retención propias.
- **Pagos, lógica de salto entre páginas (`skip logic`), puntuación tipo examen, cálculo entre campos.**
  Se pueden añadir sin migrar datos, porque el formulario es un árbol.
- **Firma con validez legal** (sello de tiempo certificado, hash encadenado). Lo que hay es una rúbrica
  gráfica, y así hay que llamarla en la interfaz.
- **Traducción del formulario público a varios idiomas.** El panel es bilingüe; el contenido que escribe
  la empresa, no.

> La **IA no está en esta lista** a propósito: no es alcance aplazado sino una decisión permanente del
> componente. Está en §2.8, junto al resto de decisiones de arquitectura.

---

## 10. Checklist de entrega (§14 del contrato)

- [ ] Ninguna clase fuera de `Components\Formularios\`; ningún archivo tocado en `app/` o `system/`
- [ ] Todas las tablas con prefijo `cmp_frm_`; ninguna tabla del núcleo alterada salvo las 3 columnas de
      `plans` que añade y retira la migración propia
- [ ] Migraciones idempotentes y portables (sin `ENUM`, sin JSON nativo, todo por Forge)
- [ ] **Ninguna ruta del grupo sin `permission:`**; borrados por POST con `partials/delete_button`
- [ ] `company_id` en todas las tablas y filtrado por `session('active_company_id')` **en el servicio**
- [ ] Vistas públicas autosuficientes: sin `tailwind.css`, sin `icons.css`, sin CDN, SVG en línea
- [ ] `Language/es` y `Language/en` **simétricos**, incluidos placeholders, confirmaciones (`esc(…, 'js')`),
      vacíos y los mensajes de los servicios
- [ ] Los 5 entregables del plan (migración, `plan_flag`/`plan_limits`, `PlanGuard` en el servicio,
      configurable por el administrador, limpieza al desinstalar)
- [ ] `spark formularios:contrato` sin incumplimientos, escaneando el código **sin comentarios**
      (`token_get_all`) para no contar falsos positivos
- [ ] **Cero IA**: ninguna referencia a `AiClient`, `anthropic`, `gemini` ni `openai` en toda la carpeta;
      ninguna clave de API en la configuración; ninguna llamada saliente salvo los canales de la empresa
- [ ] Lo que no se pudo comprobar leyendo (PostgreSQL, SQL Server, calidad de las traducciones) listado
      aparte como revisión manual, en vez de darlo por bueno
