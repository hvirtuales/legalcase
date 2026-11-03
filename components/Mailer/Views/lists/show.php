<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
// De dónde salió cada contacto: es la prueba del consentimiento que pide SES, y
// guardarla sin enseñarla en ningún sitio no sirve de nada.
$fuente = [
    'manual' => lang('Mailer.srcManual'),
    'import' => lang('Mailer.srcImport'),
    'form'   => lang('Mailer.srcForm'),
    'api'    => lang('Mailer.srcApi'),
];
$rol = new \Components\Mailer\Libraries\EmailValidator();

// Nombre del archivo del QR: el de la lista, reducido a algo que cualquier
// sistema de archivos acepte.
$qrFile = 'qr-' . (preg_replace('/[^a-z0-9]+/', '-', strtolower($list['name'])) ?: 'lista') . '.png';

$badge = [
    'subscribed'   => '<span class="badge badge-success">' . lang('Mailer.stSubscribed') . '</span>',
    'unsubscribed' => '<span class="badge badge-secondary">' . lang('Mailer.stUnsubscribed') . '</span>',
    // Sin confirmar: dio su consentimiento pero aún no pulsó el enlace del
    // correo, así que NO entra en ninguna cola (SENDABLE es 'subscribed').
    'pending'      => '<span class="badge badge-warning">' . lang('Mailer.stPending') . '</span>',
    'bounced'      => '<span class="badge badge-warning">' . lang('Mailer.stBounced') . '</span>',
    'complained'   => '<span class="badge badge-danger">' . lang('Mailer.stComplained') . '</span>',
];
?>
<div class="mb-4 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <a href="<?= base_url('mailer/listas') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Mailer.back') ?></a>
        <h5 class="font-semibold"><?= esc($list['name']) ?></h5>
    </div>
    <div class="flex items-center gap-2">
        <form method="post" action="<?= base_url('mailer/listas/' . $list['id'] . '/eliminar-duplicados') ?>" onsubmit="return confirm('<?= esc(lang('Mailer.dedupeConfirm'), 'js') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-clipboard"></i> <?= lang('Mailer.dedupe') ?></button>
        </form>
        <form method="post" action="<?= base_url('mailer/listas/' . $list['id'] . '/verificar-dominios') ?>" onsubmit="return confirm('<?= esc(lang('Mailer.verifyConfirm'), 'js') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-shield"></i> <?= lang('Mailer.verifyDomains') ?></button>
        </form>
        <a href="<?= base_url('mailer/listas/' . $list['id'] . '/contactos/importar') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-upload"></i> <?= lang('Mailer.importCsv') ?></a>
    </div>
</div>

<div class="grid grid-cols-2 gap-3 md:grid-cols-5 mb-4">
    <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold text-success"><?= (int) $stats['subscribed'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.subscribed') ?></div></div></div>
    <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold text-warning"><?= (int) ($stats['pending'] ?? 0) ?></div><div class="text-xs text-muted"><?= lang('Mailer.stPending') ?></div></div></div>
    <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold"><?= (int) $stats['unsubscribed'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.unsubscribed') ?></div></div></div>
    <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold text-warning"><?= (int) $stats['bounced'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.bounces') ?></div></div></div>
    <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold text-danger"><?= (int) $stats['complained'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.complaints') ?></div></div></div>
</div>

<!--
  El enlace del formulario público. Su sitio es este y no el de edición: aquí es
  donde se viene a copiarlo para pegarlo en una web o en un cartel. En edición
  solo se configura.
-->
<div class="card mb-4">
    <div class="card-header"><i class="icon icon-share"></i><h6 class="font-semibold"><?= lang('Mailer.publicSignup') ?></h6></div>
    <div class="card-body">
        <?php if ($link !== ''): ?>
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[280px]">
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" class="form-input flex-1 min-w-[240px]" id="signupLink" readonly value="<?= esc($link, 'attr') ?>">
                        <button type="button" class="btn btn-secondary" id="copySignup">
                            <i class="icon icon-clipboard"></i> <?= lang('Mailer.publicCopy') ?>
                        </button>
                        <a href="<?= esc($link, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-secondary">
                            <i class="icon icon-box-arrow-up-right"></i> <?= lang('Mailer.preview') ?>
                        </a>
                    </div>
                    <p class="mt-2 text-xs text-muted"><?= lang('Mailer.publicHint') ?></p>
                </div>

                <?php if ($qr): ?>
                    <!-- El QR del mismo enlace: para un cartel, el mostrador o el
                         pie de una factura. Va como data URI, así que se puede
                         guardar con el botón sin pasar por el servidor. -->
                    <div class="text-center">
                        <img src="<?= $qr ?>" alt="<?= esc(lang('Mailer.qrAlt'), 'attr') ?>"
                             width="150" height="150"
                             style="width:150px;height:150px;image-rendering:pixelated;background:#fff;padding:6px;border:1px solid var(--color-line);border-radius:.5rem">
                        <a class="btn btn-secondary btn-sm mt-2 w-full" download="<?= esc($qrFile, 'attr') ?>" href="<?= $qr ?>">
                            <i class="icon icon-download"></i> <?= lang('Mailer.qrDownload') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-muted">
                <?= lang('Mailer.publicOffPanel') ?>
                <a class="text-primary underline" href="<?= base_url('mailer/listas/editar/' . $list['id']) ?>"><?= lang('Mailer.publicOffLink') ?></a>
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="icon icon-plus-circle"></i><h6 class="font-semibold"><?= lang('Mailer.addContact') ?></h6></div>
    <div class="card-body">
        <form method="post" action="<?= base_url('mailer/listas/' . $list['id'] . '/contactos/agregar') ?>" class="flex flex-wrap items-end gap-2">
            <?= csrf_field() ?>
            <div class="form-group mb-0 flex-1 min-w-[220px]">
                <label class="form-label" for="email"><?= lang('Mailer.email') ?></label>
                <input type="email" name="email" id="email" class="form-input" required placeholder="<?= esc(lang('Mailer.emailPh'), 'attr') ?>">
            </div>
            <div class="form-group mb-0 flex-1 min-w-[180px]">
                <label class="form-label" for="name"><?= lang('Mailer.contactName') ?></label>
                <input type="text" name="name" id="name" class="form-input">
            </div>
            <div class="form-group mb-0 flex-1 min-w-[180px]">
                <label class="form-label" for="entity"><?= lang('Mailer.entityOpt') ?></label>
                <input type="text" name="entity" id="entity" class="form-input" placeholder="<?= esc(lang('Mailer.entityPh'), 'attr') ?>">
            </div>
            <div class="form-group mb-0 flex-1 min-w-[200px]">
                <label class="form-label" for="consent_note"><?= lang('Mailer.consentOrigin') ?></label>
                <input type="text" name="consent_note" id="consent_note" class="form-input"
                       placeholder="<?= esc(lang('Mailer.consentOriginPh'), 'attr') ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="icon icon-plus-circle"></i> <?= lang('Mailer.add') ?></button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="icon icon-people"></i><h6 class="font-semibold"><?= lang('Mailer.contacts') ?></h6></div>
    <div class="card-body">
        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('mailer/listas/' . $list['id']),
            'searchPlaceholder' => lang('Mailer.searchContact'),
            'emptyText'         => lang('Mailer.noContacts'),
            'columns'           => [
                ['key' => 'email', 'label' => lang('Mailer.email'), 'sortable' => true,
                    'render' => fn ($c) => esc($c['email'])
                        . ($rol->isRole($c['email'])
                            ? ' <span class="badge badge-warning" title="' . esc(lang('Mailer.roleWarn', [1, $c['email']]), 'attr') . '">'
                              . lang('Mailer.roleBadge') . '</span>'
                            : '')],
                ['key' => 'name', 'label' => lang('Mailer.name'), 'sortable' => true, 'render' => fn ($c) => esc($c['name'] ?: '—')],
                ['key' => 'entity', 'label' => lang('Mailer.entity'), 'sortable' => true, 'render' => fn ($c) => esc($c['entity'] ?: '—')],
                ['key' => 'status', 'label' => lang('Mailer.status'), 'sortable' => true, 'render' => fn ($c) => $badge[$c['status']] ?? esc($c['status'])],
                ['key' => 'consent_source', 'label' => lang('Mailer.consentSource'), 'sortable' => true,
                    'render' => function ($c) use ($fuente) {
                        $src = (string) ($c['consent_source'] ?? '');
                        if ($src === '') {
                            return '<span class="text-xs text-muted">' . lang('Mailer.srcNone') . '</span>';
                        }
                        $txt = $fuente[$src] ?? esc($src);
                        $nota = trim((string) ($c['consent_note'] ?? ''));

                        return '<span title="' . esc(trim($nota . ' ' . substr((string) ($c['consent_at'] ?? ''), 0, 10)), 'attr') . '">'
                            . esc($txt) . '</span>'
                            . ($nota !== '' ? '<div class="text-xs text-muted truncate max-w-[160px]">' . esc($nota) . '</div>' : '');
                    }],
            ],
            'actions'           => function ($c) {
                $h = '<div class="flex justify-end gap-1">';
                if ($c['status'] === 'subscribed') {
                    $h .= view('partials/delete_button', [
                        'action' => base_url('mailer/contactos/baja/' . $c['id']),
                        'confirm' => lang('Mailer.unsubConfirm'),
                        'icon' => 'x-circle', 'label' => lang('Mailer.unsubscribeOne'), 'text' => lang('Mailer.stUnsubscribed'), 'class' => 'btn btn-secondary btn-sm',
                    ]);
                }
                $h .= view('partials/delete_button', [
                    'action' => base_url('mailer/contactos/eliminar/' . $c['id']),
                    'confirm' => lang('Mailer.delContact'),
                    'text' => lang('Mailer.delete'),
                ]);

                return $h . '</div>';
            },
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        var btn = document.getElementById('copySignup');
        var box = document.getElementById('signupLink');
        if (!btn || !box) { return; }
        btn.addEventListener('click', function () {
            box.select();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(box.value).then(function () {}, function () {});
            } else {
                try { document.execCommand('copy'); } catch (e) {}
            }
            btn.textContent = <?= json_encode(lang('Mailer.publicCopied'), JSON_UNESCAPED_UNICODE) ?>;
        });
    })();
</script>
<?= $this->endSection() ?>