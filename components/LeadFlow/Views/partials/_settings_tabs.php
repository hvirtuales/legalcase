<?php
/**
 * Pestañas de Configuración. Servicios vive aquí y no en el menú: es un catálogo
 * que se toca al montar la firma, no a diario.
 *
 * «Equipo» ya no está aquí: se fundió con el directorio en /leadflow/directorio,
 * porque `cmp_lfl_members` y `cmp_lfl_profiles` se indexan por (empresa, usuario) y
 * mantenerlos en dos pantallas que no se hablan ya había descuadrado los datos.
 *
 * El manual («Ayuda») es la única pestaña de LECTURA: va con `lfl.view`, así que
 * quien no puede configurar solo ve esa y no pestañas que le darían un 403.
 *
 * @var string    $active
 * @var bool|null $canConfigure
 */
$canConfigure ??= true;

$tabs = [
    'general'    => ['leadflow/configuracion', 'icon-gear', lang('Lfl.setGeneral')],
    'services'   => ['leadflow/configuracion/servicios', 'icon-briefcase', lang('Lfl.setServices')],
    'stages'     => ['leadflow/configuracion/etapas', 'icon-list-columns-reverse', lang('Lfl.setStages')],
    'scoring'    => ['leadflow/configuracion/scoring', 'icon-star', lang('Lfl.setScoring')],
    'doctypes'   => ['leadflow/configuracion/documentos', 'icon-paperclip', lang('Lfl.setDocTypes')],
    'uscis'      => ['leadflow/configuracion/uscis', 'icon-landmark', lang('Lfl.setUscis')],
    'help'       => ['leadflow/configuracion/ayuda', 'icon-book', lang('Lfl.setHelp')],
];

if (! $canConfigure) {
    $tabs = ['help' => $tabs['help']];
}
?>
<nav class="tabs mb-4">
    <?php foreach ($tabs as $key => [$route, $icon, $label]): ?>
        <a href="<?= base_url($route) ?>" class="tab-link <?= $active === $key ? 'active' : '' ?>">
            <i class="icon <?= esc($icon, 'attr') ?>"></i> <?= esc($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
