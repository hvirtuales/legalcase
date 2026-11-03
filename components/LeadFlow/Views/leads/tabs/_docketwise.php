<?php

use Components\LeadFlow\Libraries\Meta;

$id = (int) $lead['id'];
$labels = ['contact' => 'dwContact', 'matter' => 'dwMatter', 'summary' => 'dwSummaryNote'];
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-plug"></i><h5 class="font-semibold"><?= lang('Lfl.tabDocketwise') ?></h5></div>
            <div class="card-body">
                <?php if (! $connected): ?>
                    <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i>
                        <span><?= lang('Lfl.errNotConnected') ?> <a class="underline" href="<?= base_url('leadflow/docketwise') ?>"><?= lang('Lfl.goConnect') ?></a></span>
                    </div>
                <?php endif; ?>

                <?php if ($conversion): ?>
                    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                        <span><?= lang('Lfl.conversion') ?>:</span>
                        <span class="badge <?= Meta::color(Meta::CONVERSION_COLORS, $conversion['status']) ?>"><?= esc(Meta::label(Meta::CONVERSION_STATUSES, $conversion['status'])) ?></span>
                        <span class="text-xs text-muted"><?= esc(lang('Lfl.attempts', [(int) $conversion['attempts']])) ?> · <?= esc(Meta::date($conversion['updated_at'])) ?></span>
                    </div>
                    <?php if ($conversion['status'] === 'failed'): ?>
                        <div class="alert alert-danger">
                            <i class="icon icon-exclamation-circle"></i>
                            <div class="flex flex-1 flex-wrap items-center justify-between gap-2">
                                <span><?= esc($conversion['last_error'] ?? '') ?></span>
                                <?php if ($canConvert): ?>
                                    <form action="<?= base_url('leadflow/convertir/reintentar/' . (int) $conversion['id']) ?>" method="post">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-repeat"></i> <?= lang('Lfl.retry') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="text-xs text-muted"><?= lang('Lfl.retryHint') ?></p>
                    <?php endif; ?>
                <?php elseif ($lead['status'] === 'won'): ?>
                    <p class="mb-3 text-sm"><?= lang('Lfl.readyToConvert') ?></p>
                <?php else: ?>
                    <p class="mb-3 text-sm text-muted"><?= lang('Lfl.cvNotWon') ?></p>
                <?php endif; ?>

                <?php if ($mappings): ?>
                    <h6 class="mb-2 mt-4 text-sm font-semibold"><?= lang('Lfl.externalIds') ?></h6>
                    <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                        <?php foreach ($mappings as $m): ?>
                            <div class="rounded border border-line p-2">
                                <dt class="text-xs text-muted"><?= lang('Lfl.' . ($labels[$m['entity_type']] ?? 'dwContact')) ?></dt>
                                <dd class="font-mono font-semibold">#<?= esc($m['external_id']) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>

                <?php if ($canConvert && $lead['status'] === 'won' && (! $conversion || $conversion['status'] !== 'failed')): ?>
                    <a href="<?= base_url('leadflow/convertir/' . $id) ?>" class="btn btn-primary btn-sm mt-4">
                        <i class="icon icon-box-arrow-up-right"></i> <?= lang('Lfl.convertToClient') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lfl.integrationLog') ?></h5></div>
            <div class="card-body">
                <?php if (! $logs): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($logs as $log): ?>
                            <li class="py-2 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono"><?= esc($log['action']) ?></span>
                                    <span class="badge <?= $log['status'] === 'ok' ? 'badge-success' : 'badge-danger' ?>"><?= esc($log['status']) ?></span>
                                </div>
                                <?php if ($log['message']): ?><div class="text-muted"><?= esc($log['message']) ?></div><?php endif; ?>
                                <div class="text-faint"><?= esc(Meta::date($log['created_at'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
