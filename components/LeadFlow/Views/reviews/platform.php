<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\ReviewModel;

/*
 * Moderación de opiniones, para toda la plataforma (solo Super Admin).
 *
 * **Publicar una opinión es una afirmación de la plataforma**, no de la firma, y por eso
 * se revisa aquí. Lo que hay que mirar antes de aprobar está escrito en la pantalla: que
 * el texto no prometa resultados, no identifique a un tercero y no cuente detalles del
 * caso. Eso es lo que convierte la moderación en un control real y no en un trámite.
 *
 * Rechazar pide motivo, que se le muestra a la firma: un rechazo sin explicación es
 * indistinguible de un olvido.
 *
 * @var array $reviews  opiniones contestadas de todas las firmas
 */
$badges = ['pending' => 'badge-warning', 'published' => 'badge-success', 'rejected' => 'badge-secondary'];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-shield-check"></i><h5 class="font-semibold"><?= lang('Lfl.revModerationTitle') ?></h5></div>
        <a href="<?= base_url('leadflow/opiniones') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Lfl.back') ?></a>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="icon icon-info-circle"></i>
            <div>
                <p class="mb-1 font-semibold"><?= lang('Lfl.revModerationHint') ?></p>
                <ul class="list-inside list-disc text-sm">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <li><?= lang('Lfl.revCheck' . $i) ?></li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>

        <?php if (! $reviews): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.revModEmpty') ?></p>
                <p class="text-sm text-muted"><?= lang('Lfl.revModEmptyHint') ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <article class="card mb-4">
                    <div class="card-body">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold"><?= esc($r['company_name']) ?></p>
                                <p class="text-xs text-muted">
                                    <?= esc($r['profile_name'] ?? lang('Lfl.revNoProfile')) ?>
                                    · <?= esc($r['author_name'] ?: lang('Lfl.revAnonymous')) ?>
                                    · <?= esc(Meta::date($r['created_at'])) ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-light"><?= esc(number_format((float) $r['score'], 1)) ?> / 5</span>
                                <span class="badge <?= $badges[$r['status']] ?? 'badge-light' ?>"><?= lang('Lfl.revStatus_' . $r['status']) ?></span>
                            </div>
                        </div>

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
                        <?php else: ?>
                            <p class="mt-3 text-sm text-muted"><?= lang('Lfl.revNoComment') ?></p>
                        <?php endif; ?>

                        <?php if ($r['status'] === 'pending'): ?>
                            <form action="<?= base_url('leadflow/opiniones/moderar/' . (int) $r['id']) ?>" method="post" class="mt-4">
                                <?= csrf_field() ?>
                                <div class="form-group">
                                    <label class="form-label" for="note-<?= (int) $r['id'] ?>"><?= lang('Lfl.revRejectNote') ?></label>
                                    <input type="text" id="note-<?= (int) $r['id'] ?>" name="note" maxlength="255" class="form-input">
                                    <p class="form-hint"><?= lang('Lfl.revRejectNoteHint') ?></p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" name="decision" value="publish" class="btn btn-primary btn-sm">
                                        <i class="icon icon-check-circle"></i> <?= lang('Lfl.revPublish') ?>
                                    </button>
                                    <button type="submit" name="decision" value="reject" class="btn btn-outline btn-sm">
                                        <i class="icon icon-x-circle"></i> <?= lang('Lfl.revReject') ?>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="mt-3 text-xs text-muted">
                                <?= esc(lang('Lfl.revModeratedOn', [Meta::date($r['moderated_at'])])) ?>
                                <?php if (! empty($r['reject_note'])): ?>
                                    · <?= esc($r['reject_note']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
