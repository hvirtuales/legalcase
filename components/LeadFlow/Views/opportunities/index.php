<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;

/*
 * La bandeja de oportunidades de la firma.
 *
 * **Enseña el caso, nunca a la persona**: área, jurisdicción, ciudad, idioma, el resumen
 * y cuántas plazas quedan. El contacto aparece al reclamar, ya convertido en lead — así
 * una firma no puede recoger contactos sin comprometerse a atenderlos.
 *
 * El estado vacío explica POR QUÉ está vacío, que es lo único útil cuando no hay nada:
 * sin perfiles publicados no llega ninguna, y sin áreas marcadas tampoco.
 *
 * @var array    $rows
 * @var int      $areas     áreas en las que la firma publica
 * @var int      $profiles  perfiles publicados
 * @var int|null $left      reclamos que le quedan este mes (null = ilimitado)
 * @var int      $claimed   reclamos de este mes
 * @var bool     $canClaim
 * @var bool     $directoryOn
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-signpost"></i><h5 class="font-semibold"><?= lang('Lfl.navOpportunities') ?></h5></div>
        <a href="<?= base_url('leadflow/directorio') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-people"></i> <?= lang('Lfl.navTeam') ?>
        </a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.oppBoardHint') ?></p>

        <?php if (! $directoryOn): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Lfl.planNoDirectory') ?></span></div>
        <?php endif; ?>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded border border-line p-4">
                <p class="text-sm text-muted"><?= lang('Lfl.oppStatAvailable') ?></p>
                <p class="text-2xl font-semibold"><?= count($rows) ?></p>
            </div>
            <div class="rounded border border-line p-4">
                <p class="text-sm text-muted"><?= lang('Lfl.oppStatClaimed') ?></p>
                <p class="text-2xl font-semibold"><?= (int) $claimed ?></p>
            </div>
            <div class="rounded border border-line p-4">
                <p class="text-sm text-muted"><?= lang('Lfl.oppStatLeft') ?></p>
                <p class="text-2xl font-semibold"><?= $left === null ? '&#8734;' : (int) $left ?></p>
            </div>
        </div>

        <?php if (! $rows): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.oppEmpty') ?></p>
                <p class="mb-4 text-sm text-muted">
                    <?php if ($profiles === 0): ?>
                        <?= lang('Lfl.oppEmptyNoProfiles') ?>
                    <?php elseif ($areas === 0): ?>
                        <?= lang('Lfl.oppEmptyNoAreas') ?>
                    <?php else: ?>
                        <?= esc(lang('Lfl.oppEmptyWaiting', [(int) $areas])) ?>
                    <?php endif; ?>
                </p>
                <a href="<?= base_url('leadflow/directorio') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-people"></i> <?= lang('Lfl.navTeam') ?>
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <?php foreach ($rows as $o): ?>
                    <article class="card">
                        <div class="card-body">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold"><?= esc($o['area_name']) ?></p>
                                    <p class="text-xs text-muted">
                                        <?= esc(implode(' · ', array_filter([
                                            $o['jur_name'],
                                            $o['city'] ?: null,
                                            $o['language'] ?: null,
                                        ]))) ?>
                                    </p>
                                </div>
                                <span class="badge <?= (int) $o['left'] === 1 ? 'badge-warning' : 'badge-light' ?>">
                                    <?= esc(lang('Lfl.oppLeftBadge', [(int) $o['left']])) ?>
                                </span>
                            </div>

                            <?php if (! empty($o['summary'])): ?>
                                <p class="mt-3 whitespace-pre-line text-sm"><?= esc($o['summary']) ?></p>
                            <?php else: ?>
                                <p class="mt-3 text-sm text-muted"><?= lang('Lfl.oppNoSummary') ?></p>
                            <?php endif; ?>

                            <p class="mt-3 text-xs text-muted">
                                <i class="icon icon-clock-history"></i> <?= esc(Meta::since($o['created_at'])) ?>
                            </p>

                            <?php if ($canClaim): ?>
                                <form action="<?= base_url('leadflow/oportunidades/reclamar/' . (int) $o['id']) ?>" method="post" class="mt-4"
                                    onsubmit="return confirm('<?= esc(lang('Lfl.oppClaimConfirm'), 'js') ?>')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="icon icon-check-circle"></i> <?= lang('Lfl.oppClaim') ?>
                                    </button>
                                </form>
                                <p class="mt-2 text-xs text-muted"><?= lang('Lfl.oppClaimHint') ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
