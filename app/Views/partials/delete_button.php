<?php
/**
 * Botón de acción destructiva segura: POST + token CSRF + confirmación.
 * Reemplaza los enlaces GET de borrado (que CSRF no protege).
 *
 * @var string      $action  URL destino (obligatorio)
 * @var string|null $confirm Mensaje de confirmación
 * @var string|null $class   Clases del botón (default: btn btn-danger btn-sm)
 * @var string|null $icon    Icono de public/css/icons.css sin el prefijo `icon-` (default: trash)
 * @var string|null $label   title/aria (default: Eliminar)
 * @var string|null $text    Texto opcional junto al icono
 */
$confirm = $confirm ?? '¿Eliminar este registro? Esta acción no se puede deshacer.';
$class   = $class   ?? 'btn btn-danger btn-sm';
$icon    = $icon    ?? 'trash';
$label   = $label   ?? 'Eliminar';
?>
<form action="<?= $action ?>" method="post" class="inline"
    onsubmit="return confirm('<?= esc($confirm, 'attr') ?>');">
    <?= csrf_field() ?>
    <button type="submit" class="<?= esc($class, 'attr') ?>" title="<?= esc($label, 'attr') ?>">
        <i class="icon icon-<?= esc($icon, 'attr') ?>"></i><?= !empty($text) ? ' ' . esc($text) : '' ?>
    </button>
</form>
