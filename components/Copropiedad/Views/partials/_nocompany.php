<?php
/**
 * Lo que se ve sin empresa activa.
 *
 * Todas las pantallas del componente lo usan: el registro es de una copropiedad
 * concreta, así que en contexto Global no hay nada que enseñar y hay que decirlo,
 * no dejar una tabla vacía que parezca un error.
 */
?>
<div class="alert alert-info">
    <i class="icon icon-info-circle"></i>
    <span><?= lang('Cop.errNoCompany') ?></span>
</div>
