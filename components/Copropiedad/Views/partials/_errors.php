<?php
/**
 * Los errores de validación del modelo, encima del formulario.
 *
 * Va en un partial porque los ocho formularios del componente lo necesitan igual, y
 * un formulario que se traga los errores en silencio parece que no guarda «porque
 * sí».
 */
?>
<?php if (session()->getFlashdata('errors')): ?>
    <div class="alert alert-danger">
        <ul class="list-inside list-disc">
            <?php foreach ((array) session()->getFlashdata('errors') as $e): ?>
                <li><?= esc($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
