# Componente Páginas frente a Gutenberg

**Fecha:** 2026-08-07 · **Componente:** `components/Paginas/` v3.2.0
**Fuente:** [Core Blocks Reference](https://developer.wordpress.org/block-editor/reference-guides/core-blocks/) del manual del editor de bloques de WordPress.

Contraste bloque a bloque del componente contra Gutenberg, para saber **qué falta de verdad**
y qué no hace falta. No es una lista de deseos: cada hueco se juzga por lo que impide hacer
hoy, y lo que se implementó está al final con su prueba.

---

## 0. Resumen

De los 18 bloques de Gutenberg revisados, el componente ya cubría **15** con equivalente
directo. El contraste destapó **10 huecos reales**, y uno de ellos era un **fallo activo**: la
plantilla ejecutiva tenía un botón «Contáctanos» apuntando a `#contacto`, y **no había forma
de que ningún bloque se llamara así**. El enlace no llevaba a ninguna parte y nadie lo había
notado.

Los 10 se implementaron. Quedan 4 diferencias **conscientes**, explicadas en §3.

---

## 1. Bloque a bloque

| Gutenberg | Equivalente | Estado |
|---|---|---|
| `core/paragraph` | `text` | ✅ + color y capitular añadidos |
| `core/heading` | `heading` | ✅ completo |
| `core/image` | `image` | ✅ + recorte a proporción añadido |
| `core/button` / `buttons` | `buttons` | ✅ + color propio, fila/columna y menú plegable |
| `core/columns` | `section` | ✅ + «no apilar» e imagen de fondo |
| `core/column` | `column` | ✅ + **ancho en por ciento** añadido |
| `core/cover` | `hero` + `section` con imagen | ✅ cubierto por dos vías |
| `core/gallery` | `gallery` | ✅ + recorte añadido |
| `core/list` | `list` | ✅ (viñetas, números y marcas) |
| `core/quote` | `quote` | ✅ |
| `core/separator` | `divider` | ✅ |
| `core/spacer` | `spacer` | ✅ (escala en vez de píxeles, a propósito) |
| `core/media-text` | `section` de 2 columnas | ✅ vía sección |
| `core/video` | `video` | ✅ (YouTube y Vimeo) |
| `core/embed` | `video` + `html` | ⚠️ parcial — ver §3 |
| `core/table` | dentro de `text` | ⚠️ parcial — ver §3 |
| `core/social-links` | `companySocial` | ✅ + nombres al lado del icono |
| `core/site-logo` | `image` con `source` = logo | ✅ y sin bloque propio |
| `core/template-part` | secciones de cabecera y pie | ✅ armadas con elementos (§2.14) |
| `core/group` | — | ⚠️ no existe — ver §3 |
| Copiar/pegar bloques | botón «Código» | ✅ el árbol real, no HTML (§2.15) |

**Lo que el componente tiene y Gutenberg no**, porque nacen de la plataforma y no de un CMS
genérico: `companyContact`, `companyQr`, `whatsappFloat` y `form` (en WordPress hace falta un
plugin), todos leyendo `companies` en vez de pedir los datos otra vez.

---

## 2. Los 10 huecos, y qué impedían

### 2.1 🔴 Ancla (`anchor`) — **estaba roto en producción**

En Gutenberg `anchor` es un *block support*: cualquier bloque puede declarar su `id` y
recibir enlaces internos. Aquí **no existía**, y sin embargo las plantillas ya usaban
`#contacto` como destino de sus botones. Resultado: el botón principal de la plantilla
ejecutiva no llevaba a ninguna parte.

**Implementado** como `BlockRegistry::COMMON_FIELDS` —el equivalente a los *supports*: se
declara una vez y lo heredan los 20 tipos—, con el ancla ya puesta en el formulario de
contacto de las plantillas y secciones que son destino de esos botones.

El ancla **se rescata en vez de rechazarse**: `«Contáctanos Ya»` → `contactanos-ya`,
`«2contacto»` → `contacto`. Quien la escribe quiere enlazarla; devolver vacío por un carácter
de más obligaría a adivinar la regla. Sea cual sea la entrada, la salida siempre casa con
`/^[a-z][a-z0-9-]{0,60}$/`, así que nada raro puede acabar dentro del atributo `id`.

### 2.2 🟠 Ancho por columna (`core/column` → `width`)

Todas las columnas de una sección medían lo mismo. **Un reparto 2/3 + 1/3 era imposible** —y
es el más común de todos: texto ancho a la izquierda, barra estrecha a la derecha.

**Implementado:** cada columna declara su ancho **en por ciento**. Si alguna lo hace, la sección pasa
a una **fila flexible** y cada columna ocupa lo suyo; si no, se conserva el reparto simple de antes.
En el teléfono cada columna sigue ocupando la fila entera.

**Corregido en v3.1.0:** al principio esto eran seis fracciones fijas sobre una rejilla de 12
columnas, y tenía un fallo que se veía en cuanto el sitio apretaba: la rejilla lleva **11
separaciones además de las doce pistas**, así que en un ancho corto —el lienzo del editor con los dos
paneles abiertos— los huecos se comían el espacio, las pistas se encogían a casi nada y el contenido
se salía de su celda amontonándose a la izquierda. `flex: 0 1 var(--pg-cw)` es justo la diferencia: la
columna pide su porcentaje y **cede lo que haga falta** para que quepa la separación, en vez de
desbordarse. Las seis palabras de antes siguen guardadas en páginas publicadas y se traducen a su
porcentaje al pintar, así que no hay migración de datos.

### 2.3 🟠 Sección con imagen de fondo (`core/cover`)

La sección solo admitía color plano. Para una banda con foto de fondo había que usar el
bloque `hero`, que trae título y botón propios y no sirve para envolver contenido.

**Implementado:** `backgroundImage` + `overlay` en la sección. Con velo oscuro la tinta pasa
a blanca sola, para que el texto se lea encima de cualquier foto.

### 2.4 🟠 Recorte de imagen (`aspectRatio` / `imageCrop`)

El 4:3 de la galería estaba **clavado en el CSS** y no se podía cambiar; la imagen suelta no
tenía recorte ninguno. Dos fotos de alturas distintas descuadraban cualquier fila.

**Implementado:** proporción elegible (original, 16:9, 4:3, 1:1, 3:4, 9:16) en `image` y
`gallery`.

### 2.5 🟡 No apilar en el móvil (`isStackedOnMobile`)

Las columnas se apilaban **siempre** en el teléfono. Para una fila de cuatro cifras o cuatro
iconos, apilar es peor que encoger.

**Implementado** como interruptor de la sección, apilando por defecto (que es lo correcto
para el 90 % de los casos).

### 2.6 🟡 Color del párrafo y capitular (`core/paragraph`)

El título admitía color y el párrafo no, sin razón. Y faltaba el `dropCap`, que es un recurso
editorial clásico y encaja con el registro ejecutivo.

**Implementado:** ambos. La capitular se aplica solo al primer párrafo del bloque — en el
segundo ya no es un recurso, es un error.

### 2.7 🟡 Color y ancho del botón (`core/button`)

El botón solo podía llevar el acento de la página, y siempre se ajustaba a su contenido.

**Implementado:** color propio, **color de texto propio** y ancho completo. El color se aplica **según
el estilo**: al fondo en el sólido, al borde y la tinta en los otros dos — si no, un botón «con borde»
con color se pintaría relleno, que no es lo que se pidió. La tinta se calcula por luminancia (blanca o
casi negra), pero un `textColor` **manda sobre la calculada**: el automatismo solo sabe elegir entre
dos, y no siempre acierta con lo que se busca.

### 2.8 🟡 Nombre de la red (`showLabels` de `core/social-links`)

Solo círculos con icono. Con marcas poco reconocibles no se sabe a dónde lleva cada uno.

**Implementado:** interruptor que pasa los círculos a píldoras con el nombre al lado.

### 2.9 🟠 Espaciado por bloque (`spacing` support)

Gutenberg deja fijar margen y relleno **en cada bloque**. Aquí el espaciado vivía solo en la
sección y en el bloque «Espacio», así que separar dos bloques concretos obligaba a meter un
«Espacio» entre ellos.

**Implementado** como campo común —igual que el ancla— con **la escala cerrada del componente**
(ninguno/pequeño/medio/grande/muy grande) en vez de píxeles libres: es la misma que gobierna el
bloque «Espacio», el relleno de la sección y la separación entre columnas, así que una página
sigue cuadrando aunque se toque bloque a bloque. El valor por defecto es **vacío = «lo que traiga
el bloque»**; sin él, abrir el inspector de una página ya hecha la habría recompuesto entera. Las
clases van al final de la hoja de estilos porque tienen la misma especificidad que las del bloque
y es el orden lo que decide.

### 2.10 🟠 Los dos anchos de la página (`contentSize` / `wideSize`)

Gutenberg define en el tema **dos** medidas —contenido y amplio— y cada bloque elige entre ellas
o se va a todo lo ancho. Aquí la página tenía **un** preajuste de tres valores y la sección otro
juego distinto, sin relación entre ambos.

**Implementado**: los ajustes de la página piden las dos medidas en píxeles (acotadas en el
servidor, y el amplio nunca por debajo del de contenido), y la sección elige entre *contenido*,
*amplio*, *a todo lo ancho* y *estrecho de lectura*. Las claves guardadas son las de antes, así
que ninguna página ya hecha cambia de sitio.

---

## 3. Diferencias conscientes (no se van a implementar)

**`core/group`** — Gutenberg necesita un contenedor genérico porque sus columnas no admiten
ajustes de fondo ni espaciado. Aquí la `section` **ya es** ese contenedor: tiene ancho,
relleno, separación, fondo, imagen y velo, y admite de 1 a 4 columnas. Un `group` sería el
mismo bloque con otro nombre.

**`core/embed`** — Gutenberg incrusta de 40 proveedores vía oEmbed, lo que exige **llamar al
servidor del tercero** para resolver la URL. El componente cubre YouTube y Vimeo extrayendo
el identificador con una expresión regular y reconstruyendo el `src`, sin pedir nada a nadie.
Añadir oEmbed traería una dependencia de red en el render, que es justo lo que la página
pública evita.

**`core/table`** — se escriben desde el editor de texto enriquecido, y el saneador ya admite
`<table>`, `<thead>`, `<tbody>`, `<tr>`, `<th>` y `<td>`. Un bloque de tabla con su propia
interfaz es mucho trabajo para lo poco que se usa en una página corporativa.

**`typography` como *support* universal** — Gutenberg deja fijar tamaño de letra y altura de
línea **en cada bloque**. Aquí los tamaños son una escala cerrada (pequeño/normal/grande) por
bloque de texto, y no hay control de interlineado. Es una decisión de producto: la tipografía
es lo primero que descuadra una página cuando se toca a mano.

> **Corrección (misma fecha).** Este apartado incluía también el `spacing`, con el mismo
> argumento. Se pidió expresamente y **está implementado** — ver §2.9. El argumento no era
> malo, pero la decisión no me correspondía a mí: lo que sí correspondía era elegir *cómo*, y
> por eso va con la escala cerrada del componente en vez de con píxeles libres.

**`className` (clases CSS propias)** — permitiría cualquier clase en el HTML público. Sin una
lista blanca es una vía para inyectar estilos que rompan la página; con lista blanca no
aporta nada que no den ya los campos. Fuera.

**`className` (clases CSS propias)** — permitiría cualquier clase en el HTML público. Sin una
lista blanca es una vía para inyectar estilos que rompan la página; con lista blanca no
aporta nada que no den ya los campos. Fuera.

---

## 4. Pantalla completa

Gutenberg toma la ventana entera y deja una salida arriba a la izquierda. El editor hacía lo
contrario: vivía dentro del panel, con la barra lateral y el encabezado comiéndose unas
20 rem. Con tres columnas —paleta, lienzo, inspector— lo que quedaba para la página era una
tira demasiado estrecha para juzgar una maquetación de dos o tres columnas.

**Implementado**: el editor usa un lienzo propio (`Views/_editor_layout.php`), con barra
superior propia (salida, título, estado, vista previa, ajustes, guardar) y las tres columnas
ocupando el alto de la ventana, cada una con su propio desplazamiento. Sigue siendo una
pantalla interna, así que carga el sistema de diseño del núcleo —incluido el tema dinámico y
el guion que lo fija antes de pintar, para que no haya fogonazo blanco en modo oscuro—.

Por debajo de 980 px ya no caben tres columnas: vuelve a ser una página que se desplaza
entera, con la paleta y el inspector encima y debajo del lienzo.

---

## 5. Vista en cascada («List View»)

Gutenberg tiene dos paneles laterales que aquí no existían:

- **List View** — el árbol completo del documento, con los hijos anidados bajo su padre,
  seleccionable y **reordenable arrastrando** ([drag & drop desde Gutenberg 11.1](https://wptavern.com/gutenberg-11-1-adds-drag-and-drop-support-for-list-view-and-upgrades-block-borders)).
- **Outline** — la jerarquía de títulos, para ver si es correcta.

**Implementados los dos**, en la pestaña «Estructura» de la columna izquierda:

- Cascada **sección → columnas → elementos**, sangrada por profundidad, con las columnas
  numeradas («Columna 2 de 3») y un contador de lo que contiene cada contenedor.
- Plegar y desplegar los contenedores.
- **Clic en cualquier fila** —incluidas las columnas— la selecciona y el inspector de la
  derecha pasa a **sus** propiedades. Es la forma de llegar a un bloque enterrado en una
  columna sin buscarlo por el lienzo.
- **Arrastrar para reordenar**, también hacia dentro de otra columna. Las columnas no se
  arrastran sueltas: las gobierna el desplegable «Columnas» de su sección.
- **Esquema de títulos** con aviso si hay un salto de nivel (un H4 justo detrás de un H2).
  Ese salto no se ve en el lienzo y rompe la jerarquía del documento para los lectores de
  pantalla y los buscadores.

---

### 2.11 🟡 Color con transparencia (`core/*` → `backgroundColor` + alfa)

Gutenberg deja poner un color de fondo **con canal alfa** en secciones y grupos, y el selector
enseña el color actual. Aquí el campo de color era un `<input type="color">` pelado: no mostraba
el valor elegido de un vistazo, no admitía transparencia y no había forma de **quitarlo** una vez
puesto (el control nativo nunca devuelve «sin color»).

Ahora el inspector usa un control propio (`colorControl()`): muestra la muestra sobre un damero
—así se ve la transparencia—, el hex al lado, un estado «sin color» con diagonal, un botón de
quitar y un deslizador de opacidad que produce el hex de **8 dígitos**. `PageService` acepta 3, 4,
6 y 8 dígitos y rechaza todo lo demás; `Renderer::color()` hace la misma comprobación antes de
emitirlo. La **columna** ganó además su propio `background` + `radius`: se puede destacar una sola
columna sin envolverla en otra sección. El relleno interior solo aparece si hay color (sin fondo no
hay caja que rellenar).

### 2.12 🟡 Vista por dispositivo

Gutenberg previsualiza en escritorio/tableta/móvil. Aquí son tres botones en la barra superior que
abren la página **ya renderizada por el servidor** en una ventana de 1440, 820 o 390 px, guardando
antes si hay cambios pendientes.

Se hace así, y **no encogiendo el lienzo**, porque las `@media` miran el ancho de la ventana, no el
del elemento: estrechar el lienzo cambiaría su tamaño sin disparar ni una regla responsive, y
enseñaría una mentira. (Gutenberg puede permitírselo porque su lienzo es un `<iframe>`, que sí
tiene su propia ventana; el de aquí no lo es a propósito, para compartir hoja de estilos con el
editor sin duplicarla.)

### 2.13 🟡 Viñetas y enlaces de la lista (`core/list`)

La lista tenía tres estilos y ninguno pintaba viñeta dentro del editor. La causa no estaba en el
bloque: la hoja pública se incrusta **dentro del editor**, que carga Tailwind, y su reset
(`preflight`) hace `ul { list-style: none }`. Se declaran ahora explícitas, y hay una prueba que
lo comprueba en la hoja. De paso se añadieron `arrow`, `star`, `dash` y `none` (siete en total) y
un campo **URL por elemento**: con destino, la entrada se vuelve enlace —pasando por `menuHref()`,
así que admite anclas de la propia página y descarta un `javascript:`—; sin él sigue siendo texto.

---


### 2.14 🟠 Cabecera y pie: de bloque monolítico a sección de elementos

Gutenberg no tiene un «bloque cabecera» ni un «bloque pie»: tiene **plantillas de parte** (*template
parts*) armadas con los bloques de siempre. Aquí se habían hecho al revés —`siteHeader` y `siteFooter`
eran dos tipos con veinte campos cada uno— y el resultado era el previsible: el menú se editaba en un
repetidor en vez de en el lienzo, las columnas del pie se encendían con interruptores en vez de moverse,
y en la vista en cascada los dos aparecían como **una sola fila opaca** sin nada dentro.

**v3.0.0 los retira.** Ahora son secciones prearmadas (`SectionLibrary::header()` / `::footer()`) hechas
con elementos: la cabecera es una sección de dos columnas con una imagen y un bloque de botones; el pie
son dos secciones —cuatro columnas y, debajo, la línea legal a todo lo ancho, porque una columna no puede
contener otra sección—. Se sueltan igual de hechas, pero cada pieza se edita, se mueve y se quita por su
cuenta.

Lo que hizo falta añadir a los elementos para que **nada se perdiera por el camino**:

| Lo que hacía el bloque | Ahora lo hace |
|---|---|
| Logo tomado de `companies` | `image.source` = logo / portada |
| Logo a una medida concreta | `image.width` (px, %, rem — validado y recompuesto) |
| Menú de entradas | `buttons` con `direction` (fila o columna) |
| Entradas sin aspecto de botón | estilo **«enlace»** (ahí el color es el del TEXTO) |
| Hamburguesa en el teléfono | `buttons.collapse` (misma casilla + `:checked`, sin JavaScript) |
| Cabecera fija al desplazar | `section.sticky` |
| Contacto de la empresa en el pie | elemento **`companyContact`** (con `tel:`/`mailto:`) |
| Redes y logo en el pie | `companySocial` + `image.source` |

El botón suelto (`button`) sale de la paleta pero **no del registro**: hay páginas publicadas que lo usan y
guardan `label`/`href` sueltos, no una lista. Borrar el tipo haría que el saneador se las llevara por
delante en el siguiente guardado. Se sigue editando; solo no se puede añadir uno nuevo.

**Coste asumido:** una página guardada con los bloques viejos los pierde al volver a guardarse, y las
plantillas de arranque hay que resembrarlas. Es el mismo trato que en v2.0.0 y por el mismo motivo: el
componente no arrastra migraciones de contenido para tipos que duraron dos versiones.

---

### 2.15 🟡 Copiar una página entera (el «Copiar todos los bloques» de Gutenberg)

Gutenberg deja seleccionar todo y copiar: los bloques van al portapapeles como comentarios HTML
(`<!-- wp:paragraph -->`) y se pegan en otra entrada. Aquí no había ninguna forma de llevarse una
página a otra salvo «Duplicar», que solo sirve **dentro de la misma empresa y creando una página
nueva**.

**Implementado** como botón «Código», al lado de «Vista previa»: un cuadro con el árbol en JSON que
se copia y se pega en otra página. Tiene una ventaja sobre el de Gutenberg y una diferencia
consciente:

- **Ventaja:** el formato es el árbol *real*, no una serialización con el HTML dentro. Lo que se pega
  se reconstruye con el registro de bloques, así que una página copiada hoy y pegada tras un cambio de
  maquetación sale con la maquetación nueva, no con la vieja congelada en el HTML.
- **Diferencia:** **no viajan el título ni la dirección**. Identifican a la página y no pueden
  repetirse dentro de la empresa; copiarlos convertiría cada pegado en un choque de slug. Sí viajan
  los ajustes de aspecto (acento y los dos anchos), que son lo que hace que la copia se *vea* igual.

Lo pegado **no se cree**: al guardar pasa por `sanitizeTree()` como todo lo demás. Está comprobado de
punta a punta —crear una página desde la plantilla, sacar su código, pegarlo en una página en blanco,
publicar las dos y **comparar el HTML publicado**, que sale idéntico— y con un código sucio, que
pierde el tipo inventado y el `javascript:` y conserva lo bueno.

---
## 6. Verificación

Todo lo de arriba está cubierto por `php spark paginas:autotest` (sección 10, «Atributos
contrastados con Gutenberg») y por las pruebas de extremo a extremo. Lo que más vale la pena
destacar:

- **Ningún enlace interno roto**: la prueba recorre las 5 plantillas de arranque, junta los
  destinos `#…` de sus botones y las anclas declaradas, y falla si sobra alguno. Es la que
  habría cazado el fallo original.
- Toda entrada al campo ancla produce un id válido o cadena vacía — nunca algo raro dentro
  del atributo.
- El reparto 2/3 + 1/3 produce `pg-span-8` + `pg-span-4` sobre la rejilla de 12, y sin anchos
  a medida se conserva el reparto simple.
- El color del botón va al fondo en el sólido y al borde en el de borde.
- La cascada se prueba contra un DOM simulado en Node: tres profundidades, columnas
  numeradas, y al hacer clic en una columna el inspector pasa a sus propiedades.
- La sección 14 comprueba el color con transparencia (se admiten 3/4/6/8 dígitos y se rechazan
  `rgba()`, un hex de 5 y un `red; x:y`), que el fondo de la columna solo trae caja si hay color,
  que las siete viñetas están declaradas en la hoja **y** que la lista con URL produce un `<a>`
  descartando un `javascript:`.

Al día de hoy son **353 comprobaciones** en `paginas:autotest`, 92 + 9 + 152 + 25 por HTTP en las cuatro
suites de extremo a extremo y 45 sobre el DOM simulado.

---

## 7. Lo que sigue sin verificarse

El aspecto en un navegador real. Todo esto está comprobado por HTTP, por inspección del HTML
servido y con un DOM simulado, pero **el arrastre con ratón dentro del árbol, la cascada a
tamaño completo y el recorte de las imágenes hay que mirarlos a ojo**.
