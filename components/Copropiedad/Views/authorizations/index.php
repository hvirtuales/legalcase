<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-shield-check"></i>
            <h5 class="font-semibold"><?= lang('Cop.auths') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('copropiedad/autorizaciones/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Cop.authsNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Cop.authsIntro') ?></p>

            <?php // El semáforo, arriba: es lo primero que se mira. ?>
            <div class="mb-4 flex flex-wrap gap-4">
                <?php foreach (Meta::AUTH_STATUSES as $valor => $claveIdioma): ?>
                    <div class="flex items-center gap-2">
                        <span class="badge <?= Meta::color(Meta::AUTH_STATUS_COLORS, $valor) ?>"><?= esc(lang('Cop.' . $claveIdioma)) ?></span>
                        <span class="font-semibold"><?= (int) ($counts[$valor] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="get" action="<?= base_url('copropiedad/autorizaciones') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Cop.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Cop.authsSearch'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-tipo" class="form-label text-xs"><?= lang('Cop.authKind') ?></label>
                    <select id="f-tipo" name="tipo" class="form-select">
                        <option value=""><?= lang('Cop.all') ?></option>
                        <?php foreach (Meta::options(Meta::AUTH_KINDS) as $v => $l): ?>
                            <option value="<?= esc($v, 'attr') ?>" <?= $kind === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="f-estado" class="form-label text-xs"><?= lang('Cop.authStatus') ?></label>
                    <select id="f-estado" name="estado" class="form-select">
                        <option value=""><?= lang('Cop.all') ?></option>
                        <?php foreach (Meta::options(Meta::AUTH_STATUSES) as $v => $l): ?>
                            <option value="<?= esc($v, 'attr') ?>" <?= $status === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Cop.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($kind !== '' || $status !== '' || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('copropiedad/autorizaciones') ?>" class="btn btn-ghost" title="<?= esc(lang('Cop.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => base_url('copropiedad/autorizaciones'),
                'extraParams' => ['tipo' => $kind, 'estado' => $status],
                'emptyText'   => lang('Cop.noAuths'),
                'columns'     => [
                    // El autorizado puede venir del censo o de fuera: se resuelve aquí
                    // para que la columna nunca salga vacía.
                    ['key' => 'authorized_name', 'label' => lang('Cop.authWho'), 'sortable' => false,
                        'render' => static function ($x) {
                            $nombre = $x['authorized_name'] ?: ($x['first_name'] ? PersonModel::fullName($x) : '');

                            return '<strong>' . esc($nombre ?: '—') . '</strong>'
                                . ($x['authorized_doc'] ? '<div class="text-xs text-muted">' . esc($x['authorized_doc']) . '</div>' : '');
                        }],
                    ['key' => 'kind', 'label' => lang('Cop.authKind'), 'sortable' => true, 'sortKey' => 'cmp_cop_authorizations.kind',
                        'render' => static fn ($x) => esc(Meta::label(Meta::AUTH_KINDS, $x['kind']))],
                    ['key' => 'unit_code', 'label' => lang('Cop.units'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.code',
                        'render' => static fn ($x) => $x['unit_code']
                            ? '<a class="text-primary hover:underline" href="' . base_url('copropiedad/inmuebles/ficha/' . (int) $x['unit_id']) . '">'
                                . esc(($x['block_name'] ? $x['block_name'] . ' · ' : '') . $x['unit_code']) . '</a>'
                            : '—'],
                    ['key' => 'grantor', 'label' => lang('Cop.authGrantedBy'), 'sortable' => false,
                        'render' => static fn ($x) => $x['grantor_first']
                            ? esc(trim($x['grantor_first'] . ' ' . $x['grantor_last'])) : '—'],
                    ['key' => 'end_date', 'label' => lang('Cop.relEnd'), 'sortable' => true, 'sortKey' => 'cmp_cop_authorizations.end_date',
                        'render' => static fn ($x) => esc($x['end_date'] ?: '—')],
                    ['key' => 'status', 'label' => lang('Cop.authStatus'), 'sortable' => true, 'sortKey' => 'cmp_cop_authorizations.status',
                        'render' => static fn ($x) => '<span class="badge ' . Meta::color(Meta::AUTH_STATUS_COLORS, $x['status']) . '">'
                            . esc(Meta::label(Meta::AUTH_STATUSES, $x['status'])) . '</span>'],
                ],
                'actions' => static function ($x) {
                    $id  = (int) $x['id'];
                    $out = '<div class="flex justify-end gap-1">';

                    // Revocar solo tiene sentido en una vigente: una vencida ya no
                    // autoriza nada, y una revocada ya lo está.
                    if ($x['status'] === 'vigente') {
                        $out .= '<form action="' . base_url('copropiedad/autorizaciones/revocar/' . $id) . '" method="post"'
                            . ' onsubmit="return confirm(\'' . esc(lang('Cop.authRevokeConfirm'), 'js') . '\')">'
                            . csrf_field()
                            . '<button type="submit" class="btn btn-secondary btn-sm" title="' . esc(lang('Cop.authRevoke'), 'attr') . '">'
                            . '<i class="icon icon-x-circle"></i></button></form>';
                    }

                    return $out
                        . '<a href="' . base_url('copropiedad/autorizaciones/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action' => base_url('copropiedad/autorizaciones/eliminar/' . $id),
                            'label'  => lang('Cop.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
