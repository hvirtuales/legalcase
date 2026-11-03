<?php
/**
 * Bitácora del asunto (solo lectura): quién publicó, revocó, invitó, descargó o validó.
 *
 * @var array $audit @var array $users
 */
use Components\LegalCase\Libraries\Meta;
?>
<div class="card">
    <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lgc.tab_bitacora') ?></h5></div>
    <div class="card-body">
        <p class="mb-3 text-xs text-muted"><?= lang('Lgc.auditHint') ?></p>
        <?php if (!$audit): ?>
            <p class="text-sm text-muted"><?= lang('Lgc.noAudit') ?></p>
        <?php else: ?>
            <div class="table-card overflow-x-auto">
                <table class="table">
                    <thead><tr><th><?= lang('Lgc.fDate') ?></th><th><?= lang('Lgc.fActor') ?></th><th><?= lang('Lgc.fAction') ?></th><th>IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($audit as $a): ?>
                        <tr>
                            <td class="whitespace-nowrap text-xs"><?= esc(Meta::date($a['created_at'])) ?></td>
                            <td class="text-xs">
                                <?php if ($a['actor_type'] === 'portal'): ?>
                                    <span class="badge badge-info"><?= lang('Lgc.actorPortal') ?></span>
                                <?php elseif ($a['actor_type'] === 'system'): ?>
                                    <span class="badge badge-light"><?= lang('Lgc.system') ?></span>
                                <?php else: ?>
                                    <?= esc($users[(int) $a['actor_id']] ?? '#' . $a['actor_id']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs"><code><?= esc($a['action']) ?></code></td>
                            <td class="text-xs text-muted"><?= esc($a['ip']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
