<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Vistas del directorio del grupo.
 *
 * @var array      $boards
 * @var array      $counts   [board_id => cuántas empresas]
 * @var array|null $tenant
 * @var array      $sectors  sectores que de verdad usan las empresas del grupo
 */
$publicBase = base_url('directorio-publico/' . ($tenant['slug'] ?? ''));
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-signpost"></i>
            <h5 class="font-semibold"><?= lang('Dir.boards') ?></h5>
        </div>
        <a href="<?= base_url('directorio/nueva') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i> <?= lang('Dir.newBoard') ?>
        </a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Dir.boardsHint') ?></p>

        <?php if (!$sectors): ?>
            <div class="alert alert-warning">
                <i class="icon icon-exclamation-triangle"></i>
                <?= lang('Dir.noSectorsYet') ?>
            </div>
        <?php endif; ?>

        <?php if (!$boards): ?>
            <p class="text-muted"><?= lang('Dir.boardsEmpty') ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Dir.fBoardTitle') ?></th>
                            <th><?= lang('Dir.fMode') ?></th>
                            <th><?= lang('Dir.secLayout') ?></th>
                            <th class="text-center"><?= lang('Dir.companiesShown') ?></th>
                            <th><?= lang('Dir.fPublished') ?></th>
                            <th class="text-end"><?= lang('Dir.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($boards as $b): ?>
                            <?php
                            $cuenta = $counts[(int) $b['id']] ?? ['total' => 0, 'located' => 0];
                            $n      = $cuenta['total'];
                            $sinMapa = $n - $cuenta['located'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= esc($b['title']) ?></strong>
                                    <div class="text-xs text-muted"><code><?= esc($b['slug']) ?></code></div>
                                </td>
                                <td>
                                    <?php if (($b['mode'] ?? '') === 'manual'): ?>
                                        <span class="badge badge-light"><?= lang('Dir.modeManual') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-light"><?= lang('Dir.modeSector') ?></span>
                                        <?php
                                        $sec = null;
                                        foreach ($sectors as $s) {
                                            if ((int) $s['id'] === (int) ($b['sector_id'] ?? 0)) {
                                                $sec = $s;
                                            }
                                        }
                                        ?>
                                        <div class="text-xs text-muted">
                                            <?= $sec ? esc($sec['name']) : lang('Dir.allCompanies') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php // Qué enseña: evita abrir la vista para recordarlo. ?>
                                    <span class="badge badge-light"><?= lang('Dir.layout_' . ($b['layout'] ?? 'both')) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php // Una vista que no enseña a nadie es un fallo que conviene
                                          // ver ANTES de publicarla, no después. ?>
                                    <?php if ($n === 0): ?>
                                        <span class="badge badge-warning" title="<?= esc(lang('Dir.emptyBoardHint')) ?>">0</span>
                                    <?php else: ?>
                                        <span class="badge badge-light"><?= $n ?></span>
                                        <?php // Una empresa sin coordenadas sale en el listado y NO en el
                                              // mapa: se puede publicar una vista con el mapa vacío sin que
                                              // nada lo avise. Aquí se ve, y aquí se puede arreglar. ?>
                                        <?php if ($sinMapa > 0): ?>
                                            <div class="mt-1 text-xs text-warning" title="<?= esc(lang('Dir.noMapHint')) ?>">
                                                <i class="icon icon-geo-alt"></i>
                                                <?= lang('Dir.withoutLocation', [$sinMapa]) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($b['is_published'])): ?>
                                        <span class="badge badge-success"><?= lang('Dir.published') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= lang('Dir.draft') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <?php if (!empty($b['is_published']) && !empty($tenant['slug'])): ?>
                                            <a href="<?= $publicBase . '/' . esc($b['slug'], 'url') ?>" target="_blank" rel="noopener"
                                                class="btn btn-ghost btn-sm" title="<?= esc(lang('Dir.viewPublic'), 'attr') ?>">
                                                <i class="icon icon-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= base_url('directorio/editar/' . (int) $b['id']) ?>"
                                            class="btn btn-secondary btn-sm" title="<?= esc(lang('Dir.edit'), 'attr') ?>">
                                            <i class="icon icon-pencil"></i>
                                        </a>
                                        <?= view('partials/delete_button', [
                                            'action'  => base_url('directorio/eliminar/' . (int) $b['id']),
                                            'confirm' => lang('Dir.confirmDeleteBoard'),
                                        ]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
