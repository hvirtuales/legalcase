<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Importación masiva de productos.
 *
 * @var array|null $result     resumen de la última corrida
 * @var array      $categories [id => nombre] de la empresa, para copiar el id
 */
$cols = \Components\Servicios\Libraries\ProductImporter::COLUMNS;
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-upload"></i>
            <h5 class="font-semibold"><?= lang('Quo.import') ?></h5>
        </div>
        <a href="<?= base_url('servicios/productos') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-chevron-left"></i> <?= lang('Quo.back') ?>
        </a>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <!-- Resultado de la corrida -->
            <div class="alert <?= $result['errors'] ? 'alert-warning' : 'alert-success' ?>">
                <i class="icon <?= $result['errors'] ? 'icon-exclamation-circle' : 'icon-check-circle' ?>"></i>
                <span><?= lang('Quo.impDone', [$result['created'], $result['updated'], $result['skipped']]) ?></span>
            </div>

            <?php if ($result['errors']): ?>
                <div class="mb-4 max-h-60 overflow-y-auto rounded-md border border-line">
                    <ul class="divide-y divide-line text-sm">
                        <?php foreach ($result['errors'] as $e): ?>
                            <li class="px-3 py-2 text-danger"><?= esc($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- 1. Descargar la plantilla -->
            <div class="min-w-0 md:col-span-2">
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Quo.impStep1') ?></legend>
                    <p class="form-section-hint"><?= lang('Quo.impStep1Hint') ?></p>

                    <a href="<?= base_url('servicios/productos/plantilla') ?>" class="btn btn-secondary">
                        <i class="icon icon-download"></i> <?= lang('Quo.impTemplate') ?>
                    </a>

                    <div class="mt-3 overflow-x-auto">
                        <table class="table text-xs">
                            <thead>
                                <tr><?php foreach ($cols as $c): ?><th class="whitespace-nowrap"><?= esc($c) ?></th><?php endforeach; ?></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>SRV-010</td><td>Consultoría estratégica</td><td>Sesión de 4 horas</td>
                                    <td>—</td><td>1200000</td><td>1</td><td>%</td><td>0</td><td>19</td>
                                    <td>1100000</td><td>1000000</td><td></td><td></td><td>1</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs text-muted">
                        <li><?= lang('Quo.impNoteCode') ?></li>
                        <li><?= lang('Quo.impNoteCategory') ?></li>
                        <li><?= lang('Quo.impNoteTiers') ?></li>
                        <li><?= lang('Quo.impNoteNumbers') ?></li>
                    </ul>
                </fieldset>

                <!-- 2. Subir el archivo -->
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Quo.impStep2') ?></legend>
                    <p class="form-section-hint"><?= lang('Quo.impStep2Hint') ?></p>

                    <form action="<?= base_url('servicios/productos/importar') ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="form-group mb-0 flex-1">
                                <label for="file" class="form-label"><?= lang('Quo.impFile') ?></label>
                                <input type="file" id="file" name="file" class="form-input" accept=".csv,text/csv,text/plain" required>
                            </div>
                            <button type="submit" class="btn btn-primary mb-1">
                                <i class="icon icon-upload"></i> <?= lang('Quo.impRun') ?>
                            </button>
                        </div>
                    </form>
                </fieldset>
            </div>

            <!-- Referencia: los ids de categoría -->
            <div class="min-w-0">
                <fieldset class="form-section h-full">
                    <legend class="form-section-title"><?= lang('Quo.impCategories') ?></legend>
                    <p class="form-section-hint"><?= lang('Quo.impCategoriesHint') ?></p>

                    <?php if (!$categories): ?>
                        <p class="text-sm text-muted"><?= lang('Quo.noCategories') ?></p>
                        <a href="<?= base_url('servicios/categorias/nuevo') ?>" class="btn btn-secondary btn-sm mt-2">
                            <i class="icon icon-plus-circle"></i> <?= lang('Quo.categoriesNew') ?>
                        </a>
                    <?php else: ?>
                        <table class="table text-sm">
                            <thead><tr><th>id</th><th><?= lang('Quo.name') ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($categories as $id => $name): ?>
                                    <tr><td class="font-mono"><?= (int) $id ?></td><td><?= esc($name) ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </fieldset>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
