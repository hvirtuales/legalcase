<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;

?>
<?php if ($days === null): ?>
    <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
<?php elseif ($access->scope === 'none'): ?>
    <?= view('Components\LeadFlow\Views\partials\_noaccess') ?>
<?php else: ?>
    <?php $volver = 'leadflow/consultas?semana=' . $monday; ?>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h4 class="text-xl font-semibold"><?= lang('Lfl.navAppts') ?></h4>
            <p class="text-sm text-muted"><?= esc(lang('Lfl.weekOf', [Meta::date($monday, false)])) ?></p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <a href="<?= base_url('leadflow/consultas?semana=' . $prev) ?>" class="btn btn-secondary btn-sm" title="<?= esc(lang('Lfl.prevWeek'), 'attr') ?>"><i class="icon icon-chevron-left"></i></a>
            <a href="<?= base_url('leadflow/consultas') ?>" class="btn btn-secondary btn-sm"><?= lang('Lfl.thisWeek') ?></a>
            <a href="<?= base_url('leadflow/consultas?semana=' . $next) ?>" class="btn btn-secondary btn-sm" title="<?= esc(lang('Lfl.nextWeek'), 'attr') ?>"><i class="icon icon-chevron-right"></i></a>
            <form method="get" action="<?= base_url('leadflow/consultas') ?>" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="semana" value="<?= esc($monday, 'attr') ?>">
                <select name="abogado" class="form-select" aria-label="<?= esc(lang('Lfl.lawyer'), 'attr') ?>" onchange="this.form.submit()">
                    <option value=""><?= lang('Lfl.allLawyers') ?></option>
                    <?php foreach ($team as $uid => $uname): ?>
                        <option value="<?= (int) $uid ?>" <?= (int) $lawyer === (int) $uid ? 'selected' : '' ?>><?= esc($uname) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="estado" class="form-select" aria-label="<?= esc(lang('Lfl.status'), 'attr') ?>" onchange="this.form.submit()">
                    <option value=""><?= lang('Lfl.allStatuses') ?></option>
                    <?php foreach (Meta::options(Meta::APPT_STATUSES) as $val => $lbl): ?>
                        <option value="<?= esc($val, 'attr') ?>" <?= $status === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <p class="mb-4 text-xs text-muted"><?= lang('Lfl.apptsHint') ?></p>

    <div class="space-y-4">
        <?php foreach ($days as $date => $list): ?>
            <?php $isToday = $date === date('Y-m-d'); ?>
            <section>
                <h5 class="mb-2 flex items-center gap-2 text-sm font-semibold <?= $isToday ? 'text-primary' : '' ?>">
                    <i class="icon icon-clock"></i>
                    <?= esc(lang('Lfl.day' . date('N', strtotime($date)))) ?> <?= esc(Meta::date($date, false)) ?>
                    <?php if ($isToday): ?><span class="badge badge-primary"><?= lang('Lfl.today') ?></span><?php endif; ?>
                    <span class="text-xs font-normal text-muted">(<?= count($list) ?>)</span>
                </h5>
                <?php if (! $list): ?>
                    <p class="pl-6 text-xs text-muted"><?= lang('Lfl.noApptsDay') ?></p>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        <?php foreach ($list as $ap): ?>
                            <?= view('Components\LeadFlow\Views\appointments\_card', ['ap' => $ap, 'team' => $team, 'canEdit' => $canEdit, 'canDelete' => $canDelete, 'volver' => $volver, 'showLead' => true]) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
