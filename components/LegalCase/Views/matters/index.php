<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Listado de asuntos visibles para el usuario (la visibilidad `team` ya la aplicó Access).
 *
 * @var array $dt @var array $filters @var array $users
 */
use Components\LegalCase\Libraries\Access;
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\MatterModel;

$base  = base_url('legalcase/asuntos');
$dirty = ($dt['q'] ?? '') !== '' || implode('', $filters) !== '';
?>
<div class="card">
    <div class="card-header flex-wrap justify-between gap-2">
        <div class="flex items-center gap-2">
            <i class="icon icon-briefcase"></i>
            <h5 class="font-semibold"><?= lang('Lgc.matters') ?></h5>
        </div>
        <?php if (Access::current()->can('lgc.create')): ?>
            <a href="<?= base_url('legalcase/asuntos/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Lgc.matterNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="get" action="<?= $base ?>" class="mb-4 flex flex-wrap items-end gap-2">
            <div class="form-group mb-0">
                <label for="f-q" class="form-label text-xs"><?= lang('Lgc.search') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="icon icon-search"></i></span>
                    <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                        placeholder="<?= esc(lang('Lgc.searchMatters'), 'attr') ?>">
                </div>
            </div>
            <div class="form-group mb-0">
                <label for="f-estado" class="form-label text-xs"><?= lang('Lgc.fStatus') ?></label>
                <select id="f-estado" name="estado" class="form-select">
                    <option value=""><?= lang('Lgc.all') ?></option>
                    <?php foreach (MatterModel::STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= $filters['estado'] === $s ? 'selected' : '' ?>><?= lang('Lgc.matter_' . $s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                <label class="btn <?= $filters['mios'] === '1' ? 'btn-primary' : 'btn-ghost' ?> cursor-pointer">
                    <input type="checkbox" name="mios" value="1" class="hidden" onchange="this.form.submit()" <?= $filters['mios'] === '1' ? 'checked' : '' ?>>
                    <i class="icon icon-person"></i> <?= lang('Lgc.onlyMine') ?>
                </label>
            </div>
            <div class="form-group mb-0">
                <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                <div class="flex gap-1">
                    <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Lgc.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                    <?php if ($dirty): ?>
                        <a href="<?= $base ?>" class="btn btn-ghost" title="<?= esc(lang('Lgc.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?= view('partials/datatable', [
            'dt'          => $dt,
            'showSearch'  => false,
            'baseUrl'     => $base,
            'extraParams' => $filters,
            'emptyText'   => lang('Lgc.noMatters'),
            'columns'     => [
                ['key' => 'number', 'label' => lang('Lgc.fNumber'), 'sortable' => true,
                    'render' => static fn ($r) => '<a class="font-mono font-semibold text-primary" href="' . base_url('legalcase/asuntos/ficha/' . (int) $r['id']) . '">' . esc($r['number']) . '</a>'
                        . ($r['visibility'] === 'team' ? ' <i class="icon icon-lock text-muted" title="' . esc(lang('Lgc.visibility_team'), 'attr') . '"></i>' : '')],
                ['key' => 'title', 'label' => lang('Lgc.fTitle'), 'sortable' => true,
                    'render' => static fn ($r) => '<div class="max-w-xs truncate font-semibold">' . esc($r['title']) . '</div>'
                        . '<div class="text-xs text-muted">' . esc($r['client_name'] ?: lang('Lgc.noClient')) . '</div>'],
                ['key' => 'lead_lawyer_id', 'label' => lang('Lgc.fLawyer'), 'sortable' => false,
                    'render' => static fn ($r) => esc($users[(int) $r['lead_lawyer_id']] ?? '—')],
                ['key' => 'next_update_due_at', 'label' => lang('Lgc.fNextUpdate'), 'sortable' => true,
                    'render' => static fn ($r) => $r['next_update_due_at']
                        ? '<span class="' . (Meta::isPast($r['next_update_due_at']) && $r['status'] === 'open' ? 'font-semibold text-danger' : 'text-muted') . '">' . esc(Meta::date($r['next_update_due_at'], false)) . '</span>'
                        : '<span class="text-muted">—</span>'],
                ['key' => 'status', 'label' => lang('Lgc.fStatus'), 'sortable' => true,
                    'render' => static fn ($r) => Meta::badge('matter', $r['status'])],
            ],
            'actions' => static fn ($r) => '<div class="flex justify-end"><a href="' . base_url('legalcase/asuntos/ficha/' . (int) $r['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Lgc.open'), 'attr') . '"><i class="icon icon-eye"></i></a></div>',
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
