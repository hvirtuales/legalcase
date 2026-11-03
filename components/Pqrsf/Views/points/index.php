<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Los puntos de publicación: cada uno es un QR pegado en un área.
 *
 * La URL se muestra completa y copiable porque es lo que se lleva a la
 * imprenta; el afiche en PDF es el atajo para no tener que maquetarlo.
 *
 * @var array|null $dt @var array $urls @var array $counts
 */
$base = base_url('pqrsf/puntos');
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-qr-code"></i>
            <h5 class="font-semibold"><?= lang('Pqr.points') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= $base ?>/nuevo" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Pqr.pointsNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Pqr.errNoCompany') ?></span></div>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Pqr.pointsHint') ?></p>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => $base,
                'searchPlaceholder' => lang('Pqr.pointsSearch'),
                'emptyText'         => lang('Pqr.noPoints'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Pqr.pointName'), 'sortable' => true,
                        'render' => static fn ($p) => '<strong>' . esc($p['name']) . '</strong>'
                            . ($p['notes'] ? '<div class="text-xs text-muted">' . esc($p['notes']) . '</div>' : '')],
                    ['key' => 'area', 'label' => lang('Pqr.pointArea'), 'sortable' => true,
                        'render' => static fn ($p) => esc($p['area'] ?: '—')],
                    ['key' => 'token', 'label' => lang('Pqr.pointUrl'), 'sortable' => false,
                        'render' => static function ($p) use ($urls) {
                            $url = $urls[$p['id']] ?? '';

                            return '<div class="flex items-center gap-1">'
                                . '<code class="max-w-[16rem] truncate text-xs">' . esc($url) . '</code>'
                                . '<button type="button" class="btn btn-ghost btn-sm" data-copy="' . esc($url) . '"'
                                . ' title="' . esc(lang('Pqr.copy'), 'attr') . '"><i class="icon icon-link"></i></button>'
                                . '</div>';
                        }],
                    ['key' => 'visits', 'label' => lang('Pqr.pointVisits'), 'sortable' => true,
                        'render' => static fn ($p) => '<span class="text-muted">' . (int) $p['visits'] . '</span>'],
                    ['key' => 'requests', 'label' => lang('Pqr.pointRequests'), 'sortable' => false,
                        'render' => static fn ($p) => '<span class="badge badge-info">' . (int) ($counts[$p['id']] ?? 0) . '</span>'],
                    ['key' => 'is_active', 'label' => lang('Pqr.fStatus'), 'sortable' => false,
                        'render' => static fn ($p) => !empty($p['is_active'])
                            ? '<span class="badge badge-success">' . esc(lang('Pqr.active')) . '</span>'
                            : '<span class="badge badge-secondary">' . esc(lang('Pqr.inactive')) . '</span>'],
                ],
                'actions' => static function ($p) {
                    $id = (int) $p['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('pqrsf/puntos/cartel/' . $id) . '" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"'
                        . ' title="' . esc(lang('Pqr.poster'), 'attr') . '"><i class="icon icon-printer"></i></a>'
                        . '<a href="' . base_url('pqrsf/puntos/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action'  => base_url('pqrsf/puntos/eliminar/' . $id),
                            'label'   => lang('Pqr.delete'),
                            'confirm' => lang('Pqr.pointConfirm'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Copiar la URL del punto sin salir del listado.
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn) { return; }

    var url  = btn.getAttribute('data-copy');
    var icon = btn.querySelector('i');
    var mark = function () {
        icon.classList.replace('icon-link', 'icon-check-circle');
        setTimeout(function () { icon.classList.replace('icon-check-circle', 'icon-link'); }, 1800);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(mark);
    } else {
        // Sin HTTPS no hay clipboard API: el textarea temporal de siempre.
        var t = document.createElement('textarea');
        t.value = url;
        document.body.appendChild(t);
        t.select();
        try { document.execCommand('copy'); mark(); } catch (err) {}
        document.body.removeChild(t);
    }
});
</script>
<?= $this->endSection() ?>
