<?php

/**
 * Componente Páginas — constructor de páginas web por bloques (tipo Gutenberg).
 *
 * Una página **es un JSON de bloques**, no HTML guardado. El HTML se genera en
 * cada visita con `Libraries\Renderer`, que es la ÚNICA autoridad sobre la
 * salida: así el contenido queda estructurado (se puede reordenar, re-tematizar
 * o volcar a otro formato) y nunca se persiste marcado sin validar.
 *
 * Piezas:
 *   1. `BlockRegistry` — catálogo de tipos de bloque y el esquema de sus campos.
 *   2. Editor drag & drop (`Views/editor.php` + `assets/js/editor.js`).
 *   3. Bloques personalizados (`cmp_pag_blocks`): guardar un bloque y reusarlo.
 *   4. `Renderer` — JSON → HTML, escapando y validando todo.
 *   5. Plantillas reutilizables (`cmp_pag_templates`) con juego de arranque.
 *   6. `SectionLibrary` — secciones ya configuradas (banner, tarjetas de 2/3/4
 *      columnas, contenido y pie de página) que se sueltan armadas. Van en
 *      código, no en BD: no son de ninguna empresa.
 *   7. Bloques que leen `companies` (portada con banner y logo, QR, redes y
 *      WhatsApp flotante): no vuelven a pedir datos que la plataforma ya tiene.
 *
 * Multi-tenant: páginas, plantillas, bloques y mensajes llevan `company_id` y se
 * filtran por la empresa activa. La página publicada es pública por definición.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'pag',
    'name'          => 'Páginas',
    'name_key'      => 'Pag.compName',
    // 2.0.0 — CAMBIO QUE ROMPE: se retira el bloque «portada de empresa»
    // (banner decorativo) y entran «cabecera del sitio» (logo + menú
    // responsive, sin JavaScript) y «pie de página» de 4 columnas. Nuevo
    // bloque «botones en fila». Las páginas que usaran la portada pierden ese
    // bloque al guardarse; las plantillas de arranque hay que resembrarlas.
    // 1.6.0 — los ajustes de la página se editan DENTRO del editor, en la
    // segunda pestaña del inspector (ruta JSON propia): cambiar el ancho o el
    // color ya no obliga a abandonar lo que se está montando.
    // 1.5.0 — cada sección prearmada se entrega envuelta en un bloque
    // `section` (una unidad que se mueve y se edita de una vez, con sus
    // elementos dentro); el botón gana radio y color al pasar por encima, y
    // sus colores viajan como variables CSS para que el `:hover` los use.
    // Nueva sección 13 del autotest: cobertura de TODA propiedad de TODO
    // elemento, en el servidor y en el lienzo del editor.
    // 1.4.0 — tres pestañas en la columna izquierda (Elementos sueltos en
    // rejilla · Bloques ya armados · Estructura), columnas más anchas y
    // plegables, los dos anchos de página de Gutenberg (contenido/amplio) y
    // espaciado por bloque (margen y relleno) con la escala del componente.
    // 1.3.0 — el editor pasa a PANTALLA COMPLETA con lienzo propio
    // (`Views/_editor_layout.php`): la barra lateral del panel se comía el ancho
    // que necesitan las tres columnas.
    // 1.2.0 — pestaña «Estructura» (vista en cascada + esquema de títulos) y
    // los atributos que faltaban tras contrastar con Gutenberg: ancla en todo
    // bloque, ancho por columna, imagen de fondo de sección, recorte de imagen,
    // color y capitular del párrafo, color y ancho del botón, nombre de la red.
    // Ver proyectos/PAGINAS_VS_GUTENBERG.md.
    // 1.1.0 — bloques de empresa, plantilla ejecutiva y secciones prearmadas.
    // Solo añade: no hay migraciones nuevas ni cambios que rompan (SemVer §13).
    // 3.8.3 — el `PlanGuard` deja de exigir `CompanyModel::effectivePlan()`,
    // que llegó con los tenants: en una instalación sin ellos reventaba con
    // «Call to undefined method». Si no está, resuelve el plan de la propia
    // empresa (contrato §15.2: degradar solo).
    // 3.8.2 — UN solo botón de guardar. El de la barra mandaba solo los
    // BLOQUES y el título/dirección vivían en un formulario aparte con su
    // propio botón: escribías el título, pulsabas «Guardar» y no se guardaba,
    // y había que guardar dos veces. Ahora guarda los ajustes y luego los
    // bloques, y tocar un ajuste deja el estado en «Sin guardar».
    // 3.8.1 — el componente deja de depender de `App\Libraries\ImageOptimizer`
    // (código que él mismo estrenó en el núcleo: soltarlo en otra instalación
    // habría reventado). Lleva su copia y llama a `storeImage()` con la firma
    // de tres argumentos. Auditoría del contrato: `spark paginas:contrato`.
    // 3.8.0 — BIBLIOTECA DE MEDIOS de la empresa (`cmp_pag_media`, pantalla
    // `/paginas/medios` y selector dentro del editor: elegir una imagen ya
    // subida en vez de volver a subirla). Toda imagen se guarda en **WebP** y
    // acotada a 1300 px de ancho —o al del espacio donde va— vía el nuevo
    // `App\Libraries\ImageOptimizer` del núcleo, que usa `UploadService` y por
    // tanto vale también para logos, portadas y fotos de producto.
    // 3.7.0 — el guardado reintenta con un token nuevo cuando el de CSRF ya
    // no vale (rota en cada POST; otra pestaña lo invalidaba y TODOS los
    // guardados daban 403). Espaciado y tinta llegan por fin a la SECCIÓN y a
    // la COLUMNA en el lienzo —no pasan por `withSpacing`—, y el relleno de la
    // sección deja de aplicarse dos veces. Alineación vertical del contenido
    // de la columna, color al pasar por encima en la FILA de botones (y los
    // cuatro estilos lo usan). Las plantillas de arranque se RETIRAN y el alta
    // abre un diálogo: en blanco o desde una plantilla de la empresa.
    // 3.6.0 — se RETIRA la pantalla de ajustes (`ajustes/{id}`): sus campos
    // ya vivían duplicados en la pestaña «Página» del inspector. Crear una
    // página entra DIRECTO al editor (POST, sin pantalla que pida el título)
    // y al guardar se recuerda ponerle uno si sigue con el provisional. El
    // botón «Ajustes» de la barra abre esa pestaña en vez de navegar.
    // 3.5.0 — el COLOR DEL TEXTO pasa a ser campo común de todo bloque (18
    // de los 21; el separador ya tiene el de su línea, el espacio es
    // invisible y el HTML avanzado trae el suyo). Lo tenían cinco tipos, así
    // que no había forma de aclarar una cita, una lista o unas tarjetas sobre
    // un fondo oscuro —ni de darles opacidad, que vive en el mismo control—.
    // En la sección y en la columna cascada a lo que lleven dentro.
    'version'       => '3.8.3',
    'description'   => 'Constructor de páginas web por bloques: editor drag & drop, bloques personalizados, plantillas reutilizables y publicación con URL propia.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-globe',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_pag_',

    // Columna booleana de `plans` que habilita el componente; el cupo va en
    // `plans.max_pages` (0 = ilimitado). Ambas las añade la migración propia
    // 2026-08-07-100005_AddPagesLimitsToPlans (contrato §11).
    'plan_flag'     => 'pages_enabled',
    'plan_limits'   => [
        ['column' => 'max_pages', 'label' => 'Páginas', 'label_key' => 'Pag.limPages', 'table' => 'cmp_pag_pages'],
    ],

    'permissions'   => [
        'pag.view'      => 'Ver las páginas, plantillas y mensajes recibidos',
        'pag.create'    => 'Crear páginas y duplicarlas',
        'pag.edit'      => 'Editar el contenido de las páginas y publicarlas',
        'pag.delete'    => 'Eliminar páginas y mensajes',
        'pag.configure' => 'Gestionar plantillas y bloques reutilizables',
    ],

    'menus'         => [
        ['label' => 'Páginas', 'label_key' => 'Pag.navPages', 'icon' => 'icon-journal-text', 'route' => 'paginas', 'permission' => 'pag.view', 'order' => 60],
        ['label' => 'Medios', 'label_key' => 'Pag.navMedia', 'icon' => 'icon-image', 'route' => 'paginas/medios', 'permission' => 'pag.view', 'order' => 61],
        ['label' => 'Plantillas', 'label_key' => 'Pag.navTemplates', 'icon' => 'icon-list-columns-reverse', 'route' => 'paginas/plantillas', 'permission' => 'pag.view', 'order' => 62],
        ['label' => 'Bloques', 'label_key' => 'Pag.navBlocks', 'icon' => 'icon-puzzle', 'route' => 'paginas/bloques', 'permission' => 'pag.view', 'order' => 63],
        ['label' => 'Mensajes', 'label_key' => 'Pag.navMessages', 'icon' => 'icon-envelope', 'route' => 'paginas/mensajes', 'permission' => 'pag.view', 'order' => 64],
    ],

    // `pagina` es el prefijo público (la URL que se comparte) y también sirve
    // los assets del editor: el núcleo nunca implementó la publicación a
    // public/components/<code>/ que menciona el contrato §2.
    'routes'        => ['paginas', 'pagina'],
    'settings'      => [],
];
