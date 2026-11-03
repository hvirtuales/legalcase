<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/*
 * Verificación de perfiles, para toda la plataforma (solo Super Admin).
 *
 * Es una **cola de trabajo**: los que están sin verificar salen primero, con lo que hay
 * que comprobar a la vista —número de licencia, estado y el enlace oficial— para no tener
 * que entrar a cada ficha. Verificar es afirmar algo sobre un tercero, así que se registra
 * quién y cuándo, y se puede retirar.
 *
 * @var array $profiles perfiles publicados de todas las firmas, con su empresa
 */
?>
<?php
/*
 * La portada del directorio: una sola para toda la plataforma, porque hay una sola
 * página de inicio. Va delante de la cola de verificación porque es configuración, y
 * la cola es trabajo del día a día.
 */
$cover = (new \Components\LeadFlow\Libraries\Directory\CoverService())->url();
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-shield-check"></i><h5 class="font-semibold"><?= lang('Lfl.modTitle') ?></h5></div>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_moderation_tabs',
            ['active' => 'perfiles', 'isSuper' => $isSuper, 'pending' => $pending]) ?>
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.dirVerifyHint') ?></p>

        <?php if (! $profiles): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.dirVerifyEmpty') ?></p>
                <p class="text-sm text-muted"><?= lang('Lfl.dirVerifyEmptyHint') ?></p>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.dirProfile') ?></th>
                            <th><?= lang('Lfl.dirKindFirm') ?></th>
                            <th><?= lang('Lfl.dirLicense') ?></th>
                            <th><?= lang('Lfl.repTrayTitle') ?></th>
                            <th><?= lang('Lfl.dirVerified') ?></th>
                            <th class="text-right"><?= lang('Lfl.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profiles as $p): ?>
                            <?php $pid = (int) $p['id']; ?>
                            <tr>
                                <td>
                                    <a class="font-semibold text-primary hover:underline" target="_blank" rel="noopener"
                                        href="<?= base_url('abogado/' . rawurlencode((string) $p['slug'])) ?>">
                                        <?= esc($p['display_name']) ?>
                                    </a>
                                    <div class="text-xs text-muted"><?= esc($p['headline'] ?? '') ?></div>
                                </td>
                                <td class="text-sm"><?= esc($p['company_name']) ?></td>
                                <td class="text-sm">
                                    <?php if (! empty($p['bar_number'])): ?>
                                        <span class="font-mono"><?= esc($p['bar_number']) ?></span>
                                        <?= $p['bar_state'] ? ' · ' . esc($p['bar_state']) : '' ?>
                                        <?php if (! empty($p['bar_url'])): ?>
                                            <div class="text-xs">
                                                <a href="<?= esc($p['bar_url'], 'attr') ?>" target="_blank" rel="noopener nofollow">
                                                    <?= lang('Lfl.dirBarLink') ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= lang('Lfl.dirNoLicense') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php /* Solo el RECUENTO: el texto de la denuncia es de la otra
                                             pestana, que solo ve el Super Admin. */ ?>
                                    <?php $d = $complaints[(int) $p['id']] ?? 0; ?>
                                    <?php if ($d > 0): ?>
                                        <span class="badge badge-danger"><?= (int) $d ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (! empty($p['verified_at'])): ?>
                                        <span class="badge badge-success"><?= lang('Lfl.dirVerified') ?></span>
                                        <div class="text-xs text-muted"><?= esc(date('d/m/Y', strtotime((string) $p['verified_at']))) ?></div>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><?= lang('Lfl.dirUnverified') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="<?= base_url('leadflow/moderacion/verificar/' . $pid) ?>" method="post" class="flex justify-end">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn <?= empty($p['verified_at']) ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                                            <i class="icon <?= empty($p['verified_at']) ? 'icon-shield-check' : 'icon-x-circle' ?>"></i>
                                            <?= lang(empty($p['verified_at']) ? 'Lfl.dirVerifyDo' : 'Lfl.dirVerifyUndo') ?>
                                        </button>
                                    </form>
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
