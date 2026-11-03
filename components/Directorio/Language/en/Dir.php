<?php

/** Group directory. Keep the same keys as `es/Dir.php`. */
return [


    'website'      => 'Website',
    'published'    => 'Published',
    'save'         => 'Save',
    'cancel'       => 'Cancel',



    // --- vistas del directorio (listado + mapa) ---
    'boards'                => 'Directory views',
    'newBoard'              => 'New view',
    'editBoard'             => 'Edit view',
    'boardsHint'            => 'Each view is a public page with a set of your companies: by sector, or the ones you pick. It shows as a list and a map at the same time.',
    'boardsEmpty'           => 'No views yet. Create the first one to publish a map of your companies.',
    'noSectorsYet'          => 'None of your companies has a sector yet. You can still create views with a manual selection, but to group by sector they must be classified first from their profile.',
    'companiesShown'        => 'Companies',
    'emptyBoardHint'        => 'This view shows no companies. Check its sector or its selection.',
    'draft'                 => 'Draft',
    'actions'               => 'Actions',
    'viewPublic'            => 'Open the public page',
    'edit'                  => 'Edit',
    'secBoardIdentity'      => 'Identification',
    'fBoardTitle'           => 'View title',
    'fBoardSlugHint'        => 'Its public address ends in:',
    'fIntro'                => 'Introduction',
    'fIntroHint'            => 'One line under the title on the public page.',
    'secWhoAppears'         => 'Which companies appear',
    'secWhoAppearsHint'     => 'Only active companies of your group are shown.',
    'modeSector'            => 'By sector',
    'modeSectorHint'        => 'Keeps itself up to date: a new company in that sector shows up on its own.',
    'modeManual'            => 'The ones I pick',
    'modeManualHint'        => 'For a fair, a promotion or one specific floor.',
    'fSector'               => 'Sector',
    'fSectorHint'           => 'Empty = every company in the group. In brackets, how many are in each sector.',
    'allCompanies'          => 'All companies',
    'fCompanies'            => 'Companies in this view',
    'noCompaniesYet'        => 'Your group has no companies yet.',
    'secMap'                => 'Map',
    'secMapHint'            => 'Empty = the map frames itself around the companies that have a location. Each company sets its coordinates on its own profile.',
    'fCenterLat'            => 'Centre latitude',
    'fCenterLng'            => 'Centre longitude',
    'fZoom'                 => 'Zoom (1-19)',
    'secPublish'            => 'Publishing',
    'fOrder'                => 'Order',
    'fPublished'            => 'Published',
    'confirmDeleteBoard'    => 'This deletes the view and its public page. Companies are NOT deleted. Continue?',
    'okBoardCreated'        => 'View created successfully.',
    'okBoardUpdated'        => 'View updated successfully.',
    'okBoardDeleted'        => 'View deleted.',
    'errBoardNotFound'      => 'View not found.',
    'searchCompanies'       => 'Search by name, sector, address…',
    'showOnMap'             => 'Show on map',
    'call'                  => 'Call',
    'noResults'             => 'No company matches the search.',
    'noCoordinates'         => 'None of these companies has a location yet.',
    'boardEmptyPublic'      => 'No companies in this view yet.',
    'countAll'              => '{0} companies',
    'countFiltered'         => '{0} of {1} companies',

    // --- botón flotante de la vista móvil ---
    'seeMap'                => 'See the map',
    'seeList'               => 'See the list',

    // --- detalle de la empresa en un popup ---
    'seeDetail'             => 'See details',
    'seeDetailOf'           => 'See details of {0}',
    'close'                 => 'Close',

    // --- aviso de empresas sin coordenadas ---
    'withoutLocation'       => '{0} without location',
    'noMapHint'             => 'Those companies show in the list but not on the map. Their coordinates are set on each company profile.',

    // --- índice público de las vistas del grupo ---
    'pickABoard'            => 'Choose what you want to see:',
    'noPublicBoards'        => 'Nothing published yet.',
    'fMode'                 => 'Content',

    // --- qué enseña cada vista: listado, mapa o los dos ---
    'secLayout'             => 'What is shown',
    'secLayoutHint'         => 'You can publish the view with both, only the list or only the map.',
    'layout_both'           => 'List and map',
    'layout_bothHint'       => 'Half the screen each. On phones a button switches between them.',
    'layout_list'           => 'Only the list',
    'layout_listHint'       => 'For companies with no physical address, where a map adds nothing.',
    'layout_map'            => 'Only the map',
    'layout_mapHint'        => 'Full screen. If no company has a location, the list is shown instead.',

    // --- filtro por sector en la página pública ---
    'filterBySector'        => 'Filter by sector',
    'allSectors'            => 'All sectors',

    // --- Rótulos del Manifest (menú lateral, panel y planes). Ver ComponentManager::t().
    'compName' => 'Directory',
    'navBoards' => 'Views and map',
    'limBoards' => 'Directory views',
];
