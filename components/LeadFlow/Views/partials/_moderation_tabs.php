<?php
/**
 * Pestañas de Moderación.
 *
 * Antes eran dos entradas del menú —«Verificación» y «Denuncias»— que revisaban el
 * MISMO objeto, el perfil, con el mismo revisor. Separadas, para decidir si verificabas
 * a alguien tenías que acordarte de ir a la otra a ver si estaba denunciado, que es
 * justo el dato que más pesa.
 *
 * La segunda pestaña solo la ve el Super Admin: el texto de una denuncia lo escribió un
 * ciudadano sobre una firma, y leerlo es otra cosa que saber que existe. El Verificador
 * ve el recuento en la cola y nada más.
 *
 * @var string    $active   'perfiles' | 'denuncias'
 * @var bool|null $isSuper
 * @var int|null  $pending  denuncias sin atender, para el distintivo
 */
$isSuper ??= false;
$pending ??= 0;
?>
<nav class="tabs mb-4">
    <a href="<?= base_url('leadflow/moderacion') ?>" class="tab-link <?= $active === 'perfiles' ? 'active' : '' ?>">
        <i class="icon icon-patch-check"></i> <?= lang('Lfl.modProfiles') ?>
    </a>
    <?php if ($isSuper): ?>
        <a href="<?= base_url('leadflow/moderacion/denuncias') ?>" class="tab-link <?= $active === 'denuncias' ? 'active' : '' ?>">
            <i class="icon icon-shield-check"></i> <?= lang('Lfl.repTrayTitle') ?>
            <?php if ($pending > 0): ?>
                <span class="badge badge-danger"><?= (int) $pending ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
</nav>
