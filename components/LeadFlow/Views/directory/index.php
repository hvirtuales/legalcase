<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

/*
 * Los perfiles de la firma en el directorio público.
 *
 * La pantalla dice **en qué estado está cada uno y qué falta para publicarlo**, porque
 * un perfil en borrador no aparece en ningún sitio y eso no se ve desde fuera. El
 * enlace público se pinta solo cuando existe de verdad: un enlace que devuelve 404 se
 * lee como un fallo del sistema.
 *
 * ── Dos ejes, dos columnas ──────────────────────────────────────────────────
 *
 * **Publicación** la decide la firma: está o no está en el sitio. **Verificación** la
 * decide la plataforma, y aquí es de SOLO LECTURA: `Profiles::verify()` cruza empresas
 * a propósito y `lfl.verify` es `permissions_super_only`, porque una firma que pudiera
 * verificarse a sí misma convertiría la insignia en algo sin valor.
 *
 * Lo que es de la PERSONA y no del perfil —su función comercial y su usuario de
 * Docketwise— se edita en el formulario del perfil, no aquí: son dos datos por fila que
 * convertían esta lista en una rejilla de controles.
 *
 * @var array     $profiles
 * @var bool      $directoryOn
 * @var bool      $featured
 * @var int|null  $profilesLeft
 * @var array|null $firmProfile
 */
$badges = ['draft' => 'badge-secondary', 'published' => 'badge-success', 'suspended' => 'badge-warning'];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-people"></i><h5 class="font-semibold"><?= lang('Lfl.navTeam') ?></h5></div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= base_url('/') ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                <i class="icon icon-box-arrow-up-right"></i> <?= lang('Lfl.dirSeeSite') ?>
            </a>
            <?php // El perfil de la firma no es una persona: tiene su propio acceso. ?>
            <?php if ($firmProfile !== null): ?>
                <a href="<?= base_url('leadflow/directorio/editar/' . (int) $firmProfile['id']) ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-buildings"></i> <?= lang('Lfl.dirFirmPage') ?>
                </a>
            <?php endif; ?>
            <a href="<?= base_url('leadflow/directorio/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-person-plus"></i> <?= lang('Lfl.dirNewProfile') ?>
            </a>
        </div>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.dirManageHint') ?></p>

        <?php if ($profiles): ?>
            <?php // Las tres cifras del directorio, antes de la lista: es lo que se mira
                  // para saber si esto está sirviendo de algo. ?>
            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <?php foreach (['dirStatPublished' => $totales['published'], 'dirStatViews' => $totales['views'], 'dirStatLeads' => $totales['leads']] as $clave => $valor): ?>
                    <div class="rounded border border-line p-4">
                        <p class="text-sm text-muted"><?= lang('Lfl.' . $clave) ?></p>
                        <p class="text-2xl font-semibold"><?= (int) $valor ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (! $directoryOn): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Lfl.planNoDirectory') ?></span></div>
        <?php endif; ?>
        <?php if ($profilesLeft !== null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= esc(lang('Lfl.dirProfilesLeft', [(int) $profilesLeft])) ?></span></div>
        <?php endif; ?>
        <?php if ($featured): ?>
            <div class="alert alert-success"><i class="icon icon-star"></i><span><?= lang('Lfl.dirFeaturedOn') ?></span></div>
        <?php endif; ?>

        <?php if (! $profiles): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.dirNoProfiles') ?></p>
                <p class="mb-4 text-sm text-muted"><?= lang('Lfl.dirNoProfilesHint') ?></p>
                <a href="<?= base_url('leadflow/directorio/nuevo') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-person-plus"></i> <?= lang('Lfl.dirNewProfile') ?>
                </a>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.dirProfile') ?></th>
                            <th><?= lang('Lfl.commercialRole') ?></th>
                            <th><?= lang('Lfl.status') ?></th>
                            <th><?= lang('Lfl.dirVerified') ?></th>
                            <th class="text-right"><?= lang('Lfl.dirLeadsFrom') ?></th>
                            <th class="text-right"><?= lang('Lfl.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profiles as $p): ?>
                            <?php
                            $pid = (int) $p['id'];
                            // La función comercial se EDITA en el formulario; aquí solo se lee.
                            $rol = $p['user_id'] ? ($members[(int) $p['user_id']]['role'] ?? null) : null;
                            ?>
                            <tr>
                                <td>
                                    <a class="font-semibold text-primary hover:underline" href="<?= base_url('leadflow/directorio/editar/' . $pid) ?>">
                                        <?= esc($p['display_name']) ?>
                                    </a>
                                    <div class="text-xs text-muted">
                                        <?= lang($p['kind'] === 'firm' ? 'Lfl.dirKindFirm' : 'Lfl.dirKindLawyer') ?>
                                        <?php if ($p['status'] === 'published'): ?>
                                            · <a href="<?= esc($publicBase . rawurlencode((string) $p['slug']), 'attr') ?>" target="_blank" rel="noopener">/<?= esc($p['slug']) ?></a>
                                            · <a href="<?= esc($publicBase . rawurlencode((string) $p['slug']) . '/tarjeta', 'attr') ?>" target="_blank" rel="noopener"><?= lang('Lfl.dcCardLink') ?></a>
                                        <?php else: ?>
                                            · <span class="font-mono">/<?= esc($p['slug']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-sm">
                                    <?= $rol ? esc(lang('Lfl.' . \Components\LeadFlow\Libraries\Meta::MEMBER_ROLES[$rol])) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badges[$p['status']] ?? 'badge-light' ?>"><?= lang('Lfl.dirStatus_' . $p['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($p['status'] !== 'published'): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif (! empty($p['verified_at'])): ?>
                                        <span class="badge badge-primary"><?= lang('Lfl.dirVerified') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><?= lang('Lfl.dirUnverified') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right"><?= (int) $p['leads_count'] ?>
                                    <div class="text-xs text-muted"><?= esc(lang('Lfl.dirViews', [(int) $p['views_count']])) ?></div>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="<?= base_url('leadflow/directorio/editar/' . $pid) ?>" class="btn btn-secondary btn-sm"
                                            title="<?= esc(lang('Lfl.edit'), 'attr') ?>"><i class="icon icon-pencil"></i></a>
                                        <form action="<?= base_url('leadflow/directorio/publicar/' . $pid) ?>" method="post">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn <?= $p['status'] === 'published' ? 'btn-ghost' : 'btn-primary' ?> btn-sm">
                                                <i class="icon <?= $p['status'] === 'published' ? 'icon-eye-slash' : 'icon-check-circle' ?>"></i>
                                                <?= lang($p['status'] === 'published' ? 'Lfl.dirUnpublish' : 'Lfl.dirPublish') ?>
                                            </button>
                                        </form>
                                        <?php if ($canDelete): ?>
                                            <?= view('partials/delete_button', [
                                                'action'  => base_url('leadflow/directorio/eliminar/' . $pid),
                                                'confirm' => lang('Lfl.dirDeleteConfirm'),
                                                'label'   => lang('Lfl.delete'),
                                            ]) ?>
                                        <?php endif; ?>
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
