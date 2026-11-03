<?php

/** Directorio del grupo. Mantener las mismas claves que `en/Dir.php`. */
return [


    'website'      => 'Sitio web',
    'published'    => 'Publicada',
    'save'         => 'Guardar',
    'cancel'       => 'Cancelar',



    // --- vistas del directorio (listado + mapa) ---
    'boards'                => 'Vistas del directorio',
    'newBoard'              => 'Nueva vista',
    'editBoard'             => 'Editar vista',
    'boardsHint'            => 'Cada vista es una página pública con un grupo de tus empresas: por sector, o las que elijas a mano. Se ve como listado y como mapa a la vez.',
    'boardsEmpty'           => 'Todavía no hay vistas. Crea la primera para publicar un mapa de tus empresas.',
    'noSectorsYet'          => 'Ninguna de tus empresas tiene sector asignado todavía. Puedes crear vistas con selección manual, pero para agrupar por sector hay que clasificarlas primero desde su ficha.',
    'companiesShown'        => 'Empresas',
    'emptyBoardHint'        => 'Esta vista no muestra ninguna empresa. Revisa su sector o su selección.',
    'draft'                 => 'Borrador',
    'actions'               => 'Acciones',
    'viewPublic'            => 'Ver la página pública',
    'edit'                  => 'Editar',
    'secBoardIdentity'      => 'Identificación',
    'fBoardTitle'           => 'Título de la vista',
    'fBoardSlugHint'        => 'Su dirección pública termina en:',
    'fIntro'                => 'Introducción',
    'fIntroHint'            => 'Una línea bajo el título, en la página pública.',
    'secWhoAppears'         => 'Qué empresas aparecen',
    'secWhoAppearsHint'     => 'Solo se muestran empresas activas de tu grupo.',
    'modeSector'            => 'Por sector',
    'modeSectorHint'        => 'Se mantiene sola: una empresa nueva de ese sector aparece sin tocar nada.',
    'modeManual'            => 'Las que yo elija',
    'modeManualHint'        => 'Para una feria, una promoción o un piso concreto.',
    'fSector'               => 'Sector',
    'fSectorHint'           => 'Vacío = todas las empresas del grupo. Entre paréntesis, cuántas hay en cada sector.',
    'allCompanies'          => 'Todas las empresas',
    'fCompanies'            => 'Empresas de esta vista',
    'noCompaniesYet'        => 'Tu grupo todavía no tiene empresas.',
    'secMap'                => 'Mapa',
    'secMapHint'            => 'Vacío = el mapa se encuadra solo sobre las empresas que tengan ubicación. Las coordenadas de cada empresa se ponen en su ficha.',
    'fCenterLat'            => 'Latitud del centro',
    'fCenterLng'            => 'Longitud del centro',
    'fZoom'                 => 'Zoom (1-19)',
    'secPublish'            => 'Publicación',
    'fOrder'                => 'Orden',
    'fPublished'            => 'Publicada',
    'confirmDeleteBoard'    => 'Se elimina esta vista y su página pública. Las empresas NO se borran. ¿Continuar?',
    'okBoardCreated'        => 'Vista creada correctamente.',
    'okBoardUpdated'        => 'Vista actualizada correctamente.',
    'okBoardDeleted'        => 'Vista eliminada.',
    'errBoardNotFound'      => 'No se encontró la vista.',
    'searchCompanies'       => 'Buscar por nombre, sector, dirección…',
    'showOnMap'             => 'Ver en el mapa',
    'call'                  => 'Llamar',
    'noResults'             => 'Ninguna empresa coincide con la búsqueda.',
    'noCoordinates'         => 'Ninguna de estas empresas tiene ubicación todavía.',
    'boardEmptyPublic'      => 'Todavía no hay empresas en esta vista.',
    'countAll'              => '{0} empresas',
    'countFiltered'         => '{0} de {1} empresas',

    // --- botón flotante de la vista móvil ---
    'seeMap'                => 'Ver el mapa',
    'seeList'               => 'Ver el listado',

    // --- detalle de la empresa en un popup ---
    'seeDetail'             => 'Ver el detalle',
    'seeDetailOf'           => 'Ver el detalle de {0}',
    'close'                 => 'Cerrar',

    // --- aviso de empresas sin coordenadas ---
    'withoutLocation'       => '{0} sin ubicación',
    'noMapHint'             => 'Esas empresas salen en el listado pero no en el mapa. Sus coordenadas se cargan en la ficha de cada empresa.',

    // --- índice público de las vistas del grupo ---
    'pickABoard'            => 'Elige qué quieres ver:',
    'noPublicBoards'        => 'Todavía no hay nada publicado.',
    'fMode'                 => 'Contenido',

    // --- qué enseña cada vista: listado, mapa o los dos ---
    'secLayout'             => 'Qué se enseña',
    'secLayoutHint'         => 'Puedes publicar la vista con las dos cosas, solo el listado o solo el mapa.',
    'layout_both'           => 'Listado y mapa',
    'layout_bothHint'       => 'A media pantalla cada uno. En el teléfono se cambia con un botón.',
    'layout_list'           => 'Solo el listado',
    'layout_listHint'       => 'Para empresas sin sede física, donde el mapa no aporta.',
    'layout_map'            => 'Solo el mapa',
    'layout_mapHint'        => 'A pantalla completa. Si ninguna empresa tiene ubicación, se enseña el listado igualmente.',

    // --- filtro por sector en la página pública ---
    'filterBySector'        => 'Filtrar por sector',
    'allSectors'            => 'Todos los sectores',

    // --- Rótulos del Manifest (menú lateral, panel y planes). Ver ComponentManager::t().
    'compName' => 'Directorio',
    'navBoards' => 'Vistas y mapa',
    'limBoards' => 'Vistas del directorio',
];
