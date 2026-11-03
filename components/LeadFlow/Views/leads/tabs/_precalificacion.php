<?php

use Components\LeadFlow\Libraries\FormEngine;
use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\FormModel;

$id = (int) $lead['id'];
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <?php if (! $submissions): ?>
            <div class="card"><div class="card-body"><p class="text-sm text-muted"><?= lang('Lfl.noSubmissions') ?></p></div></div>
        <?php endif; ?>
        <?php foreach ($submissions as $s): ?>
            <?php
            $form    = $formsById[(int) $s['form_id']] ?? null;
            $fields  = [];
            foreach (FormModel::fields($form) as $f) {
                $fields[$f['id']] = $f;
            }
            // Las etiquetas congeladas en la respuesta mandan: renombrar una pregunta
            // después no reescribe lo que el prospecto contestó.
            $labels  = json_decode((string) $s['labels'], true) ?: [];
            $answers = json_decode((string) $s['answers'], true) ?: [];
            ?>
            <div class="card">
                <div class="card-header justify-between">
                    <div class="flex items-center gap-2">
                        <i class="icon icon-clipboard"></i>
                        <h5 class="font-semibold"><?= esc($form['name'] ?? lang('Lfl.form')) ?></h5>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-muted">
                        <span class="badge <?= $s['is_complete'] ? 'badge-success' : 'badge-light' ?>"><?= lang($s['is_complete'] ? 'Lfl.formComplete' : 'Lfl.partial') ?></span>
                        <span><?= esc(lang('Lfl.chan_' . ($s['channel'] === 'public' ? 'public' : 'internal'))) ?> · <?= esc(Meta::date($s['created_at'])) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (! $answers): ?>
                        <p class="text-sm text-muted"><?= lang('Lfl.noAnswers') ?></p>
                    <?php else: ?>
                        <dl class="space-y-2 text-sm">
                            <?php foreach ($answers as $fid => $val): ?>
                                <div>
                                    <dt class="text-muted"><?= esc($labels[$fid] ?? $fid) ?></dt>
                                    <dd class="whitespace-pre-line font-medium"><?= esc(FormEngine::display($fields[$fid] ?? null, $val)) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><i class="icon icon-pencil-square"></i><h5 class="font-semibold"><?= lang('Lfl.fillForm') ?></h5></div>
            <div class="card-body">
                <p class="mb-3 text-xs text-muted"><?= lang('Lfl.fillFormHint') ?></p>
                <?php if (! $forms): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.noForms') ?></p>
                <?php elseif (! $canEdit): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.errReadOnly') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($forms as $f): ?>
                            <li class="flex items-center justify-between gap-2 py-2">
                                <span class="text-sm"><?= esc($f['name']) ?></span>
                                <a href="<?= base_url('leadflow/leads/precalificar/' . $id . '/' . (int) $f['id']) ?>" class="btn btn-secondary btn-sm">
                                    <i class="icon icon-pencil"></i> <?= lang('Lfl.fill') ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
