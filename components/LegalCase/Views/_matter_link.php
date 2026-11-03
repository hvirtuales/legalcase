<?php
/** Enlace corto a un asunto en listas transversales. @var array $numbers @var int $mid */
$m = $numbers[$mid] ?? null;
?>
<?php if ($m): ?>
    <a class="font-mono text-xs text-primary" href="<?= base_url('legalcase/asuntos/ficha/' . $mid) ?>"><?= esc($m['number']) ?></a>
<?php endif; ?>
