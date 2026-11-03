<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var \Components\Networking\Libraries\NetworkService $net */
$badges = [
    'pending'   => [lang('Net.statusPending'), 'badge-warning'],
    'accepted'  => [lang('Net.statusAccepted'), 'badge-success'],
    'rejected'  => [lang('Net.statusRejected'), 'badge-danger'],
    'cancelled' => [lang('Net.statusCancelled'), 'badge-secondary'],
];
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-send"></i>
        <h5 class="font-semibold"><?= lang('Net.sentTitle') ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Networking\Views\_tabs', ['counts' => $counts, 'current' => 'enviadas']) ?>

        <?php if (empty($rows)): ?>
            <p class="py-8 text-center text-muted"><?= lang('Net.noneSent') ?>
                <a href="<?= base_url('networking/descubrir') ?>" class="text-primary"><?= lang('Net.discoverPeopleLink') ?></a>.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Net.addressee') ?></th>
                            <th><?= lang('Net.reason') ?></th>
                            <th><?= lang('Net.sentAt') ?></th>
                            <th><?= lang('Net.statusLabel') ?></th>
                            <th class="text-right"><?= lang('Net.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $uid                = (int) $r['addressee_id'];
                            [$label, $badgeCls] = $badges[$r['status']] ?? ['—', 'badge-secondary'];
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('networking/perfil/' . $uid) ?>" class="font-semibold text-primary">
                                        <?= esc($net->displayName($r['other'])) ?>
                                    </a>
                                </td>
                                <td class="max-w-md text-sm text-muted"><?= esc($r['message'] ?? '') ?></td>
                                <td class="text-sm"><?= esc($r['created_at'] ?? '') ?></td>
                                <td><span class="badge <?= $badgeCls ?>"><?= esc($label) ?></span></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <?php if ($r['status'] === 'accepted'): ?>
                                            <a href="<?= base_url('networking/mensajes/' . (int) $r['id']) ?>" class="btn btn-primary btn-sm">
                                                <i class="icon icon-chat"></i> <?= lang('Net.message') ?>
                                            </a>
                                        <?php elseif ($r['status'] === 'pending'): ?>
                                            <form action="<?= base_url('networking/cancelar/' . (int) $r['id']) ?>" method="post" class="inline"
                                                onsubmit="return confirm('<?= esc(lang('Net.confirmCancel'), 'js') ?>')">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-secondary btn-sm"><i class="icon icon-x-circle"></i> <?= lang('Net.cancel') ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="<?= base_url('networking/perfil/' . $uid) ?>" class="btn btn-ghost btn-sm" title="<?= esc(lang('Net.viewProfile'), 'attr') ?>">
                                            <i class="icon icon-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
