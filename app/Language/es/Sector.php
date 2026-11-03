<?php

return [
    // --- catálogo -------------------------------------------------------
    'sectors'          => 'Sectores',
    'newSector'        => 'Nuevo sector',
    'editSector'       => 'Editar sector',
    'catalogHint'      => 'Los sectores clasifican a las empresas de toda la plataforma. Son un vocabulario común: si cada grupo definiera los suyos, dos directorios llamarían distinto a lo mismo y no habría forma de compararlos.',
    'searchPlaceholder' => 'Buscar sector…',
    'empty'            => 'Todavía no hay sectores. Crea el primero para poder clasificar empresas.',
    'companiesCount'   => 'Empresas',
    'edit'             => 'Editar',
    'save'             => 'Guardar',
    'cancel'           => 'Cancelar',
    'active'           => 'Activo',
    'inactive'         => 'Inactivo',

    // --- formulario -----------------------------------------------------
    'secIdentity'      => 'Identificación',
    'secIdentityHint'  => 'Cómo se llama el sector y cómo se le reconoce.',
    'secLook'          => 'Aspecto',
    'secLookHint'      => 'Con qué icono y color se pinta en las fichas y en los marcadores del mapa del directorio.',
    'secPublish'       => 'Publicación',
    'fName'            => 'Nombre',
    'fSlug'            => 'Identificador',
    'fSlugHint'        => 'Se calcula solo a partir del nombre y se usa en las direcciones públicas:',
    'fDescription'     => 'Descripción',
    'fIcon'            => 'Icono',
    'fIconHint'        => 'Nombre de un icono de la plataforma, por ejemplo icon-shop. Vacío = sin icono.',
    'fColor'           => 'Color',
    'fColorHint'       => 'Con este color se pinta su marcador en el mapa.',
    'fOrder'           => 'Orden',
    'fOrderHint'       => 'Menor primero. Con el mismo número se ordenan por nombre.',
    'fStatus'          => 'Estado',
    'fActive'          => 'Activo',
    'fActiveHint'      => 'Un sector inactivo deja de ofrecerse al clasificar, pero las empresas que ya lo tienen lo conservan.',

    // --- selector en la ficha de la empresa -----------------------------
    'fSector'          => 'Sector',
    'fSectorHint'      => 'A qué se dedica la empresa. Es lo que agrupa los directorios y los mapas.',
    'noSector'         => 'Sin clasificar',

    // --- mensajes -------------------------------------------------------
    'okCreated'        => 'Sector creado correctamente.',
    'okUpdated'        => 'Sector actualizado correctamente.',
    'okDeleted'        => 'Sector eliminado.',
    'okDeletedWith'    => 'Sector eliminado. {0} empresa(s) quedaron sin clasificar.',
    'confirmDelete'    => '¿Eliminar este sector?',
    'confirmDeleteWith' => 'Este sector clasifica a {0} empresa(s). No se borrarán: quedarán sin clasificar. ¿Continuar?',
    'errNotFound'      => 'No se encontró el sector.',
];
