<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Los enlaces del catálogo: uno por vendedor, con sus visitas, y los pedidos que
 * ya entraron (que son cotizaciones).
 *
 * @var array $catalog @var array $links @var array $orders
 */
$meta = require __DIR__ . '/../_meta.php';
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-link"></i>
            <h5 class="font-semibold"><?= lang('Quo.catalogLinks') ?> · <?= esc($catalog['name']) ?></h5>
        </div>
        <div class="flex gap-2">
            <a href="<?= base_url('servicios/catalogo-digital/editar/' . (int) $catalog['id']) ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-pencil"></i> <?= lang('Quo.edit') ?>
            </a>
            <a href="<?= base_url('servicios/catalogo-digital') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-chevron-left"></i> <?= lang('Quo.back') ?>
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($catalog['is_published'])): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i>
                <span><?= lang('Quo.catalogNotPublished') ?></span></div>
        <?php endif; ?>

        <?php if (!$links): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.catalogNoSellers') ?></span></div>
        <?php else: ?>
            <p class="mb-3 text-sm text-muted"><?= lang('Quo.catalogLinksHint') ?></p>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Quo.seller') ?></th>
                            <th><?= lang('Quo.catalogUrl') ?></th>
                            <th class="text-right"><?= lang('Quo.visits') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $i => $l): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($l['seller_name']) ?></strong>
                                    <div class="text-xs text-muted"><?= esc($l['seller_phone'] ?: lang('Quo.sellerNoPhone')) ?></div>
                                </td>
                                <td>
                                    <input type="text" class="form-input font-mono text-xs" readonly
                                        id="url-<?= $i ?>" value="<?= esc($l['url'], 'attr') ?>">
                                </td>
                                <td class="text-right"><?= (int) $l['visits'] ?></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" class="btn btn-secondary btn-sm" data-copy="url-<?= $i ?>"
                                            title="<?= esc(lang('Quo.cardCopyLink'), 'attr') ?>"><i class="icon icon-link"></i></button>
                                        <a href="<?= esc($l['url'], 'attr') ?>" target="_blank" rel="noopener"
                                            class="btn btn-secondary btn-sm" title="<?= esc(lang('Quo.catalogOpen'), 'attr') ?>">
                                            <i class="icon icon-box-arrow-up-right"></i></a>
                                        <a href="https://wa.me/<?= esc(preg_replace('/\D+/', '', (string) $l['seller_phone']), 'attr') ?>?text=<?= rawurlencode($l['url']) ?>"
                                            target="_blank" rel="noopener" class="btn btn-secondary btn-sm"
                                            title="<?= esc(lang('Quo.catalogSendSeller'), 'attr') ?>"><i class="icon icon-whatsapp"></i></a>
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

<div class="card mt-4">
    <div class="card-header">
        <i class="icon icon-receipt"></i>
        <h5 class="font-semibold"><?= lang('Quo.catalogOrders') ?></h5>
    </div>
    <div class="card-body">
        <?php if (!$orders): ?>
            <p class="text-sm text-muted"><?= lang('Quo.catalogNoOrders') ?></p>
        <?php else: ?>
            <p class="mb-3 text-sm text-muted"><?= lang('Quo.catalogOrdersHint') ?></p>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Quo.number') ?></th>
                            <th><?= lang('Quo.client') ?></th>
                            <th><?= lang('Quo.seller') ?></th>
                            <th><?= lang('Quo.date') ?></th>
                            <th class="text-right"><?= lang('Quo.total') ?></th>
                            <th><?= lang('Quo.status') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="font-semibold"><?= esc($o['number']) ?></td>
                                <td><?= esc($o['client_name'] ?: '—') ?>
                                    <?php if ($o['client_phone']): ?>
                                        <div class="text-xs text-muted"><?= esc($o['client_phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($o['seller_name'] ?: '—') ?></td>
                                <td><?= esc(substr((string) $o['issue_date'], 0, 10)) ?></td>
                                <td class="text-right font-semibold"><?= $meta['money']($o['total'], $o['currency']) ?></td>
                                <td><span class="badge <?= $meta['statusBadge']($o['status']) ?>"><?= esc($meta['statusLabel']($o['status'])) ?></span></td>
                                <td class="text-right">
                                    <a href="<?= base_url('servicios/cotizaciones/ver/' . (int) $o['id']) ?>" class="btn btn-secondary btn-sm">
                                        <i class="icon icon-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = document.getElementById(btn.dataset.copy);
            box.select();
            var done = function () {
                var html = btn.innerHTML;
                btn.innerHTML = '<i class="icon icon-check-circle"></i>';
                setTimeout(function () { btn.innerHTML = html; }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(box.value).then(done);
            } else {
                try { document.execCommand('copy'); done(); } catch (e) {}
            }
        });
    });
}());
</script>
<?= $this->endSection() ?>
