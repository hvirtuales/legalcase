<?php

use Components\LeadFlow\Libraries\Meta;

/** La lista de eventos; la usan el resumen (los últimos) y la pestaña completa. */
?>
<?php if (! $timeline): ?>
    <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
<?php else: ?>
    <ol class="space-y-3">
        <?php foreach ($timeline as $ev): ?>
            <?php $who = trim(($ev['u_first'] ?? '') . ' ' . ($ev['u_last'] ?? '')) ?: ($ev['u_name'] ?? ''); ?>
            <li class="flex gap-3">
                <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-muted">
                    <i class="icon <?= esc(Meta::TIMELINE_ICONS[$ev['type']] ?? 'icon-circle', 'attr') ?>"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-sm"><?= esc($ev['summary']) ?></p>
                    <p class="text-xs text-muted">
                        <?= esc(Meta::date($ev['created_at'])) ?> · <?= esc($who !== '' ? $who : lang('Lfl.system')) ?>
                    </p>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
