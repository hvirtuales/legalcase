<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array $listing @var bool $isMine @var bool $canAsk */
$meta    = require __DIR__ . '/_meta.php';
$company = $listing['company'] ?? null;
$cover   = $listing['image'] ?: ($company['banner'] ?? null);
$phone   = preg_replace('/\D+/', '', (string) ($company['whatsapp'] ?? $company['contact_phone'] ?? ''));
?>
<div class="mb-4">
    <a href="<?= base_url('marketplace') ?>" class="btn btn-secondary btn-sm">
        <i class="icon icon-chevron-left"></i> <?= lang('Mkt.back') ?>
    </a>
</div>

<?php if ($listing['status'] !== 'published'): ?>
    <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Mkt.previewNotice') ?></span></div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="relative aspect-[8/3] w-full bg-surface-muted">
                <?php if ($cover): ?>
                    <img src="<?= media_url($cover) ?>" alt="" class="size-full object-cover">
                <?php else: ?>
                    <div class="flex size-full items-center justify-center bg-gradient-to-br from-primary/20 to-primary/5">
                        <i class="icon <?= esc($meta['typeIcon']($listing['type'])) ?> text-5xl text-primary/60"></i>
                    </div>
                <?php endif; ?>
                <span class="absolute left-3 top-3 badge <?= esc($meta['typeBadge']($listing['type'])) ?>">
                    <?= esc($meta['typeLabel']($listing['type'])) ?>
                </span>
            </div>

            <div class="card-body">
                <h5 class="text-lg font-semibold text-ink"><?= esc($listing['title']) ?></h5>
                <?php if (!empty($listing['summary'])): ?>
                    <p class="mt-1 text-muted"><?= esc($listing['summary']) ?></p>
                <?php endif; ?>

                <table class="table mt-4">
                    <tbody class="text-sm">
                        <tr>
                            <td class="text-muted"><?= lang('Mkt.price') ?></td>
                            <td class="font-semibold"><?= $meta['priceText']($listing) ?></td>
                        </tr>
                        <?php if (!empty($listing['category'])): ?>
                            <tr><td class="text-muted"><?= lang('Mkt.category') ?></td><td><?= esc($listing['category']['name']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($listing['city'])): ?>
                            <tr><td class="text-muted"><?= lang('Mkt.city') ?></td><td><?= esc($listing['city']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($listing['valid_until'])): ?>
                            <tr><td class="text-muted"><?= lang('Mkt.validUntil') ?></td><td><?= esc(substr((string) $listing['valid_until'], 0, 10)) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($listing['tags'])): ?>
                            <tr>
                                <td class="text-muted"><?= lang('Mkt.tags') ?></td>
                                <td>
                                    <?php foreach ($listing['tags'] as $t): ?>
                                        <a href="<?= base_url('marketplace?tag=' . urlencode($t['slug'])) ?>" class="badge badge-light"># <?= esc($t['name']) ?></a>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($listing['description'])): ?>
                    <h6 class="mb-1 mt-4 font-semibold text-muted"><?= lang('Mkt.description') ?></h6>
                    <p class="text-sm"><?= nl2br(esc($listing['description'])) ?></p>
                <?php endif; ?>

                <p class="mt-4 text-xs text-faint"><?= lang('Mkt.viewsCount', [(int) $listing['views']]) ?></p>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <!-- Empresa que publica -->
        <div class="card">
            <div class="card-header"><i class="icon icon-buildings"></i><h6 class="font-semibold"><?= lang('Mkt.publishedBy') ?></h6></div>
            <div class="card-body">
                <div class="flex items-center gap-3">
                    <?php if (!empty($company['logo'])): ?>
                        <img src="<?= media_url($company['logo']) ?>" alt=""
                            class="size-12 rounded-md border border-line bg-surface object-contain p-1">
                    <?php endif; ?>
                    <div>
                        <div class="font-semibold text-ink"><?= esc($company['name'] ?? '') ?></div>
                        <?php if (!empty($company['address'])): ?>
                            <div class="text-xs text-muted"><?= esc($company['address']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 space-y-2">
                    <?php if (!empty($company['contact_email'])): ?>
                        <a href="mailto:<?= esc($company['contact_email'], 'attr') ?>" class="btn btn-secondary btn-sm w-full">
                            <i class="icon icon-envelope"></i> <?= lang('Mkt.email') ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($phone !== ''): ?>
                        <a href="https://wa.me/<?= esc($phone, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm w-full">
                            <i class="icon icon-whatsapp"></i> <?= lang('Mkt.whatsapp') ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($company['website'])): ?>
                        <a href="<?= esc($company['website'], 'attr') ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm w-full">
                            <i class="icon icon-external-link"></i> <?= lang('Mkt.website') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cruce oferta/demanda -->
        <div class="card">
            <div class="card-header"><i class="icon icon-send"></i><h6 class="font-semibold"><?= lang('Mkt.imInterested') ?></h6></div>
            <div class="card-body">
                <?php if ($isMine): ?>
                    <p class="mb-3 text-sm text-muted"><?= lang('Mkt.ownListing') ?></p>
                    <a href="<?= base_url('marketplace/editar/' . (int) $listing['id']) ?>" class="btn btn-primary btn-sm w-full">
                        <i class="icon icon-pencil"></i> <?= lang('Mkt.edit') ?>
                    </a>
                <?php elseif ($canAsk): ?>
                    <p class="mb-2 text-xs text-muted"><?= lang('Mkt.interestIntro') ?></p>
                    <form action="<?= base_url('marketplace/publicacion/' . (int) $listing['id'] . '/interes') ?>" method="post">
                        <?= csrf_field() ?>
                        <label for="message" class="form-label text-xs"><?= lang('Mkt.yourMessage') ?> <span class="text-danger">*</span></label>
                        <textarea id="message" name="message" rows="4" required minlength="10" maxlength="1000"
                            class="form-textarea text-sm" placeholder="<?= esc(lang('Mkt.messagePlaceholder'), 'attr') ?>"></textarea>
                        <button class="btn btn-primary btn-sm mt-2 w-full">
                            <i class="icon icon-send"></i> <?= lang('Mkt.sendInterest') ?>
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-sm text-muted"><?= lang('Mkt.errNoCompany') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
