<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Panel del coordinador (HC-C09): asuntos con la comunicación comprometida vencida,
 * actualizaciones esperando aprobación o publicación, y avisos que el proveedor rechazó.
 *
 * @var array $overdue @var array $reviews @var array $failed @var array $numbers @var array $users
 */
use Components\LegalCase\Libraries\Meta;

$link = fn (int $mid) => view('Components\LegalCase\Views\_matter_link', ['numbers' => $numbers, 'mid' => $mid]);
?>
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card min-w-0 lg:col-span-2">
        <div class="card-header"><i class="icon icon-send text-danger"></i><h5 class="font-semibold"><?= lang('Lgc.overdueComms') ?> (<?= count($overdue) ?>)</h5></div>
        <div class="card-body">
            <?php if (!$overdue): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noOverdueComms') ?></p>
            <?php else: ?>
                <div class="table-card overflow-x-auto">
                    <table class="table">
                        <thead><tr><th><?= lang('Lgc.matter') ?></th><th><?= lang('Lgc.fOwner') ?></th><th><?= lang('Lgc.fNextUpdate') ?></th><th><?= lang('Lgc.lastPublished') ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($overdue as $o): ?>
                            <tr>
                                <td><a class="text-primary" href="<?= base_url('legalcase/asuntos/ficha/' . (int) $o['matter_id'] . '?tab=comunicacion') ?>"><span class="font-mono"><?= esc($o['number']) ?></span> · <?= esc($o['title']) ?></a></td>
                                <td><?= esc($users[(int) ($o['owner_id'] ?: $o['lead_lawyer_id'])] ?? '—') ?></td>
                                <td class="font-semibold text-danger"><?= esc(Meta::date($o['next_update_due_at'], false)) ?></td>
                                <td class="text-muted"><?= esc(Meta::date($o['last_published_at'], false)) ?></td>
                                <td class="text-right"><a class="btn btn-secondary btn-sm" href="<?= base_url('legalcase/asuntos/actualizacion/' . (int) $o['matter_id'] . '?tipo=no_news') ?>"><i class="icon icon-history"></i> <?= lang('Lgc.updNoNews') ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-check-circle"></i><h5 class="font-semibold"><?= lang('Lgc.toApprove') ?></h5></div>
        <div class="card-body">
            <?php if (!$reviews): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noReviews') ?></p>
            <?php else: ?>
                <ul class="divide-y divide-line text-sm">
                    <?php foreach ($reviews as $r): ?>
                        <li class="py-2"><?= $link((int) $r['matter_id']) ?> <a class="text-primary" href="<?= base_url('legalcase/actualizaciones/ver/' . (int) $r['id']) ?>"><?= esc($r['title']) ?></a> <?= Meta::badge('upd', $r['status']) ?>
                            <div class="text-xs text-muted"><?= esc($users[(int) $r['author_id']] ?? '—') ?> · <?= esc(Meta::date($r['submitted_at'])) ?></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-exclamation-circle text-danger"></i><h5 class="font-semibold"><?= lang('Lgc.failedDeliveries') ?></h5></div>
        <div class="card-body">
            <p class="mb-2 text-xs text-muted"><?= lang('Lgc.failedDeliveriesHint') ?></p>
            <?php if (!$failed): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noFailed') ?></p>
            <?php else: ?>
                <ul class="divide-y divide-line text-sm">
                    <?php foreach ($failed as $f): ?>
                        <li class="py-2"><?= $link((int) $f['matter_id']) ?> <?= esc($f['recipient']) ?> · <?= lang('Lgc.dkind_' . $f['kind']) ?>
                            <div class="text-xs text-danger"><?= esc($f['last_error']) ?> (<?= (int) $f['attempts'] ?>)</div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
