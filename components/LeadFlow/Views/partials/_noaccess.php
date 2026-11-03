<?php
/**
 * El usuario tiene el permiso pero no una función comercial en esta firma: es un
 * error de configuración y hay que decirlo, no enseñar una lista vacía que parezca
 * que no hay leads.
 */
?>
<div class="alert alert-warning">
    <i class="icon icon-exclamation-circle"></i>
    <span><?= lang('Lfl.errNoRole') ?></span>
</div>
