<?php
/**
 * Sección reutilizable de adjuntos.
 * @var string $ownerType   voucher|payment_request|beneficiary
 * @var int    $ownerId
 * @var array  $attachments
 * @var bool   $canEdit      mostrar subir/eliminar
 * @var string $return       URL a la que volver tras subir/eliminar
 */
$canEdit ??= true;
?>
<div class="card">
    <div class="card-header"><i class="icon icon-paperclip"></i><h6 class="font-semibold">Adjuntos</h6></div>
    <div class="card-body">
        <?php if (empty($attachments)): ?>
            <p class="text-sm text-muted">Sin adjuntos.</p>
        <?php else: ?>
            <ul class="mb-3 space-y-1">
                <?php foreach ($attachments as $a): ?>
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <a href="<?= base_url('contabilidad/adjuntos/' . $a['id']) ?>" class="flex min-w-0 items-center gap-1 text-primary">
                            <i class="icon icon-download shrink-0"></i>
                            <span class="truncate"><?= esc($a['original_name']) ?></span>
                        </a>
                        <span class="flex shrink-0 items-center gap-2">
                            <span class="text-xs text-muted"><?= number_format(max(1, (int) $a['size'] / 1024), 0) ?> KB</span>
                            <?php if ($canEdit): ?>
                                <form action="<?= base_url('contabilidad/adjuntos/eliminar/' . $a['id']) ?>" method="post" onsubmit="return confirm('¿Eliminar este adjunto?')" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return" value="<?= esc($return, 'attr') ?>">
                                    <button class="btn btn-ghost btn-sm text-danger" title="Eliminar"><i class="icon icon-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canEdit): ?>
            <form action="<?= base_url('contabilidad/adjuntos/subir') ?>" method="post" enctype="multipart/form-data" class="flex items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="owner_type" value="<?= esc($ownerType, 'attr') ?>">
                <input type="hidden" name="owner_id" value="<?= (int) $ownerId ?>">
                <input type="hidden" name="return" value="<?= esc($return, 'attr') ?>">
                <input type="file" name="files[]" multiple class="form-input" required>
                <button class="btn btn-secondary btn-sm"><i class="icon icon-upload"></i> Subir</button>
            </form>
            <p class="mt-1 text-xs text-muted">PDF, imágenes, Word/Excel. Máx. 5 MB c/u.</p>
        <?php endif; ?>
    </div>
</div>
