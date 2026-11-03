<?php
/**
 * Tarjeta de publicación del catálogo. La cabecera usa la imagen de la publicación
 * y, si no hay, el banner de la empresa; el logo de la empresa va superpuesto.
 *
 * @var array $l    fila de cmp_mkt_listings + company_name/company_logo/company_banner
 * @var array $tags etiquetas de la publicación
 * @var array $meta salida de _meta.php
 */
$id    = (int) $l['id'];
$tags  = $tags ?? [];
$cover = $l['image'] ?: ($l['company_banner'] ?? null);
?>
<article class="flex flex-col overflow-hidden rounded-lg border border-line bg-surface">
    <a href="<?= base_url('marketplace/publicacion/' . $id) ?>" class="relative block aspect-[8/3] w-full bg-surface-muted">
        <?php if ($cover): ?>
            <img src="<?= media_url($cover) ?>" alt="" class="size-full object-cover">
        <?php else: ?>
            <div class="flex size-full items-center justify-center bg-gradient-to-br from-primary/20 to-primary/5">
                <i class="icon <?= esc($meta['typeIcon']($l['type'])) ?> text-3xl text-primary/60"></i>
            </div>
        <?php endif; ?>

        <span class="absolute left-2 top-2 badge <?= esc($meta['typeBadge']($l['type'])) ?>">
            <?= esc($meta['typeLabel']($l['type'])) ?>
        </span>

        <?php if (!empty($l['company_logo'])): ?>
            <img src="<?= media_url($l['company_logo']) ?>" alt=""
                class="absolute -bottom-4 right-3 size-10 rounded-md border border-line bg-surface object-contain p-1 shadow-sm">
        <?php endif; ?>
    </a>

    <div class="flex flex-1 flex-col p-4">
        <a href="<?= base_url('marketplace/publicacion/' . $id) ?>" class="font-semibold text-primary">
            <?= esc($l['title']) ?>
        </a>

        <?php if (!empty($l['summary'])): ?>
            <p class="mt-1 line-clamp-2 text-sm text-muted"><?= esc($l['summary']) ?></p>
        <?php endif; ?>

        <div class="mt-2 space-y-1 text-xs text-muted">
            <div><i class="icon icon-buildings"></i> <?= esc($l['company_name'] ?? '') ?></div>
            <?php if (!empty($l['city'])): ?>
                <div><i class="icon icon-globe"></i> <?= esc($l['city']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($tags): ?>
            <div class="mt-2 flex flex-wrap gap-1">
                <?php foreach (array_slice($tags, 0, 4) as $t): ?>
                    <a href="<?= base_url('marketplace?tag=' . urlencode($t['slug'])) ?>"
                        class="badge badge-light hover:bg-line-strong"># <?= esc($t['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-auto flex items-center justify-between gap-2 pt-3">
            <span class="text-sm font-semibold text-ink"><?= $meta['priceText']($l) ?></span>
            <a href="<?= base_url('marketplace/publicacion/' . $id) ?>" class="btn btn-secondary btn-sm">
                <?= lang('Mkt.seeDetail') ?>
            </a>
        </div>
    </div>
</article>
