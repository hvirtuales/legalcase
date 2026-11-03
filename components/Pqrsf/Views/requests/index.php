<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * La bandeja.
 *
 * Cuatro números arriba (lo abierto, lo vencido, lo respondido este mes y el
 * total) y el listado con los filtros **en la misma fila del buscador**, como el
 * resto de la plataforma.
 *
 * @var array|null $dt @var array $metrics @var array $points @var array $agents
 * @var array      $filters @var array $types  [código => nombre]
 */
$statuses = \Components\Pqrsf\Models\RequestModel::STATUSES;
$base     = base_url('pqrsf');
$now      = date('Y-m-d H:i:s');

/** Filtro puesto = algo que limpiar. */
$dirty = ($dt['q'] ?? '') !== '' || implode('', $filters) !== '';
?>

<?php if ($dt === null): ?>
    <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Pqr.errNoCompany') ?></span></div>
<?php else: ?>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <?php
        $cards = [
            ['icon' => 'icon-chat', 'label' => lang('Pqr.mOpen'), 'value' => $metrics['open'] ?? 0, 'tone' => 'text-primary', 'href' => $base . '?estado=recibida'],
            ['icon' => 'icon-clock', 'label' => lang('Pqr.mLate'), 'value' => $metrics['late'] ?? 0, 'tone' => 'text-danger', 'href' => $base . '?vencidas=1'],
            ['icon' => 'icon-check-circle', 'label' => lang('Pqr.mMonth'), 'value' => $metrics['month'] ?? 0, 'tone' => 'text-success', 'href' => $base . '?estado=respondida'],
            ['icon' => 'icon-list', 'label' => lang('Pqr.mTotal'), 'value' => $metrics['total'] ?? 0, 'tone' => 'text-muted', 'href' => $base],
        ];
        foreach ($cards as $c): ?>
            <a href="<?= esc($c['href'], 'attr') ?>" class="card block transition hover:shadow-md">
                <div class="card-body py-4">
                    <div class="flex items-center gap-2 text-xs text-muted">
                        <i class="icon <?= $c['icon'] ?> <?= $c['tone'] ?>"></i> <?= esc($c['label']) ?>
                    </div>
                    <div class="mt-1 text-2xl font-bold <?= $c['tone'] ?>"><?= (int) $c['value'] ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header justify-between">
            <div class="flex items-center gap-2">
                <i class="icon icon-chat"></i>
                <h5 class="font-semibold"><?= lang('Pqr.inbox') ?></h5>
            </div>
            <div class="flex gap-2">
                <?php if (!empty($showDemo)): ?>
                    <form method="post" action="<?= base_url('pqrsf/demo') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-sm" title="<?= esc(lang('Pqr.demoHint'), 'attr') ?>">
                            <i class="icon icon-sparkles"></i> <?= lang('Pqr.demo') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= base_url('pqrsf/puntos') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-qr-code"></i> <?= lang('Pqr.points') ?>
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Buscador y filtros en UNA fila: el DataTable no pinta el suyo. -->
            <form method="get" action="<?= $base ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Pqr.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Pqr.searchHint'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-estado" class="form-label text-xs"><?= lang('Pqr.fStatus') ?></label>
                    <select id="f-estado" name="estado" class="form-select">
                        <option value=""><?= lang('Pqr.all') ?></option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= lang('Pqr.status_' . $s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="f-tipo" class="form-label text-xs"><?= lang('Pqr.fType') ?></label>
                    <select id="f-tipo" name="tipo" class="form-select">
                        <option value=""><?= lang('Pqr.all') ?></option>
                        <?php foreach ($types as $code => $name): ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $filters['type'] === $code ? 'selected' : '' ?>><?= esc($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($points): ?>
                    <div class="form-group mb-0">
                        <label for="f-punto" class="form-label text-xs"><?= lang('Pqr.point') ?></label>
                        <select id="f-punto" name="punto" class="form-select">
                            <option value=""><?= lang('Pqr.all') ?></option>
                            <?php foreach ($points as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (string) $filters['point'] === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="form-group mb-0">
                    <label for="f-resp" class="form-label text-xs"><?= lang('Pqr.assignee') ?></label>
                    <select id="f-resp" name="responsable" class="form-select">
                        <option value=""><?= lang('Pqr.all') ?></option>
                        <option value="0" <?= $filters['assignee'] === '0' ? 'selected' : '' ?>><?= lang('Pqr.unassigned') ?></option>
                        <?php foreach ($agents as $id => $name): ?>
                            <option value="<?= (int) $id ?>" <?= (string) $filters['assignee'] === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <label class="btn <?= $filters['late'] === '1' ? 'btn-danger' : 'btn-ghost' ?> cursor-pointer">
                        <input type="checkbox" name="vencidas" value="1" class="hidden" onchange="this.form.submit()"
                            <?= $filters['late'] === '1' ? 'checked' : '' ?>>
                        <i class="icon icon-clock"></i> <?= lang('Pqr.onlyLate') ?>
                    </label>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Pqr.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($dirty): ?>
                            <a href="<?= $base ?>" class="btn btn-ghost" title="<?= esc(lang('Pqr.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => $base,
                'extraParams' => [
                    'estado'      => $filters['status'],
                    'tipo'        => $filters['type'],
                    'punto'       => $filters['point'],
                    'responsable' => $filters['assignee'],
                    'vencidas'    => $filters['late'],
                ],
                'emptyText' => lang('Pqr.noRequests'),
                'columns'   => [
                    ['key' => 'number', 'label' => lang('Pqr.number'), 'sortable' => true,
                        'render' => static fn ($r) => '<a class="font-semibold text-primary" href="' . base_url('pqrsf/ver/' . (int) $r['id']) . '">' . esc($r['number']) . '</a>'
                            . '<div class="text-xs text-muted">' . esc(date('Y-m-d', strtotime((string) $r['created_at']))) . '</div>'],
                    ['key' => 'subject', 'label' => lang('Pqr.subject'), 'sortable' => false,
                        'render' => static fn ($r) => '<div class="max-w-xs truncate">' . esc($r['subject']) . '</div>'
                            . '<div class="text-xs text-muted">'
                            . (!empty($r['is_anonymous'])
                                ? '<i class="icon icon-eye-slash"></i> ' . esc(lang('Pqr.anonymous'))
                                : esc($r['citizen_name'] ?: '—'))
                            . '</div>'],
                    ['key' => 'type', 'label' => lang('Pqr.fType'), 'sortable' => true,
                        'render' => static fn ($r) => '<span class="badge badge-info">' . esc($types[$r['type']] ?? $r['type']) . '</span>'],
                    ['key' => 'point_id', 'label' => lang('Pqr.point'), 'sortable' => false,
                        'render' => static fn ($r) => esc($points[$r['point_id']] ?? '—')],
                    ['key' => 'assignee_id', 'label' => lang('Pqr.assignee'), 'sortable' => false,
                        'render' => static fn ($r) => $r['assignee_id']
                            ? esc($agents[(int) $r['assignee_id']] ?? '#' . $r['assignee_id'])
                            : '<span class="text-muted">' . esc(lang('Pqr.unassigned')) . '</span>'],
                    ['key' => 'due_at', 'label' => lang('Pqr.due'), 'sortable' => true,
                        'render' => static function ($r) use ($now) {
                            if (!$r['due_at']) {
                                return '—';
                            }
                            $late = $r['due_at'] < $now && in_array($r['status'], ['recibida', 'en_gestion'], true);

                            return '<span class="' . ($late ? 'font-semibold text-danger' : 'text-muted') . '">'
                                . esc(date('Y-m-d', strtotime((string) $r['due_at']))) . '</span>';
                        }],
                    ['key' => 'status', 'label' => lang('Pqr.fStatus'), 'sortable' => true,
                        'render' => static function ($r) {
                            $tone = [
                                'recibida'   => 'badge-warning',
                                'en_gestion' => 'badge-info',
                                'respondida' => 'badge-success',
                                'cerrada'    => 'badge-secondary',
                                'anulada'    => 'badge-danger',
                            ][$r['status']] ?? 'badge-secondary';

                            return '<span class="badge ' . $tone . '">' . esc(lang('Pqr.status_' . $r['status'])) . '</span>';
                        }],
                ],
                'actions' => static fn ($r) => '<div class="flex justify-end">'
                    . '<a href="' . base_url('pqrsf/ver/' . (int) $r['id']) . '" class="btn btn-secondary btn-sm">'
                    . '<i class="icon icon-eye"></i></a></div>',
            ]) ?>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
