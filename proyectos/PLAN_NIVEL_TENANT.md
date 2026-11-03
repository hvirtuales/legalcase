# Nivel intermedio «tenant» — agrupar empresas bajo un administrador

> **Estado: implementado** (2026-08-05). Migraciones `2026-08-05-0000xx`, `App\Libraries\TenantGuard`,
> `App\Filters\TenantFilter`, `TenantController` (`/tenants`, Super Admin) y `TenantPanelController` (`/tenant`,
> administrador del grupo). Los componentes de nivel grupo tienen su propio contrato
> ([`CONTRATO_COMPONENTES_TENANT.md`](CONTRATO_COMPONENTES_TENANT.md)) y su piloto (`components/Directorio/`).
> Se comprueba con `php spark tenant:autotest`.
>
> Dos decisiones cambiaron respecto al planteamiento original y están reescritas abajo: **los planes tienen
> ámbito y el tenant reparte de una lista blanca** (§3.3, sustituye a la herencia de plan) y **el formulario de
> planes se genera desde los Manifest** (§6.1).
>
> **Ampliación (2026-08-06):** el administrador del grupo tiene ahora su propio **menú de empresas**
> (`/tenant/empresas`, `TenantCompanyController`): crea empresas, les asigna plan y administrador, y reparte
> roles **de «Admin» hacia abajo**. Ver §5.1. Trajo un arreglo necesario en el núcleo: **`/companies` quedó
> acotado a las empresas accesibles** para los no-super (§5.2).

## 1. El problema

Un centro comercial o una cámara de comercio no es una empresa más: **agrupa** empresas y necesita gente que
las administre sin ser dueña de cada una. Hoy la plataforma solo tiene dos niveles —plataforma y empresa— y el
único que ve varias empresas es el Super Admin, que las ve **todas**. No hay forma de decir «este usuario
administra estas doce y ninguna más».

---

## 2. Qué opino de la propuesta

**El fondo es correcto y la intuición de modelarlo como «una empresa contenedora» también.** Un tenant tiene
nombre, NIT, logo, color de marca, canales de comunicación, plan y suscripción: exactamente lo que ya sabe
guardar `companies`. Crear una tabla `tenants` paralela duplicaría todo eso y obligaría a tocar el aislamiento
de los cinco componentes. Con **`companies.parent_id` + `companies.type`** el modelo sale casi gratis.

**Hay una parte que yo cambiaría: el «rol tenant».** Los roles de la plataforma son globales —`hasPermission()`
une todos los roles del usuario sin mirar contexto—, así que un rol «Administrador de tenant» le daría ese poder
**en todas partes**, no solo en su grupo. El alcance tiene que ser **relativo al tenant**, igual que ya lo es
`company_users.is_admin` para una empresa.

La buena noticia es que, si el tenant es una fila de `companies`, **eso ya existe**: administrador del tenant =
miembro con `is_admin = 1` de la fila-tenant. Sin tabla nueva y sin rol global nuevo. Sí hace falta un permiso
(`tenants.view` / `tenants.manage`) para las pantallas de gestión, pero **quién puede tocar qué sale del
vínculo, no del rol**.

**El riesgo no está en el modelo, está en el aislamiento.** Los cinco componentes filtran por
`session('active_company_id')`. En cuanto el administrador de un tenant quiera «ver lo de sus empresas», hay dos
caminos con costes muy distintos, y conviene elegirlo ahora (§4).

**Tres cosas que hay que decidir a propósito antes de escribir código**, porque si se descubren a mitad duelen:

1. **`plans.max_companies` ya existe, pero hoy significa otra cosa**: `CompanyController::ownerAtLimit()` lo usa
   como «empresas por **dueño**». Si pasa a significar «empresas del **tenant**», hay que migrar ese uso, no
   dejar los dos conviviendo.
2. **¿Quién paga el plan?** Si el tenant tiene plan y sus empresas también, hay dos fuentes de verdad y
   `PlanGuard` no sabrá cuál manda.
3. **¿El administrador del tenant puede crear empresas** o solo administrar las que le asignen? Cambia quién
   consume el cupo y quién responde por los datos.

Mis respuestas están en las decisiones de abajo. Ninguna es irreversible, pero las tres tienen que estar
tomadas antes de la primera migración.

---

## 3. Decisiones

### 3.1 El tenant es una empresa, con dos columnas nuevas

```
companies
  + type       VARCHAR(10)  DEFAULT 'company'   -- 'company' | 'tenant'
  + parent_id  INT NULL     FK companies(id) ON DELETE SET NULL
```

- `type = 'tenant'` marca al centro comercial o la cámara.
- `parent_id` en cada empresa dice a qué tenant pertenece. `NULL` = empresa suelta, como hasta hoy.
- **Un solo nivel de anidación**: un tenant no puede colgar de otro. Se valida al guardar; sin esa regla, el día
  que alguien encadene tres niveles hay que reescribir todas las consultas de alcance.

*Descartado:* tabla `tenants` propia. Duplicaría marca, canales, plan y suscripción, y obligaría a un segundo
juego de pantallas para lo mismo.

### 3.2 El administrador del tenant es un miembro, no un rol global

Se reutiliza `company_users` apuntando a la fila-tenant con `is_admin = 1`. Eso ya significa hoy «administra
esta empresa»; aplicado al tenant significa «administra el grupo».

Se añade el permiso **`tenants.view` / `tenants.manage`** (para las pantallas del panel global) y el rol
**«Admin de tenant»** con `tenants.view` + `companies.view/edit` + `users.*`. Pero **el rol no decide el
alcance**: decide qué pantallas ve. Qué empresas puede tocar lo resuelve siempre `TenantGuard` (§4).

*Descartado:* un rol global «Tenant» sin vínculo. Le daría permiso sobre todas las empresas de la plataforma.

### 3.3 Los planes tienen **ámbito**, y el tenant reparte de una lista blanca

> Esta decisión **sustituye** a la herencia de plan que se planteaba antes. La herencia dejó de tener sentido en
> cuanto los planes pasaron a tener ámbito: el plan de un tenant declara banderas de **componentes de nivel
> grupo**, así que heredárselo a una empresa no le daría ningún servicio útil.

- **`plans.scope`** = `'company'` (todos los que ya existían) o `'tenant'`. El formulario de planes ofrece las
  banderas y cupos de los componentes de **ese** ámbito, y `max_companies` solo aparece en los de tenant, donde
  significa **cuántas empresas puede agrupar**.
- El **Super Admin** crea los dos tipos y, al dar de alta el tenant, le asigna: su **plan de tenant** (con su
  suscripción) y la **lista blanca** de planes de empresa que podrá repartir (`tenant_plans`), con uno marcado
  como **por defecto**.
- El **administrador del tenant** asigna plan a cada una de sus empresas **eligiendo solo dentro de esa lista**.
  Sin la lista blanca nada le impediría ponerle a sus doce locales el plan más caro sin haberlo contratado.
- Una empresa recién colgada del grupo recibe automáticamente el **plan por defecto**, para que no se quede sin
  servicios por un olvido.
- **`CompanyModel::effectivePlan($companyId)`** sigue siendo el único punto por el que se resuelve el plan (lo
  usan los cinco `PlanGuard`, `ComponentManager` y el Panel), aunque hoy devuelva simplemente el plan propio de
  la fila: tenerlo centralizado es lo que permitió cambiar esta regla tocando un solo método.
- La **vigencia sí se hereda**: si la suscripción del tenant venció, sus empresas no se pueden seleccionar
  aunque la suya esté al día (`CompanyModel::isUsable()`). Es lo que espera quien factura al centro comercial y
  no a cada local.

### 3.4 `max_companies` pasa a ser el cupo del tenant

`plans.max_companies` deja de contarse por dueño y pasa a contarse por tenant: cuántas hijas puede tener, y solo
en los planes de `scope = 'tenant'`. `CompanyController::ownerAtLimit()` se sustituyó por
`TenantGuard::companiesLeft($tenantId)`. Las empresas sueltas (sin `parent_id`) quedan fuera del cupo, como hoy.

### 3.5 El administrador del tenant crea empresas solo si el Super Admin lo habilita

Las crea el Super Admin y las cuelga del tenant, o el administrador del tenant las crea **consumiendo su cupo**
si el Super Admin le activa esa casilla (`companies.can_create_children`, columna en la fila-tenant). Por
defecto **no**: quien responde por los datos de una empresa nueva es quien la crea.

Implementado en `/tenant/empresas` (§5.1). El interruptor no es decorativo: sin él, el formulario no se pinta
**y** el POST se rechaza.

---

## 4. Aislamiento: la decisión que más cuesta si se equivoca

Los cinco componentes filtran por `session('active_company_id')` y eso **funciona y está probado**. Dos caminos:

| | A · Cambio de contexto | B · Vista agregada real |
|---|---|---|
| Qué hace | El admin del tenant **cambia de empresa** en el selector, como hace hoy el Super Admin | Ve datos de todas sus empresas en una sola pantalla |
| Cambios en componentes | **Ninguno** | Los cinco: cada consulta pasa de `= company_id` a `IN (…)` |
| Riesgo de fuga | Nulo: el aislamiento no se toca | Uno por componente, y cada componente nuevo lo hereda |
| Coste | Días | Semanas, más una auditoría por componente |

**Recomiendo A**, más un **Panel del Tenant** de **solo lectura** con los agregados que de verdad se necesitan
(cuántas empresas, cuáles vencen, consumo del cupo), calculados **en el núcleo** con consultas propias, sin
tocar el aislamiento de nadie. Si más adelante hace falta un informe cruzado de un componente concreto, se
añade ahí y solo ahí.

**`TenantGuard`** (`app/Libraries/TenantGuard.php`) es la única fuente de verdad del alcance:

```php
tenantsOf(int $userId): array          // tenants donde es miembro admin
companyIdsFor(int $userId): array      // ids de las hijas de esos tenants
canAccess(int $companyId, int $userId) // ¿esa empresa está en su grupo?
companiesLeft(int $tenantId): ?int     // cupo del plan (null = ilimitado)
```

Y se conecta en **dos sitios, no más**:

- `CompanyModel::getAccessibleFor()` → suma las hijas del tenant a las que ya salían (dueño ∪ miembro).
- `CompanyModel::userCanAccess()` → acepta también «hija de un tenant que administro».

Todo lo demás —`AuthFilter`, `CompanySwitcherController`, los componentes— sigue funcionando sin cambios,
porque ya preguntan a esos dos métodos.

---

## 5. Pantallas

| Ruta | Quién | Qué |
|---|---|---|
| `/tenants` | Super Admin | Listado con nº de empresas, plan, vigencia y su administrador |
| `/tenants/nuevo`, `/tenants/editar/{id}` | Super Admin | Alta: datos, plan, cupo de empresas y **asignar administrador** (usuario existente o nuevo), en una transacción como el alta de empresas |
| `/tenants/{id}/empresas` | Super Admin | Colgar y descolgar empresas del tenant |
| `/tenant` | Admin de tenant | **Panel del Tenant**: sus empresas con estado de plan y vigencia, cupo consumido, y entrar a cada una |
| `/tenant/empresas` | Admin de tenant | **Empresas del grupo**: listar, crear, editar, asignar plan y administrador |
| `/tenant/empresas/nueva`, `/tenant/empresas/{id}/editar` | Admin de tenant | Datos, plan de la lista blanca y administrador (existente o nuevo) |
| `/tenant/usuarios` | Admin de tenant | Usuarios de sus empresas, acotado por `TenantGuard` |
| `/tenant/usuarios/nuevo` | Admin de tenant | Alta en una de sus empresas: usuario nuevo o uno que ya tiene cuenta |
| `/tenant/usuarios/{id}/rol` | Admin de tenant | Rol del usuario, de «Admin» hacia abajo |

En el **encabezado**, el selector de empresa muestra las hijas agrupadas bajo el nombre del tenant. En el
**sidebar**, «Panel del Tenant», «Empresas del grupo» y «Usuarios del grupo» aparecen a quien administre uno —
con el mismo criterio que ya usamos para ocultar la administración de plataforma al Super Admin con empresa
activa.

### 5.1 El menú de empresas del grupo

Es **otra pantalla que `/companies`**, no el mismo CRUD con un filtro, y la diferencia es deliberada: el
catálogo de plataforma es del Super Admin —crea empresas sueltas, elige cualquier plan, cuelga de cualquier
grupo—, mientras que aquí **todo está acotado por el grupo y la acotación no es opcional**:

- `parent_id` sale de la **sesión**, nunca del POST: la empresa nace dentro del grupo o no nace.
- El plan tiene que estar en la **lista blanca** (`tenant_plans`); si no, se rechaza en el servidor aunque el
  `<select>` diga otra cosa.
- El alta exige las tres condiciones juntas: `companies.can_create_children`, cupo libre
  (`plans.max_companies`) y **suscripción del grupo vigente**. Se comprueba dos veces —para no pintar el botón
  y para rechazar el POST—, porque lo primero es cortesía y lo segundo es la regla.
- El administrador de la empresa puede ser un usuario existente o uno nuevo, y su rol sale de
  **`TenantGuard::assignableRoles()`**: todos menos el **Super Admin** y menos **cualquier rol con permisos de
  plataforma** (`tenants.*`, `permissions.*`, `components.manage`). «Admin» sigue dentro — es el rol con el que
  se administra una empresa —, que es exactamente lo que se pidió: de Admin hacia abajo.
- Al aplicar un rol solo se tocan los **asignables**: si alguien ya tiene uno que el administrador del grupo no
  puede otorgar, ni se le pone ni se le quita. No es suyo.

**Forma, no invento:** estas pantallas usan el mismo lenguaje visual que las del núcleo —listados con
`DataTable` + `partials/datatable` dentro de una `card` con su acción principal en la cabecera, como `/companies`
y `/users`; alta y rol en pantalla propia, como `/users/create` y `/users/assign-role`; y el formulario de
empresa reutilizando `companies/_fields` y el mismo bloque de administrador—. Entre una pantalla y otra cambia el
alcance, no el aspecto: quien sepa usar el panel de plataforma sabe usar el del grupo.

### 5.2 Efecto secundario: `/companies` quedó acotado

Repartir el rol «Admin» tenía una consecuencia que rompía el nivel: ese rol lleva `companies.view/edit/delete`,
y `CompanyController` **no acotaba nada** — cualquier Admin veía, editaba y borraba las empresas de toda la
plataforma, incluidas las de otros grupos. Se acotó con la misma regla que ya gobierna el selector y el
`AuthFilter` (`getAccessibleFor()` / `userCanAccess()`): dueño, miembro o empresa de un grupo que administra. El
Super Admin las sigue viendo todas.

Por lo mismo, `$sbPlatform` (el que decide si el sidebar muestra la administración de plataforma) también es
falso para quien administra un grupo: su sitio son las pantallas del grupo, no el catálogo de todos.

---

## 6. Componentes de ámbito tenant

Tienen **contrato propio**: [`CONTRATO_COMPONENTES_TENANT.md`](CONTRATO_COMPONENTES_TENANT.md), que es un anexo
del contrato base (todo lo de aquel sigue vigente) y describe solo lo que cambia. En resumen:

```php
// Manifest.php
'scope' => 'tenant',   // opcional; por defecto 'company'
```

- **`company`** (por defecto): lo de hoy. Menú y datos por `active_company_id`.
- **`tenant`**: sus tablas llevan `tenant_id` en vez de `company_id`, su menú **solo aparece dentro del grupo**,
  y su `plan_flag` vive en los planes de `scope = 'tenant'`.
- Sus rutas llevan el filtro **`tenant`** (`App\Filters\TenantFilter`), que comprueba contexto, vínculo y
  vigencia del grupo. Es lo que convierte «tiene el permiso» en «tiene el permiso **sobre este grupo**».
- `ComponentManager` gana `scopeOf()` / `currentScope()` y filtra menús y `planStatus()` por el contexto activo.

**No se toca ningún componente existente**: los cinco siguen siendo `company` sin declarar nada.

**Piloto:** `components/Directorio/` (code `dir`) — el directorio de locales del centro comercial, con página
pública autosuficiente. Se instala con `php spark components:install Directorio` y sirve de plantilla del
contrato.

### 6.1 Efecto secundario: el formulario de planes ya no se toca

Para que un componente de tenant pudiera declarar sus cupos **sin editar `app/`**, el formulario de planes dejó
de tener los componentes cableados: `ComponentManager::planFormGroups()` genera un recuadro por componente desde
su Manifest, `PlanController::collect()` guarda exactamente esas columnas y `PlanModel` las suma solo a sus
`allowedFields`. Esto **también simplifica el contrato de los componentes de empresa**: de los cinco entregables
del §11, los tres que obligaban a tocar el núcleo (vista, `allowedFields`, `collect()`) desaparecen.

Para los cupos que no se cuentan por empresa/grupo —y que por eso no están en `plan_limits`— el Manifest declara
**`plan_fields`**, que es solo para el formulario. Es el caso de `max_connections` en Networking.

---

## 7. Cómo se valida

Autotest CLI temporal + pruebas HTTP, y se borra al terminar:

1. **Alcance**: el admin del tenant ve en el selector **sus** hijas y ninguna otra; `userCanAccess` le niega una
   empresa de otro tenant y una empresa suelta.
2. **Por URL también**: entrar a `/switch-company/{id}` de una empresa ajena se rechaza (el alcance es del
   servidor, no del selector).
3. **Plan heredado**: una hija sin plan propio usa el del tenant en `PlanGuard`, `planStatus()` y el Panel
   Empresa; con plan propio, manda el suyo.
4. **Vigencia heredada**: vencida la suscripción del tenant, sus hijas no se pueden seleccionar.
5. **Cupo**: con `max_companies` alcanzado no se puede colgar otra empresa; descolgar libera.
6. **Un solo nivel**: no se puede poner un tenant como hijo de otro, ni una empresa como su propio padre.
7. **Borrado**: eliminar un tenant **no borra sus empresas** (FK `SET NULL`); quedan sueltas y se avisa de
   cuántas antes de confirmar.
8. **Los componentes no cambian de comportamiento**: la batería actual de aislamiento entre empresas (la de los
   cinco componentes) sigue pasando igual.
9. **El Super Admin no pierde nada**: sigue viendo todo y usando Global.
10. **i18n** es/en simétricos y permisos sembrados por migración.

---

## 8. Fuera de alcance

Jerarquía de más de un nivel · facturación consolidada al tenant · informes cruzados de los componentes
existentes · permisos por empresa dentro del tenant (hoy: administra todas o ninguna) · marca del tenant
aplicada a sus hijas · portal público del tenant.

---

## 9. Orden de implementación

1. Migración: `type`, `parent_id`, `can_create_children`, permisos `tenants.*` y el rol «Admin de tenant».
2. `TenantGuard` + `effectivePlan()` + conexión en los **dos** métodos de `CompanyModel`.
3. Repuntar a `effectivePlan()` todo lo que hoy lee `companies.plan_id` (5 `PlanGuard`, `ComponentManager`,
   Panel Empresa, selector).
4. Sustituir `ownerAtLimit()` por el cupo del tenant.
5. Pantallas del Super Admin (`/tenants`).
6. Panel del Tenant y gestión de sus usuarios.
7. `scope` en el Manifest + filtrado de menús.
8. Autotest, i18n y documentación.

Los pasos 2 y 3 son el corazón: si el plan efectivo no queda bien resuelto, los cupos y las vigencias mienten
en cinco sitios distintos.
