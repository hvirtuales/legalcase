<?php
/* Errores de validación que vienen en flash como lista (además del 'error' del layout). */
$errs = (array) (session()->getFlashdata('errors') ?? []);
if ($errs): ?>
    <div class="alert alert-danger mb-4">
        <i class="icon icon-exclamation-circle"></i>
        <span><?= esc(implode(' ', $errs)) ?></span>
    </div>
<?php endif; ?>
