<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Plantillas: las de arranque (globales) y las que la empresa ha guardado.
 * Se distinguen por `company_id`: solo las propias se pueden borrar.
 *
 * @var array|null $rows
 * @var array      $pages    páginas de la empresa, para «guardar como plantilla»
 * @var callable   $countBlocks
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-list-columns-reverse"></i>
            <h5 class="font-semibold"><?= lang('Pag.templates') ?></h5>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if (!empty($canSeed)): ?>
                <form action="<?= base_url('paginas/plantillas/sembrar') ?>" method="post" class="inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm" title="<?= esc(lang('Pag.seedHint'), 'attr') ?>">
                        <i class="icon icon-sparkles"></i> <?= lang('Pag.seedTemplates') ?>
                    </button>
                </form>
            <?php endif; ?>
            <a href="<?= base_url('paginas') ?>" class="btn btn-ghost btn-sm">
                <i class="icon icon-arrow-left"></i> <?= lang('Pag.back') ?>
            </a>
        </div>
    </div>

    <div class="card-body">
        <?php if ($rows === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Pag.noCompanyHint') ?></span></div>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Pag.templatesIntro') ?></p>

            <?php if (!$rows): ?>
                <p class="py-6 text-center text-muted"><?= lang('Pag.noTemplates') ?></p>
            <?php else: ?>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($rows as $t): ?>
                        <?php $isOwn = (int) ($t['company_id'] ?? 0) === (int) $companyId; ?>
                        <article class="flex flex-col rounded-lg border border-line bg-surface p-4">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <h6 class="font-semibold"><?= esc($t['name']) ?></h6>
                                <span class="badge <?= $isOwn ? 'badge-primary' : 'badge-light' ?>">
                                    <?= $isOwn ? lang('Pag.tplOwn') : lang('Pag.tplStarter') ?>
                                </span>
                            </div>

                            <?php if (!empty($t['description'])): ?>
                                <p class="mb-3 text-sm text-muted"><?= esc($t['description']) ?></p>
                            <?php endif; ?>

                            <p class="mb-3 text-xs text-muted">
                                <i class="icon icon-puzzle"></i> <?= esc(lang('Pag.blocksCount', [$countBlocks($t)])) ?>
                            </p>

                            <form action="<?= base_url('paginas/plantillas/usar/' . (int) $t['id']) ?>" method="post"
                                class="mt-auto grid gap-2">
                                <?= csrf_field() ?>
                                <input type="text" name="title" class="form-input"
                                    placeholder="<?= esc(lang('Pag.phNewPageTitle'), 'attr') ?>"
                                    value="<?= esc($t['name'], 'attr') ?>" maxlength="180">
                                <div class="flex flex-wrap gap-1">
                                    <button class="btn btn-primary btn-sm">
                                        <i class="icon icon-plus-circle"></i> <?= lang('Pag.useTemplate') ?>
                                    </button>
                                    <a href="<?= base_url('paginas/plantillas/' . (int) $t['id']) ?>" target="_blank"
                                        rel="noopener" class="btn btn-ghost btn-sm"
                                        title="<?= esc(lang('Pag.preview'), 'attr') ?>">
                                        <i class="icon icon-eye"></i>
                                    </a>
                                    <?php if ($isOwn): ?>
                                        <?= view('partials/delete_button', [
                                            'action'  => base_url('paginas/plantillas/eliminar/' . (int) $t['id']),
                                            'confirm' => lang('Pag.confirmDeleteTemplate'),
                                            'label'   => lang('Pag.delete'),
                                        ]) ?>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($pages)): ?>
    <div class="card mt-4">
        <div class="card-header">
            <div class="flex items-center gap-2">
                <i class="icon icon-save"></i>
                <h5 class="font-semibold"><?= lang('Pag.saveAsTemplate') ?></h5>
            </div>
        </div>
        <div class="card-body">
            <p class="mb-4 text-sm text-muted"><?= lang('Pag.saveAsTemplateHint') ?></p>

            <form method="post" action="" id="tplFromPage" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                <?= csrf_field() ?>
                <div class="form-group sm:col-span-1">
                    <label class="form-label" for="tplPage"><?= lang('Pag.fSourcePage') ?></label>
                    <select id="tplPage" class="form-select" onchange="
                        document.getElementById('tplFromPage').action =
                            '<?= base_url('paginas/plantillas/desde/') ?>' + this.value;">
                        <?php foreach ($pages as $i => $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= esc($p['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group sm:col-span-1">
                    <label class="form-label" for="tplName"><?= lang('Pag.fTemplateName') ?></label>
                    <input type="text" id="tplName" name="name" class="form-input" maxlength="120"
                        placeholder="<?= esc(lang('Pag.phTemplateName'), 'attr') ?>">
                </div>
                <div class="form-group sm:col-span-1">
                    <label class="form-label" for="tplDesc"><?= lang('Pag.fTemplateDesc') ?></label>
                    <input type="text" id="tplDesc" name="description" class="form-input" maxlength="255"
                        placeholder="<?= esc(lang('Pag.phTemplateDesc'), 'attr') ?>">
                </div>
                <div class="form-group sm:col-span-1">
                    <button class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Pag.save') ?></button>
                </div>
            </form>

            <script>
                // La acción del formulario depende de la página elegida; se fija
                // ya en la carga para que valga sin tocar el desplegable.
                (function () {
                    var sel = document.getElementById('tplPage');
                    var frm = document.getElementById('tplFromPage');
                    if (sel && frm) { frm.action = '<?= base_url('paginas/plantillas/desde/') ?>' + sel.value; }
                }());
            </script>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
