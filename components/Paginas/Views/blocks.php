<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Bloques personalizados de la empresa.
 *
 * Cada uno se muestra **renderizado de verdad** (por `Libraries\Renderer`, no
 * por una miniatura): entre diez bloques guardados, el nombre no basta para
 * reconocer cuál es cuál.
 *
 * @var array|null $rows
 * @var array      $previews  id => HTML
 * @var callable   $typeLabel
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-puzzle"></i>
            <h5 class="font-semibold"><?= lang('Pag.blocks') ?></h5>
        </div>
        <a href="<?= base_url('paginas') ?>" class="btn btn-ghost btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('Pag.back') ?>
        </a>
    </div>

    <div class="card-body">
        <?php if ($rows === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Pag.noCompanyHint') ?></span></div>
        <?php elseif (!$rows): ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i><span><?= lang('Pag.noBlocksHint') ?></span>
            </div>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Pag.blocksIntro') ?></p>

            <style>
                /* La vista previa usa las clases de la página publicada; se acota
                   aquí para que no se mezcle con el resto del panel. */
                <?= $this->include('Components\Paginas\Views\public\_styles') ?>
                .pag-bprev { max-height: 16rem; overflow: hidden; pointer-events: none; }
            </style>

            <div class="grid gap-4 lg:grid-cols-2">
                <?php foreach ($rows as $b): ?>
                    <article class="overflow-hidden rounded-lg border border-line bg-surface">
                        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-2">
                            <div class="min-w-0">
                                <h6 class="truncate font-semibold"><?= esc($b['name']) ?></h6>
                                <p class="text-xs text-muted">
                                    <?= esc($typeLabel((string) $b['root_type'])) ?>
                                    · <?= esc(substr((string) $b['created_at'], 0, 10)) ?>
                                </p>
                            </div>
                            <?= view('partials/delete_button', [
                                'action'  => base_url('paginas/bloques/eliminar/' . (int) $b['id']),
                                'confirm' => lang('Pag.confirmDeleteBlock'),
                                'label'   => lang('Pag.delete'),
                            ]) ?>
                        </div>
                        <div class="pag-bprev pg-root" data-pg-theme="light">
                            <?= $previews[$b['id']] ?? '' ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
