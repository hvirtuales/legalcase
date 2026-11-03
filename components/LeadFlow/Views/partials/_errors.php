<?php
/**
 * Errores de validación encima del formulario. Un formulario que se traga los
 * errores en silencio parece que no guarda «porque sí».
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
