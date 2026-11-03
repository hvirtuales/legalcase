<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

/*
 * El historial completo del inmueble.
 *
 * No hay tabla de histórico: estas filas son las MISMAS relaciones de la ficha, las
 * vigentes y las cerradas. Por eso «quién vive hoy» y «quién vivía en 2019» no
 * pueden discrepar — salen del mismo sitio.
 */
$fecha = static fn (?string $d) => $d ? esc($d) : '—';
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-clock-history"></i>
            <h5 class="font-semibold"><?= esc(lang('Cop.relHistoryOf', [$unit['code']])) ?></h5>
        </div>
        <a href="<?= base_url('copropiedad/inmuebles/ficha/' . (int) $unit['id']) ?>" class="btn btn-ghost btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('Cop.back') ?>
        </a>
    </div>
    <div class="card-body">
        <?php if (! $history): ?>
            <p class="text-sm text-muted"><?= lang('Cop.relNoHistory') ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Cop.relPickPerson') ?></th>
                            <th><?= lang('Cop.relRole') ?></th>
                            <th><?= lang('Cop.relShare') ?></th>
                            <th><?= lang('Cop.relStart') ?></th>
                            <th><?= lang('Cop.relEnd') ?></th>
                            <th><?= lang('Cop.authStatus') ?></th>
                            <th class="text-right"><?= lang('Cop.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <?php $vigente = (int) $h['is_current'] === 1; ?>
                            <tr class="<?= $vigente ? '' : 'text-muted' ?>">
                                <td>
                                    <a class="font-medium text-primary hover:underline"
                                        href="<?= base_url('copropiedad/personas/ficha/' . (int) $h['person_id']) ?>">
                                        <?= esc(PersonModel::fullName($h)) ?>
                                    </a>
                                    <div class="text-xs text-muted"><?= esc($h['doc_type'] . ' ' . $h['doc_number']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= Meta::color(Meta::ROLE_COLORS, $h['role']) ?>">
                                        <?= esc(Meta::label(Meta::ROLES, $h['role'])) ?>
                                    </span>
                                </td>
                                <td><?= $h['share'] !== null ? esc(rtrim(rtrim(number_format((float) $h['share'], 4, ',', '.'), '0'), ',')) . ' %' : '—' ?></td>
                                <td><?= $fecha($h['start_date']) ?></td>
                                <td><?= $fecha($h['end_date']) ?></td>
                                <td>
                                    <span class="badge <?= $vigente ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= $vigente ? lang('Cop.relCurrent') : lang('Cop.relEnded') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <?php if ($vigente): ?>
                                            <form action="<?= base_url('copropiedad/relaciones/cerrar/' . (int) $h['id']) ?>" method="post"
                                                onsubmit="return confirm('<?= esc(lang('Cop.relCloseConfirm'), 'js') ?>')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Cop.relClose'), 'attr') ?>">
                                                    <i class="icon icon-box-arrow-right"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <?php // Reabrir, para el cierre hecho por error. ?>
                                            <form action="<?= base_url('copropiedad/relaciones/reabrir/' . (int) $h['id']) ?>" method="post">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Cop.relReopen'), 'attr') ?>">
                                                    <i class="icon icon-arrow-counterclockwise"></i>
                                                </button>
                                            </form>
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
