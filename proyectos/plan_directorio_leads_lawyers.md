# Plan — Directorio público «Leads Lawyers» sobre LeadFlow

Análisis del frontend de `htdocs/salud` (componente `Salud`, v0.9.3) y plan para llevar ese mismo
patrón a esta instalación con **firmas y abogados**, usando los contenidos de
`proyectos/insumos/contenidos_web_leads_lawyers.md` y la paleta `#0b1f44` / `#bf8f23`.

Documento de diseño: decide y justifica. No hay código escrito todavía.

---

## 1. Qué hace el frontend de Salud, y qué de eso sirve aquí

El componente `Salud` sirve **el sitio completo**, no una sección: se queda con la raíz `/` y desde
ahí monta un marketplace de dos caras. Sus piezas, en el orden en que las ve un visitante:

| Pieza | Ruta | Archivo | Qué resuelve |
|---|---|---|---|
| Portada | `/` | `public/home.php` (157 líneas) | Hero con buscador por GET, especialidades con conteo, banda de consultorios, «quién atiende antes», CTA de alta |
| Buscador | `/encuentra` | `public/find.php` | Filtros (texto, especialidad, ciudad) que viajan en la URL, resultados paginados |
| Tarjeta de resultado | — | `public/_card_pro.php` | Un solo partial que pintan portada, buscador y guardados |
| Perfil | `/profesional/{slug}` | `public/profile.php` | Ficha pública indexable |
| Microsite | `/consultorio/{slug}` | `public/company.php` | La página de la organización, no de la persona |
| Tarjeta digital | `/profesional/{slug}/tarjeta` + `.vcf` | `public/card.php` (733 líneas) | Formato CR80 vertical que se gira, QR, contactos como círculos |
| Alta pública | `/registro` | `public/signup.php` | Un formulario, dos caras (profesional / clínica); crea usuario + empresa + suscripción en transacción y nace **sin publicar** |
| Guardados | `/favoritos` | `public/favorites.php` | Lista en el navegador; el servidor devuelve las tarjetas renderizadas |
| Conversión | `/profesional/{slug}/reservar` | `public/book.php` | **Reservar cita** ← aquí es donde nuestro caso se separa |

Y tres decisiones transversales que conviene copiar tal cual:

1. **Layout público autosuficiente** (`public/_layout.php`, 708 líneas): toda la hoja en línea, SVG
   en línea, tipografía del sistema, tokens propios `--sa-*` con modo oscuro por
   `prefers-color-scheme`. Cumple §7.3 del contrato y sobrevive a que la carpeta se suelte en otra
   instalación.
2. **SEO explícito y restrictivo por defecto**: `robots` es `noindex, nofollow` salvo que la vista
   pida lo contrario, con `canonical`, Open Graph y JSON-LD. Las páginas con token no se indexan
   nunca.
3. **Una sola definición de lo publicable**: `Libraries/PublicDirectory` decide qué sale al público
   (estado de publicación + plan) y lo usan buscador, portada, perfil, microsite y sitemap. Mismo
   criterio que `AutomationRegistry`: una fuente de verdad.

**Lo que NO sirve aquí:** la agenda. En Salud la acción final es reservar una hora contra
disponibilidad calculada. En Leads Lawyers la acción final es **registrar un lead** en la firma del
abogado, que ya tenemos resuelto y con pipeline detrás.

---

## 2. La decisión arquitectónica: ampliar LeadFlow, no crear otro componente

Un componente **no puede depender de otro** (contrato §0.4). El directorio tiene que crear leads que
entren por `LeadService` —para que hereden duplicados, scoring, timeline, automatizaciones y
conversión a Docketwise—, y eso desde un componente aparte obligaría a importar
`Components\LeadFlow\…`, que es exactamente lo prohibido.

Es el mismo razonamiento que el Manifest de Salud escribe para justificar un componente y no cinco.
Así que:

> **El directorio público es una capa nueva DENTRO de `components/LeadFlow`**, que pasa de «CRM de una
> firma» a «marketplace legal + CRM de cada firma». Sube a `2.0.0`.

Consecuencia que hay que aceptar: LeadFlow crece (hoy 146 archivos). Se mitiga con carpetas separadas
(`Controllers/Public*`, `Views/public/directory/*`, `Libraries/Directory/*`) y con que el auditor de
contrato ya cubre el conjunto.

---

## 3. Lo que falta, pieza por pieza

### 3.1 Perfil público del abogado y de la firma

Hoy `cmp_lfl_members` dice qué función comercial tiene un usuario en una firma, y nada más. Falta la
cara pública.

- **`cmp_lfl_profiles`** (nueva, `company_id` + `user_id` nullable): `slug` único, `kind`
  (`lawyer` | `firm`), `headline`, `bio`, `photo`, `languages`, `consult_modes`, `experience_years`,
  `website`, `status` (`draft` | `published` | `suspended`), `published_at`, `verified_at`,
  `bar_number`, `bar_state`, `bar_url`, `featured` (del plan), `seo_description`.
  - `user_id` nulo = perfil de la firma; con valor = perfil de un abogado de esa firma.
  - Nace en `draft`: nadie aparece publicado sin decidirlo, igual que en Salud.
- **La firma reutiliza `companies`** (nombre, logo, color de marca, slug) — no se duplica nada del
  núcleo.

### 3.2 Taxonomía común: áreas de práctica y jurisdicciones

`cmp_lfl_services` es **por empresa** y alimenta el título del Matter. Para buscar en un directorio
hace falta un vocabulario **compartido por todas las firmas**, o el filtro no cruza.

- **`cmp_lfl_areas`** (global, sin `company_id` — excepción consciente, como la red de Networking):
  las 12 áreas del §9 del insumo, con `slug`, `name_es`, `name_en`, `sort`, `is_active`.
- **`cmp_lfl_profile_areas`** (pivote perfil ↔ área).
- **`cmp_lfl_jurisdictions`** (global): estado/país donde el profesional está autorizado, con su
  pivote `cmp_lfl_profile_jurisdictions`.
- El servicio interno de la firma puede **apuntar** a un área (`cmp_lfl_services.area_id`), y así el
  lead que entra por el directorio ya trae el servicio correcto para la conversión.

### 3.3 Captación: el lead sustituye a la cita

Dos caminos, y el segundo es el que hace de esto un negocio:

**A. Lead dirigido** (fase 1). El visitante está en un perfil y pulsa «Solicitar orientación». Se
abre un formulario corto (nombre, apellido, correo, teléfono, área, descripción del caso, consentimiento)
que crea el lead **en la firma de ese perfil**:

- pasa por `LeadService::create()` → duplicados, etapa «Nuevo», scoring, timeline, automatizaciones;
- `source` = fuente sembrada «Directorio Leads Lawyers», `campaign` = el área, `landing_page` = el
  perfil, y se guarda `profile_id` para poder medir qué perfil capta;
- defensas ya existentes en el componente: `Libraries/Captcha`, honeypot, sello de tiempo firmado,
  `throttler` con IP hasheada, CSRF;
- el cupo lo sigue haciendo valer `PlanGuard` **en el servicio**, no en la vista.

**B. Oportunidad abierta** (fase 3). El visitante entra por «Buscar orientación legal» sin elegir
abogado. Su solicitud no puede ir a una firma concreta, así que nace como **oportunidad** en una tabla
global `cmp_lfl_opportunities` (área + jurisdicción + idioma + resumen, datos de contacto **ocultos**),
y las firmas suscritas al área la ven en una bandeja y la **reclaman**:

- al reclamar, y solo entonces, se crea el lead en esa firma con los datos completos;
- `cmp_lfl_opportunity_claims` con UNIQUE `(opportunity_id, company_id)` y un tope de reclamos por
  oportunidad (p. ej. 3) para que el prospecto no reciba quince llamadas;
- el cupo de reclamos es **columna de plan** (`max_opportunities`): es la palanca de la «membresía
  premium» del §10 del insumo.

Esto es literalmente «conectar las oportunidades» y es lo que diferencia la plataforma de un
directorio.

### 3.4 Confianza y verificación (§8 del insumo)

- Campos de licencia ya listados en `cmp_lfl_profiles`, con `verified_at` y `verified_by`.
- Un perfil sin verificar se publica pero **no muestra insignia**, y el buscador lo ordena por debajo.
- **Aviso legal obligatorio** (§17 del insumo) en el pie de todas las páginas públicas y en cada
  perfil: la plataforma no garantiza resultados ni sustituye asesoría; la relación abogado-cliente es
  entre las partes.

### 3.5 Frontend: rutas, vistas y marca

Grupo público nuevo, separado y evidente, hermano del que ya existe (`leadflow-publico`):

```
/                            ← ver §5: decisión sobre la raíz
/abogados                    buscador (filtros por GET: area, estado, idioma, modalidad, q, pagina)
/abogado/{slug}              perfil público del abogado
/abogado/{slug}/tarjeta      tarjeta digital (+ /tarjeta.vcf)
/abogado/{slug}/consulta     formulario que crea el lead dirigido  (GET + POST)
/firma/{slug}                microsite de la firma con sus abogados
/firmas                      listado de firmas
/areas/{slug}                landing por área de práctica (SEO: «abogado de inmigración»)
/orientacion                 formulario de oportunidad abierta (fase 3)
/registro-legal              alta pública de abogado o firma
/abogados/sitemap.xml        sitemap de lo publicado
```

Vistas nuevas bajo `Views/public/directory/`, con **su propio layout autosuficiente** y tokens de la
paleta pedida:

```css
:root{
  --ll-navy:#0b1f44;  --ll-gold:#bf8f23;
  --ll-bg:#f7f8fa;    --ll-card:#fff;  --ll-ink:#11151c;  --ll-muted:#5b6472;
}
@media (prefers-color-scheme: dark){
  :root{ --ll-bg:#0a0e16; --ll-card:#141a24; --ll-ink:#e8eaee; --ll-muted:#98a2b3;
         --ll-navy:#12305f; --ll-gold:#d8a93a; }
}
```

El navy manda en superficies y encabezados; el dorado se reserva para acciones y la insignia de
verificado. En oscuro se aclaran los dos: `#0b1f44` sobre fondo negro pierde contraste, y el dorado
puro vibra. El color de marca de cada firma solo pinta su microsite y **se valida con regex antes de
emitirse crudo** (nunca `esc($hex,'css')`).

Contenido de la portada: las 8 secciones del §11 del insumo, en el orden del §19, con los textos tal
como están escritos — hero, qué es, para abogados, para usuarios, confianza, áreas en tarjetas,
comunidad, CTA final. Todo por `lang()` en `es` **y** `en` (el público objetivo es hispanohablante en
EE. UU.: el inglés no es decorativo).

### 3.6 Tarjeta digital

Se hereda el patrón ya probado dos veces en el ecosistema —`components/Servicios/Views/public/card.php`
(328 líneas) y la de Salud (733)—: formato CR80 vertical, giro con las dos caras opacas cruzadas,
`prefers-reduced-motion` respetado, barra de acciones **fuera** de la cara que gira, QR con el
`QrService` propio del componente y descarga `.vcf`.

Cambios de contenido para el caso legal: áreas de práctica en vez de especialidades médicas,
jurisdicciones, idiomas, insignia de verificado, y el botón principal es **«Solicitar orientación»**
(que crea el lead), no «reservar».

---

## 4. Plan y monetización

Columnas nuevas en `plans`, declaradas en el Manifest y añadidas por migración **del propio
componente** (nunca se toca el formulario de planes):

| Columna | Tipo | Para qué |
|---|---|---|
| `lfl_directory_enabled` | flag | Aparecer en el directorio público |
| `lfl_featured` | flag | Perfil destacado: orden preferente y distintivo dorado |
| `max_profiles` | cupo | Cuántos abogados puede publicar la firma |
| `max_opportunities` | cupo | Oportunidades abiertas reclamables al mes (fase 3) |

Recordar la regla del núcleo: **una columna que no existe significa «permitido»**, no «prohibido».
Los cuatro modelos de participación del §10 del insumo se expresan combinando estas cuatro columnas,
sin código nuevo.

---

## 5. La raíz del sitio: decisión que hay que tomar antes de la fase 1

Hoy `/` sirve `AuthController::login` (`app/Config/Routes.php:33`), y **el componente no puede
quedarse con la raíz sin tocar el núcleo**. No es una cuestión de orden de carga que se pueda sortear
poniendo la ruta más tarde: `RouteCollection::create()` lo impide a propósito
(`system/Router/RouteCollection.php:1538`):

> «Don't overwrite any existing 'froms' so that auto-discovered routes do not overwrite any
> app/Config/Routes settings. The app routes should always be the "source of truth".»

Las rutas auto-descubiertas de un componente se registran solo si el patrón no existe ya. Salud
resolvió esto retirando del enrutador la vitrina del núcleo, dejando el código intacto y
documentándolo.

Tres opciones, de menos a más invasiva:

1. **Prefijo propio** (`/abogados` como puerta). Cero cambios en `app/`. El login sigue en `/`. Bueno
   para construir y enseñar; malo como producto: la puerta de una plataforma pública no puede ser un
   formulario de acceso.
2. **Una línea en `app/Config/Routes.php`**: la raíz pasa al controlador público del componente y el
   login queda solo en `/login`. Es el precedente de Salud, es reversible y es lo que pide el negocio.
   Hay que documentarlo en `CLAUDE.md` como la única excepción, porque contradice «no se toca el
   núcleo».
3. **Dominio aparte** apuntando al mismo código, con la raíz resuelta por host. Es lo correcto a
   futuro (`leadslawyers.com` público, `app.` privado) y no requiere decidirlo ahora.

**Recomendación:** construir en `/abogados` (opción 1) y cambiar a la 2 cuando el contenido esté
listo, para no dejar la puerta del sitio a medias durante el desarrollo.

---

## 6. Fases

Cada fase termina con: `leadflow:contrato` conforme, `leadflow:autotest` verde, `composer tailwind`
—las pantallas internas sí lo usan— y revisión en navegador real en claro/oscuro y a 390 px de ancho
(recordar que Edge sin interfaz no baja de ~500 px: usar emulación de dispositivo).

**Fase 0 — Decisiones (medio día).** Raíz del sitio, nombre visible de la marca, dominio, y las 12
áreas y las jurisdicciones iniciales. Sembrar `cmp_lfl_areas` y la fuente «Directorio».

**Fase 1 — Perfil, buscador y lead dirigido (el MVP del directorio).**
Migraciones de `cmp_lfl_profiles`, áreas, jurisdicciones y pivotes · `Libraries/Directory/PublicDirectory`
(única definición de lo publicable: estado + plan) · pantallas internas para que la firma edite y
publique su perfil y los de sus abogados (`lfl.configure`) · landing, buscador, perfil y formulario de
consulta → lead por `LeadService` · aviso legal · SEO con canónicas y `noindex` por defecto · i18n
es/en · autotest nuevo: perfil en borrador no aparece, perfil de otra firma no se edita, el lead entra
con su fuente y respeta el cupo del plan.

**Fase 2 — Identidad y alcance.** Tarjeta digital + `.vcf` + QR · microsite de firma y listado de
firmas · landings por área · sitemap · verificación de licencia con insignia · métricas por perfil
(visitas y leads) en el panel de la firma.

**Fase 3 — Oportunidades abiertas.** `cmp_lfl_opportunities` + reclamos con UNIQUE y tope · bandeja
interna · cupo por plan · notificación a las firmas del área (por `CompanyMailer`, que ya se usa) ·
autotest de la carrera de reclamos: dos firmas reclamando a la vez no pueden pasar del tope.

**Fase 4 — Reputación y contenidos.** Opiniones ancladas a un lead convertido (como Salud las ancla a
una cita completada) y sección de recursos. Aquí hay riesgo regulatorio en publicidad legal: conviene
revisarlo antes de construirlo.

---

## 7. Riesgos y cabos sueltos

- **El autotest no cubre nada de esto todavía.** Cada fase tiene que ampliarlo; es la única
  verificación real del componente (PHPUnit solo tiene el boilerplate de CI4).
- **Dos asuntos abiertos de Docketwise** que conviene cerrar antes de abrir el directorio: el teléfono
  no se guardaba al crear el contacto (corregido en el adaptador, sin verificar contra la API) y el
  modo `live` global deja la sección 9 del autotest en rojo.
- **Datos personales de terceros.** Las oportunidades abiertas guardan contacto de personas que no
  son clientes de ninguna firma todavía: hace falta política de retención y que el consentimiento
  del formulario lo diga explícitamente.
- **Un perfil publicado es contenido indexable por Google.** Suspender un perfil tiene que quitarlo
  del sitemap y devolver 404, no dejarlo huérfano.
- **No prometer «prospectos calificados» en la landing** más de lo que el producto hace en la fase 1:
  en esa fase el lead lo elige el visitante, no lo califica la plataforma.

---

## 8. Resumen en una frase

Se copia el patrón probado de Salud —portada con buscador, tarjetas, perfiles, microsites, tarjeta
digital y alta pública, todo con layout autosuficiente y SEO explícito— **dentro de LeadFlow**, y se
sustituye la reserva de cita por el registro del lead, que es justo lo que este componente ya sabe
hacer mejor que nadie.
