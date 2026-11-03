<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;

$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-diagram-3"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.blocks') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.blocksIntro') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group md:col-span-2">
                        <label for="name" class="form-label"><?= lang('Cop.blockName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="80"
                            placeholder="Torre 1" value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="code" class="form-label"><?= lang('Cop.blockCode') ?></label>
                        <input type="text" id="code" name="code" class="form-input" maxlength="20"
                            placeholder="T1" value="<?= esc($v('code'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="kind" class="form-label"><?= lang('Cop.blockKind') ?></label>
                        <select id="kind" name="kind" class="form-select">
                            <?php foreach (Meta::options(Meta::BLOCK_KINDS) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('kind', 'torre') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="floors" class="form-label"><?= lang('Cop.blockFloors') ?></label>
                        <input type="number" min="0" id="floors" name="floors" class="form-input" value="<?= esc($v('floors'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="sort_order" class="form-label"><?= lang('Cop.blockOrder') ?></label>
                        <input type="number" id="sort_order" name="sort_order" class="form-input" value="<?= esc($v('sort_order', 0), 'attr') ?>">
                        <small class="form-hint"><?= lang('Cop.blockOrderHint') ?></small>
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-checkbox"
                        <?= $row === [] || $v('is_active', 1) ? 'checked' : '' ?>>
                    <label for="is_active"><?= lang('Cop.active') ?></label>
                </div>

                <div class="form-group mb-2">
                    <label for="notes" class="form-label"><?= lang('Cop.notes') ?></label>
                    <textarea id="notes" name="notes" class="form-textarea" rows="2"><?= esc($v('notes')) ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Cop.save') ?></button>
                <a href="<?= base_url('copropiedad/torres') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
