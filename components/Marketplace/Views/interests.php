<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array|null $rows */
$meta          = require __DIR__ . '/_meta.php';
$statusLabels  = ['new' => lang('Mkt.statusNew'), 'contacted' => lang('Mkt.statusContacted'), 'closed' => lang('Mkt.statusClosedI')];
$statusBadges  = ['new' => 'badge-danger', 'contacted' => 'badge-warning', 'closed' => 'badge-light'];
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-people"></i>
        <h5 class="font-semibold"><?= lang('Mkt.interested') ?></h5>
    </div>
    <div class="card-body">
        <?php if ($rows === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Mkt.noCompanyHint') ?></span></div>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Mkt.inboxIntro') ?></p>

            <!-- Filtro por estado -->
            <?php $states = ['' => lang('Mkt.all')] + $statusLabels; ?>
            <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
                <?php foreach ($states as $key => $label): ?>
                    <a href="<?= base_url('marketplace/interesados') . ($key ? '?status=' . $key : '') ?>"
                        class="border-b-2 px-3 py-2 text-sm <?= (string) ($status ?? '') === (string) $key ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
                        <?= esc($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($rows)): ?>
                <p class="py-10 text-center text-muted"><?= lang('Mkt.noInterest') ?></p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($rows as $i): ?>
                        <?php $phone = preg_replace('/\D+/', '', (string) ($i['whatsapp'] ?: $i['contact_phone'])); ?>
                        <div class="rounded-lg border border-line bg-surface p-4">
                            <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-ink">
                                        <i class="icon icon-buildings"></i> <?= esc($i['from_company']) ?>
                                    </div>
                                    <div class="text-xs text-muted">
                                        <?= lang('Mkt.aboutListing') ?>:
                                        <a href="<?= base_url('marketplace/publicacion/' . (int) $i['listing_id']) ?>" class="text-primary">
                                            <?= esc($i['listing_title']) ?>
                                        </a>
                                        <span class="badge <?= esc($meta['typeBadge']($i['listing_type'])) ?> ml-1">
                                            <?= esc($meta['typeLabel']($i['listing_type'])) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-faint">
                                        <i class="icon icon-clock"></i> <?= lang('Mkt.receivedAt') ?>: <?= esc($i['created_at']) ?>
                                    </div>
                                </div>
                                <span class="badge <?= $statusBadges[$i['status']] ?? 'badge-secondary' ?>">
                                    <?= esc($statusLabels[$i['status']] ?? $i['status']) ?>
                                </span>
                            </div>

                            <div class="mb-3 rounded border border-line bg-body p-3 text-sm">
                                <?= nl2br(esc($i['message'] ?? '')) ?>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <?php if (!empty($i['contact_email'])): ?>
                                    <a href="mailto:<?= esc($i['contact_email'], 'attr') ?>" class="btn btn-secondary btn-sm">
                                        <i class="icon icon-envelope"></i> <?= lang('Mkt.email') ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($phone !== ''): ?>
                                    <a href="https://wa.me/<?= esc($phone, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                                        <i class="icon icon-whatsapp"></i> <?= lang('Mkt.whatsapp') ?>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $action = static function (string $st, string $label, string $icon, string $cls) use ($i) {
                                    echo '<form action="' . base_url('marketplace/interesados/' . (int) $i['id'] . '/estado') . '" method="post" class="inline">'
                                        . csrf_field() . '<input type="hidden" name="status" value="' . $st . '">'
                                        . '<button class="btn ' . $cls . ' btn-sm"><i class="icon ' . $icon . '"></i> ' . esc($label) . '</button></form>';
                                };
                                if ($i['status'] === 'new') {
                                    $action('contacted', lang('Mkt.markContacted'), 'icon-check-circle', 'btn-primary');
                                }
                                if ($i['status'] !== 'closed') {
                                    $action('closed', lang('Mkt.markClosed'), 'icon-x-circle', 'btn-ghost');
                                } else {
                                    $action('new', lang('Mkt.reopen'), 'icon-arrow-counterclockwise', 'btn-ghost');
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
