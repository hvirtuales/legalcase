<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\ReviewModel;

/*
 * Las opiniones de la firma.
 *
 * Tres cosas y ninguna más: **pedirlas** a los clientes convertidos, **verlas** con su
 * estado y **responder** una vez a las publicadas. No hay botón de borrar ni de editar,
 * y es a propósito: una firma que pudiera quitar las malas convertiría la media en
 * publicidad engañosa.
 *
 * El enlace de la invitación se enseña para copiar, no se envía: mandar correos en
 * nombre de la firma es una decisión suya.
 *
 * @var array $reviews
 * @var array $invitable  leads convertidos a los que aún no se les pidió
 * @var bool  $enabled
 * @var bool  $canConfigure
 */
$badges = ['pending' => 'badge-warning', 'published' => 'badge-success', 'rejected' => 'badge-secondary'];
$url    = session('lfl_review_url');
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-star"></i><h5 class="font-semibold"><?= lang('Lfl.navReviews') ?></h5></div>
        <?php if ($isSuper): ?>
            <a href="<?= base_url('leadflow/opiniones/plataforma') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-shield-check"></i> <?= lang('Lfl.revModerationTitle') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.revBoardHint') ?></p>

        <?php if (! $enabled): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Lfl.planNoReviews') ?></span></div>
        <?php endif; ?>

        <?php if ($url): ?>
            <?php // El enlace recién creado, listo para copiar y pasárselo al cliente. ?>
            <div class="alert alert-success">
                <i class="icon icon-check-circle"></i>
                <div class="min-w-0">
                    <p class="mb-1 font-semibold"><?= lang('Lfl.revLinkReady') ?></p>
                    <p class="break-all font-mono text-xs"><?= esc($url) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($enabled && $invitable): ?>
            <h6 class="mb-2 text-sm font-semibold"><?= lang('Lfl.revAskTitle') ?></h6>
            <p class="mb-3 text-sm text-muted"><?= lang('Lfl.revAskHint') ?></p>
            <div class="table-card mb-6">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.lead') ?></th>
                            <th><?= lang('Lfl.stConverted') ?></th>
                            <th class="text-right"><?= lang('Lfl.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invitable as $l): ?>
                            <tr>
                                <td>
                                    <a class="font-semibold text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $l['id']) ?>">
                                        <?= esc(LeadModel::fullName($l)) ?>
                                    </a>
                                </td>
                                <td class="text-sm text-muted"><?= esc(Meta::date($l['converted_at'])) ?></td>
                                <td>
                                    <?php if ($canInvite): ?>
                                        <form action="<?= base_url('leadflow/opiniones/pedir/' . (int) $l['id']) ?>" method="post" class="flex justify-end">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="icon icon-star"></i> <?= lang('Lfl.revAsk') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h6 class="mb-2 text-sm font-semibold"><?= lang('Lfl.revListTitle') ?></h6>
        <?php if (! $reviews): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.revEmpty') ?></p>
                <p class="mb-3 text-sm text-muted"><?= lang('Lfl.revEmptyHint') ?></p>
                <?php
                /*
                 * Sembrar ejemplos solo tiene sentido si hay clientes convertidos a
                 * quien «pedírselas»: sin ellos el botón prometería algo que no puede
                 * cumplir. Y solo para quien configura: no es trabajo del día a día.
                 */
                ?>
                <?php if ($canConfigure && $enabled && $invitable): ?>
                    <form action="<?= base_url('leadflow/opiniones/demo') ?>" method="post"
                        onsubmit="return confirm('<?= esc(lang('Lfl.revDemoConfirm'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <i class="icon icon-sparkles"></i> <?= lang('Lfl.demoButton') ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <?php foreach ($reviews as $r): ?>
                    <article class="card">
                        <div class="card-body">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold">
                                        <?= esc($r['author_name'] ?: lang('Lfl.revAnonymous')) ?>
                                        <?php if ($r['score'] !== null): ?>
                                            <span class="badge badge-light"><?= esc(number_format((float) $r['score'], 1)) ?> / 5</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-muted"><?= esc(Meta::date($r['created_at'])) ?></p>
                                </div>
                                <span class="badge <?= $badges[$r['status']] ?? 'badge-light' ?>">
                                    <?= lang('Lfl.revStatus_' . $r['status']) ?>
                                </span>
                            </div>

                            <?php if ($r['score'] === null): ?>
                                <p class="mt-3 text-sm text-muted"><?= lang('Lfl.revNotAnsweredYet') ?></p>
                                <p class="mt-1 break-all font-mono text-xs"><?= esc(base_url('opinion/' . $r['token'])) ?></p>
                            <?php else: ?>
                                <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                    <?php foreach (ReviewModel::SCORES as $field => $labelKey): ?>
                                        <div>
                                            <dt class="text-muted"><?= lang('Lfl.' . $labelKey) ?></dt>
                                            <dd class="font-semibold"><?= (int) $r[$field] ?>/5</dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                                <?php if (! empty($r['comment'])): ?>
                                    <p class="mt-3 whitespace-pre-line text-sm"><?= esc($r['comment']) ?></p>
                                <?php endif; ?>
                                <?php if (! empty($r['reject_note'])): ?>
                                    <p class="mt-2 text-xs text-danger"><?= esc($r['reject_note']) ?></p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (! empty($r['reply'])): ?>
                                <div class="mt-3 rounded border border-line p-3">
                                    <p class="text-xs font-semibold text-muted"><?= lang('Lfl.revYourReply') ?></p>
                                    <p class="whitespace-pre-line text-sm"><?= esc($r['reply']) ?></p>
                                </div>
                            <?php elseif ($canReply && $r['status'] === 'published'): ?>
                                <form action="<?= base_url('leadflow/opiniones/responder/' . (int) $r['id']) ?>" method="post" class="mt-3">
                                    <?= csrf_field() ?>
                                    <div class="form-group">
                                        <label class="form-label" for="reply-<?= (int) $r['id'] ?>"><?= lang('Lfl.revReply') ?></label>
                                        <textarea id="reply-<?= (int) $r['id'] ?>" name="reply" rows="2" maxlength="1500" class="form-textarea"></textarea>
                                        <p class="form-hint"><?= lang('Lfl.revReplyHint') ?></p>
                                    </div>
                                    <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-chat"></i> <?= lang('Lfl.send') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
