<?php

return [
    // --- catalogue ------------------------------------------------------
    'sectors'          => 'Sectors',
    'newSector'        => 'New sector',
    'editSector'       => 'Edit sector',
    'catalogHint'      => 'Sectors classify companies across the whole platform. They are a shared vocabulary: if every group defined its own, two directories would call the same thing by different names and there would be no way to compare them.',
    'searchPlaceholder' => 'Search sector…',
    'empty'            => 'No sectors yet. Create the first one so companies can be classified.',
    'companiesCount'   => 'Companies',
    'edit'             => 'Edit',
    'save'             => 'Save',
    'cancel'           => 'Cancel',
    'active'           => 'Active',
    'inactive'         => 'Inactive',

    // --- form -----------------------------------------------------------
    'secIdentity'      => 'Identification',
    'secIdentityHint'  => 'What the sector is called and how it is recognised.',
    'secLook'          => 'Look',
    'secLookHint'      => 'Which icon and colour it gets on cards and on the directory map markers.',
    'secPublish'       => 'Publishing',
    'fName'            => 'Name',
    'fSlug'            => 'Identifier',
    'fSlugHint'        => 'Derived from the name and used in public addresses:',
    'fDescription'     => 'Description',
    'fIcon'            => 'Icon',
    'fIconHint'        => 'Name of a platform icon, for example icon-shop. Empty = no icon.',
    'fColor'           => 'Colour',
    'fColorHint'       => 'Its map marker is painted in this colour.',
    'fOrder'           => 'Order',
    'fOrderHint'       => 'Lower first. Ties are broken by name.',
    'fStatus'          => 'Status',
    'fActive'          => 'Active',
    'fActiveHint'      => 'An inactive sector is no longer offered when classifying, but companies that already have it keep it.',

    // --- selector on the company profile ---------------------------------
    'fSector'          => 'Sector',
    'fSectorHint'      => 'What the company does. This is what groups directories and maps.',
    'noSector'         => 'Unclassified',

    // --- messages --------------------------------------------------------
    'okCreated'        => 'Sector created successfully.',
    'okUpdated'        => 'Sector updated successfully.',
    'okDeleted'        => 'Sector deleted.',
    'okDeletedWith'    => 'Sector deleted. {0} company(ies) are now unclassified.',
    'confirmDelete'    => 'Delete this sector?',
    'confirmDeleteWith' => 'This sector classifies {0} company(ies). They will not be deleted: they will become unclassified. Continue?',
    'errNotFound'      => 'Sector not found.',
];
