<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Ayuda de LegalCase: la guía de uso con capturas de referencia.
 *
 * Todo sale de `HelpGuide` (pasos, vocabulario, permisos y preguntas): añadir un paso
 * no toca esta plantilla. El botón «Abrir» de cada paso solo aparece si existe pantalla
 * propia Y quien lee tiene el permiso: un enlace que devuelve «no autorizado» es peor
 * que no ofrecerlo.
 *
 * @var array  $perms  [permiso => bool] de quien lee
 * @var string $locale
 */
use Components\LegalCase\Libraries\HelpGuide;

$img = static fn (string $step): string => base_url('legalcase/ayuda/imagen/' . HelpGuide::imageFile($step, $locale));
$n   = 0;
?>
<div class="card mb-4">
    <div class="card-header"><i class="icon icon-life-preserver"></i><h5 class="font-semibold"><?= lang('Lgc.hlpTitle') ?></h5></div>
    <div class="card-body">
        <p class="mb-2 max-w-3xl text-sm text-muted"><?= lang('Lgc.hlpIntro') ?></p>
        <p class="mb-4 text-xs text-muted"><i class="icon icon-info-circle"></i> <?= lang('Lgc.hlpDemoNote') ?></p>
        <nav class="flex flex-wrap gap-2" aria-label="<?= esc(lang('Lgc.hlpIndex'), 'attr') ?>">
            <?php foreach (HelpGuide::STEPS as $key => $s): ?>
                <a href="#paso-<?= esc($key, 'attr') ?>" class="btn btn-outline btn-sm"><i class="icon <?= esc($s['icon'], 'attr') ?>"></i> <?= lang('Lgc.hlp_' . $key) ?></a>
            <?php endforeach; ?>
            <a href="#vocabulario" class="btn btn-outline btn-sm"><i class="icon icon-journal-text"></i> <?= lang('Lgc.hlpTermsTitle') ?></a>
            <a href="#permisos" class="btn btn-outline btn-sm"><i class="icon icon-shield-check"></i> <?= lang('Lgc.hlpPermsTitle') ?></a>
            <a href="#preguntas" class="btn btn-outline btn-sm"><i class="icon icon-life-preserver"></i> <?= lang('Lgc.hlpFaqTitle') ?></a>
        </nav>
    </div>
</div>

<?php foreach (HelpGuide::STEPS as $key => $s): ?>
    <?php
    $n++;
    $canOpen = $s['route'] !== null && ! empty($perms[$s['perm']]);
    ?>
    <section id="paso-<?= esc($key, 'attr') ?>" class="card mb-4 scroll-mt-4">
        <div class="card-body grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <p class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted">
                    <span class="inline-grid h-6 w-6 place-items-center rounded-full bg-ink text-[11px] text-surface"><?= (int) $n ?></span>
                    <?= esc(lang('Lgc.hlpStep', [$n])) ?>
                </p>
                <h5 class="mb-2 flex items-center gap-2 text-lg font-semibold"><i class="icon <?= esc($s['icon'], 'attr') ?>"></i> <?= lang('Lgc.hlp_' . $key) ?></h5>
                <p class="mb-3 text-sm text-muted"><?= lang('Lgc.hlp_' . $key . 'Body') ?></p>
                <ul class="mb-4 list-disc space-y-1.5 pl-5 text-sm">
                    <?php for ($p = 1; $p <= (int) $s['points']; $p++): ?>
                        <li><?= lang('Lgc.hlp_' . $key . 'P' . $p) ?></li>
                    <?php endfor; ?>
                </ul>
                <?php if ($s['route'] === null): ?>
                    <p class="mb-3 text-xs text-muted"><strong><?= lang('Lgc.hlpWhere') ?>:</strong> <?= lang('Lgc.hlp_' . $key . 'Where') ?></p>
                <?php endif; ?>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($canOpen): ?>
                        <a href="<?= base_url($s['route']) ?>" class="btn btn-primary btn-sm"><i class="icon icon-chevron-right"></i> <?= lang('Lgc.hlpOpen') ?></a>
                    <?php endif; ?>
                    <span class="badge badge-light"><?= esc(lang('Lgc.hlpNeeds', [$s['perm']])) ?></span>
                </div>
            </div>
            <figure class="lg:col-span-3">
                <a href="<?= $img($key) ?>" target="_blank" rel="noopener" title="<?= esc(lang('Lgc.hlpEnlarge'), 'attr') ?>" class="block overflow-hidden rounded-lg border border-line bg-surface-muted">
                    <img src="<?= $img($key) ?>" alt="<?= esc(lang('Lgc.hlp_' . $key . 'Cap'), 'attr') ?>" loading="lazy" decoding="async" class="block h-auto w-full">
                </a>
                <figcaption class="mt-2 text-xs text-muted"><?= lang('Lgc.hlp_' . $key . 'Cap') ?></figcaption>
            </figure>
        </div>
    </section>
<?php endforeach; ?>

<section id="vocabulario" class="card mb-4 scroll-mt-4">
    <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lgc.hlpTermsTitle') ?></h5></div>
    <div class="card-body">
        <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach (HelpGuide::TERMS as $t): ?>
                <div>
                    <dt class="text-sm font-semibold"><?= lang('Lgc.hlpT_' . $t) ?></dt>
                    <dd class="text-sm text-muted"><?= lang('Lgc.hlpT_' . $t . 'D') ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>
</section>

<section id="permisos" class="card mb-4 scroll-mt-4">
    <div class="card-header"><i class="icon icon-shield-check"></i><h5 class="font-semibold"><?= lang('Lgc.hlpPermsTitle') ?></h5></div>
    <div class="card-body">
        <p class="mb-4 max-w-3xl text-sm text-muted"><?= lang('Lgc.hlpPermsIntro') ?></p>
        <div class="table-card overflow-x-auto">
            <table class="table">
                <thead><tr><th><?= lang('Lgc.hlpPermCol') ?></th><th><?= lang('Lgc.hlpPermWhat') ?></th><th class="text-right"><?= lang('Lgc.hlpPermYou') ?></th></tr></thead>
                <tbody>
                    <?php foreach (HelpGuide::PERMS as $p): ?>
                        <?php $has = ! empty($perms['lgc.' . $p]); ?>
                        <tr>
                            <td class="whitespace-nowrap"><code class="text-xs">lgc.<?= esc($p) ?></code></td>
                            <td><?= lang('Lgc.hlpPerm_' . $p) ?></td>
                            <td class="text-right"><span class="badge <?= $has ? 'badge-success' : 'badge-light' ?>"><?= lang($has ? 'Lgc.hlpPermYes' : 'Lgc.hlpPermNo') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section id="preguntas" class="card scroll-mt-4">
    <div class="card-header"><i class="icon icon-life-preserver"></i><h5 class="font-semibold"><?= lang('Lgc.hlpFaqTitle') ?></h5></div>
    <div class="card-body">
        <dl class="divide-y divide-line">
            <?php for ($i = 1; $i <= HelpGuide::FAQ; $i++): ?>
                <div class="py-3 first:pt-0 last:pb-0">
                    <dt class="text-sm font-semibold"><?= lang('Lgc.hlpFaqQ_' . $i) ?></dt>
                    <dd class="mt-1 text-sm text-muted"><?= lang('Lgc.hlpFaqA_' . $i) ?></dd>
                </div>
            <?php endfor; ?>
        </dl>
    </div>
</section>
<?= $this->endSection() ?>
