<?php
/**
 * Resumen: a la izquierda lo INTERNO (hechos, objetivos, estrategia); a la derecha lo
 * que hoy ve el cliente (estado público y última actualización) y lo próximo.
 *
 * @var array $matter @var ?array $lastUpdate @var array $nextDeadlines @var array $proceedings @var ?array $plan
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\MatterModel;

$id = (int) $matter['id'];
?>
<div class="grid gap-4 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header flex-wrap justify-between gap-2">
                <div class="flex items-center gap-2"><i class="icon icon-lock"></i><h5 class="font-semibold"><?= lang('Lgc.internalView') ?></h5></div>
                <span class="badge badge-danger"><?= lang('Lgc.neverPublished') ?></span>
            </div>
            <div class="card-body space-y-4 text-sm">
                <?php foreach (['description' => 'fFacts', 'objectives' => 'fObjectives', 'strategy' => 'fStrategy'] as $f => $label): ?>
                    <div>
                        <div class="text-xs font-semibold uppercase text-muted"><?= lang('Lgc.' . $label) ?></div>
                        <p class="mt-1 whitespace-pre-line"><?= $matter[$f] !== null && trim((string) $matter[$f]) !== '' ? esc($matter[$f]) : '<span class="text-muted">—</span>' ?></p>
                    </div>
                <?php endforeach; ?>
                <dl class="grid gap-3 sm:grid-cols-3">
                    <div><dt class="text-xs text-muted"><?= lang('Lgc.fSpecialty') ?></dt><dd><?= $matter['specialty'] ? lang('Lgc.spec_' . $matter['specialty']) : '—' ?></dd></div>
                    <div><dt class="text-xs text-muted"><?= lang('Lgc.fJurisdiction') ?></dt><dd><?= esc(trim($matter['jurisdiction'] . ' ' . $matter['country']) ?: '—') ?></dd></div>
                    <div><dt class="text-xs text-muted"><?= lang('Lgc.fRisk') ?></dt><dd><?= lang('Lgc.risk_' . $matter['risk_level']) ?></dd></div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header flex-wrap justify-between gap-2">
                <div class="flex items-center gap-2"><i class="icon icon-landmark"></i><h5 class="font-semibold"><?= lang('Lgc.proceedings') ?></h5></div>
                <a href="<?= base_url('legalcase/asuntos/ficha/' . $id . '?tab=procesos') ?>" class="btn btn-ghost btn-sm"><?= lang('Lgc.seeAll') ?></a>
            </div>
            <div class="card-body">
                <?php if (!$proceedings): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noProceedingsHint') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line text-sm">
                        <?php foreach ($proceedings as $p): ?>
                            <li class="py-2">
                                <span class="font-mono font-semibold"><?= esc($p['filing_number'] ?: lang('Lgc.noFiling')) ?></span>
                                · <?= esc($p['authority'] ?: '—') ?>
                                <span class="badge badge-light"><?= lang('Lgc.proc_' . $p['status']) ?></span>
                                <?php if ($p['stage']): ?><div class="text-xs text-muted"><?= esc($p['stage']) ?></div><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="min-w-0 space-y-4">
        <div class="card">
            <div class="card-header"><i class="icon icon-eye"></i><h5 class="font-semibold"><?= lang('Lgc.clientView') ?></h5></div>
            <div class="card-body space-y-3 text-sm">
                <div><span class="text-xs text-muted"><?= lang('Lgc.fPublicTitle') ?></span><div class="font-semibold"><?= esc(MatterModel::clientTitle($matter)) ?></div></div>
                <div>
                    <span class="text-xs text-muted"><?= lang('Lgc.fPublicStage') ?></span>
                    <div><?= $matter['stage_hidden'] ? '<span class="text-muted">' . lang('Lgc.stageHiddenShort') . '</span>' : esc($matter['public_stage'] ?: '—') ?></div>
                </div>
                <div>
                    <span class="text-xs text-muted"><?= lang('Lgc.lastPublished') ?></span>
                    <?php if ($lastUpdate): ?>
                        <div><a class="text-primary" href="<?= base_url('legalcase/actualizaciones/ver/' . (int) $lastUpdate['id']) ?>"><?= esc($lastUpdate['title']) ?></a></div>
                        <div class="text-xs text-muted"><?= esc(Meta::date($lastUpdate['published_at'])) ?></div>
                    <?php else: ?>
                        <div class="text-muted"><?= lang('Lgc.neverUpdated') ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-xs text-muted"><?= lang('Lgc.planTitle') ?></span>
                    <div><?= $plan ? esc(lang('Lgc.mode_' . $plan['mode'])) . ((int) $plan['is_active'] ? '' : ' · ' . lang('Lgc.inactive')) : '—' ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header flex-wrap justify-between gap-2">
                <div class="flex items-center gap-2"><i class="icon icon-clock"></i><h5 class="font-semibold"><?= lang('Lgc.nextDeadlines') ?></h5></div>
                <a href="<?= base_url('legalcase/asuntos/ficha/' . $id . '?tab=plazos') ?>" class="btn btn-ghost btn-sm"><?= lang('Lgc.seeAll') ?></a>
            </div>
            <div class="card-body">
                <?php if (!$nextDeadlines): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noDeadlines') ?></p>
                <?php else: ?>
                    <ul class="space-y-2 text-sm">
                        <?php foreach ($nextDeadlines as $d): ?>
                            <li>
                                <div class="font-semibold"><?= esc($d['title']) ?> <?= (int) $d['is_critical'] ? '<span class="badge badge-danger">' . lang('Lgc.critical') . '</span>' : '' ?></div>
                                <div class="text-xs <?= Meta::isPast($d['due_at']) ? 'text-danger' : 'text-muted' ?>"><?= esc(Meta::date($d['due_at'])) ?> · <?= Meta::badge('deadline', $d['status']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
