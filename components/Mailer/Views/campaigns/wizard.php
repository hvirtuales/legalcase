<?= $this->extend('Components\Mailer\Views\_builder_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * Asistente de campaña, en tres pasos, a PANTALLA COMPLETA.
 *
 * Antes era un formulario único con todo a la vez: nombre, lista, remitente,
 * asunto y el cuerpo del correo en un cuadro de HTML CRUDO. Ahora cada paso
 * pregunta una cosa —a quién, qué, y revisar antes de enviar— y el contenido se
 * monta con un constructor visual como el del creador de formularios.
 *
 * El armazón es el mismo en los tres pasos —barra arriba con la salida, el
 * título, los pasos y las acciones— para que pasar de uno a otro no mueva la
 * pantalla debajo de las manos. Lo que cambia es el cuerpo: los pasos 1 y 3 son
 * una columna centrada que se desplaza; el 2 son las tres columnas del
 * constructor, cada una con su propio desplazamiento.
 *
 * @var array $campaign  vacío cuando aún no existe (paso 1 del alta)
 * @var int   $step
 */
$id     = (int) ($campaign['id'] ?? 0);
$isNew  = $id === 0;
$action = $isNew ? base_url('mailer/campanas/crear') : base_url('mailer/campanas/asistente/' . $id);

$pasos = [
    1 => [lang('Mailer.wizStep1'), lang('Mailer.wizStep1Sub'), 'icon-people'],
    2 => [lang('Mailer.wizStep2'), lang('Mailer.wizStep2Sub'), 'icon-envelope'],
    3 => [lang('Mailer.wizStep3'), lang('Mailer.wizStep3Sub'), 'icon-send'],
];
?>
<div class="ml-shell">

    <header class="ml-bar">
        <a href="<?= base_url('mailer/campanas') ?>" class="btn btn-ghost btn-sm" title="<?= esc(lang('Mailer.campaigns'), 'attr') ?>">
            <i class="icon icon-arrow-left"></i> <span class="hidden sm:inline"><?= lang('Mailer.campaigns') ?></span>
        </a>
        <div class="ml-bar-title"><strong><?= esc($title) ?></strong></div>

        <!-- Los tres pasos, siempre a la vista: se ve dónde estás y cuánto queda. -->
        <ol class="ml-wiz is-compact">
            <?php foreach ($pasos as $n => [$nombre, $sub, $icono]): ?>
                <?php
                $estado = $n === $step ? 'is-now' : ($n < $step ? 'is-done' : '');
                // A los pasos ya recorridos se puede volver; a los futuros no,
                // porque el paso 2 necesita una campaña ya creada.
                $ir = (!$isNew && $n !== $step) ? base_url('mailer/campanas/asistente/' . $id . '?paso=' . $n) : null;
                ?>
                <li class="ml-wiz-step <?= $estado ?>">
                    <?php if ($ir): ?><a href="<?= $ir ?>"><?php else: ?><span><?php endif; ?>
                        <span class="ml-wiz-n"><?= $n ?></span>
                        <span class="ml-wiz-t">
                            <strong><?= esc($nombre) ?></strong>
                            <small><?= esc($sub) ?></small>
                        </span>
                    <?php if ($ir): ?></a><?php else: ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="ml-bar-actions">
            <?php if (!$isNew): ?>
                <a href="<?= base_url('mailer/campanas/' . $id) ?>" class="btn btn-ghost btn-sm">
                    <i class="icon icon-eye"></i> <span class="hidden md:inline"><?= lang('Mailer.wizSeeCampaign') ?></span>
                </a>
            <?php endif; ?>
            <?php if ($step === 2): ?>
                <a href="<?= base_url('mailer/campanas/asistente/' . $id . '?paso=1') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-chevron-left"></i> <?= lang('Mailer.wizBack') ?>
                </a>
                <button form="mlForm" class="btn btn-primary btn-sm">
                    <?= lang('Mailer.wizSaveNext') ?> <i class="icon icon-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($step === 1): ?>
        <!-- ============================ PASO 1: destinatarios ============ -->
        <div class="ml-main">
            <div class="ml-wrap-narrow">
                <?php if (session('errors')): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0"><?php foreach ((array) session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= $action ?>" class="card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="paso" value="1">
                    <div class="card-body space-y-4">
                        <div class="form-group">
                            <label class="form-label" for="name"><?= lang('Mailer.wizCampaignName') ?></label>
                            <input type="text" name="name" id="name" class="form-input" required
                                   value="<?= esc($campaign['name'] ?? old('name'), 'attr') ?>"
                                   placeholder="<?= esc(lang('Mailer.wizNamePh'), 'attr') ?>">
                            <p class="mt-1 text-xs text-muted"><?= lang('Mailer.wizNameHint') ?></p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="list_id"><?= lang('Mailer.wizList') ?></label>
                            <select name="list_id" id="list_id" class="form-select" required>
                                <option value=""><?= lang('Mailer.wizListPick') ?></option>
                                <?php foreach ($lists as $l): ?>
                                    <option value="<?= (int) $l['id'] ?>"
                                        <?= (int) ($campaign['list_id'] ?? old('list_id')) === (int) $l['id'] ? 'selected' : '' ?>>
                                        <?= esc($l['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$lists): ?>
                                <p class="mt-1 text-xs text-danger">
                                    <?= lang('Mailer.wizNoLists') ?>
                                    <a class="underline" href="<?= base_url('mailer/listas/crear') ?>"><?= lang('Mailer.wizCreateList') ?></a>.
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($isNew && $templates): ?>
                            <fieldset class="form-section">
                                <legend class="font-semibold"><?= lang('Mailer.wizFromTemplate') ?></legend>
                                <p class="mb-3 text-xs text-muted">
                                    <?= lang('Mailer.wizFromTemplateHint') ?>
                                </p>
                                <select name="template_id" class="form-select">
                                    <option value=""><?= lang('Mailer.wizBlank') ?></option>
                                    <?php foreach ($templates as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>"><?= esc($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </fieldset>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer flex justify-end gap-2">
                        <a href="<?= base_url('mailer/campanas') ?>" class="btn btn-secondary"><?= lang('Mailer.cancel') ?></a>
                        <button class="btn btn-primary"><?= lang('Mailer.wizContinue') ?> <i class="icon icon-chevron-right"></i></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($step === 2): ?>
        <!-- ============================ PASO 2: contenido ================ -->
        <form method="post" action="<?= $action ?>" id="mlForm" class="ml-builder" data-ml-builder>
            <?= csrf_field() ?>
            <input type="hidden" name="paso" value="2">
            <input type="hidden" name="design_json" id="design_json"
                   value="<?= esc($campaign['design_json'] ?? '', 'attr') ?>">

            <aside class="ml-side ml-left">
                <div class="ml-side-head"><?= lang('Mailer.wizElements') ?></div>
                <div class="ml-side-body" data-ml-palette></div>
            </aside>

            <main class="ml-canvas">
                <div class="ml-canvas-bar">
                    <span data-ml-count></span>
                    <button type="button" class="btn btn-ghost btn-sm" data-ml-addsection>
                        <i class="icon icon-plus-circle"></i> <?= lang('Mailer.wizAddSection') ?>
                    </button>
                </div>

                <?php if (session('errors')): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0"><?php foreach ((array) session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <div class="ml-paper" data-ml-list></div>
            </main>

            <!--
              El panel derecho tiene DOS caras: las propiedades del bloque
              elegido y los ajustes del correo —asunto y remitente—, que antes
              ocupaban una tarjeta encima del lienzo. Ahí le robaban al correo
              justo el alto que hace falta para verlo entero.
            -->
            <aside class="ml-side ml-right">
                <div class="ml-panel-tabs">
                    <button type="button" class="ml-panel-tab is-on" data-ml-panel="props"><?= lang('Mailer.panelBlock') ?></button>
                    <button type="button" class="ml-panel-tab" data-ml-panel="mail"><?= lang('Mailer.panelMail') ?></button>
                </div>
                <div class="ml-side-body">
                    <div class="ml-pane" data-ml-pane="props">
                        <div data-ml-inspector></div>
                    </div>

                    <div class="ml-pane ml-settings" data-ml-pane="mail" hidden>
                        <?php
                        // Al crear la campaña, el asunto nace con su NOMBRE porque el
                        // modelo lo exige. Enseñarlo aquí invitaría a enviarlo tal cual,
                        // y el nombre es de uso interno: si sigue sin tocar, el campo se
                        // muestra vacío para que se escriba uno de verdad.
                        $asunto = (string) ($campaign['subject'] ?? '');
                        if ($asunto === (string) ($campaign['name'] ?? '')) {
                            $asunto = '';
                        }
                        ?>
                        <div class="form-group">
                            <label class="form-label" for="subject"><?= lang('Mailer.subject') ?></label>
                            <input type="text" name="subject" id="subject" class="form-input" required
                                   value="<?= esc($asunto, 'attr') ?>"
                                   placeholder="<?= esc(lang('Mailer.wizSubjectPh'), 'attr') ?>">
                            <p class="mt-1 text-xs text-muted">
                                <?= lang('Mailer.wizSubjectHint', ['<code>{{nombre}}</code>', '<code>{{email}}</code>', '<code>{{entidad}}</code>']) ?>
                            </p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="from_name"><?= lang('Mailer.wizFromName') ?></label>
                            <input type="text" name="from_name" id="from_name" class="form-input"
                                   value="<?= esc($campaign['from_name'] ?? ($company['name'] ?? ''), 'attr') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="from_email"><?= lang('Mailer.wizFromEmail') ?></label>
                            <input type="email" name="from_email" id="from_email" class="form-input"
                                   placeholder="<?= esc($sesFrom ?: 'remitente@tudominio.com', 'attr') ?>"
                                   value="<?= esc($campaign['from_email'] ?? '', 'attr') ?>">
                            <p class="mt-1 text-xs text-muted"><?= lang('Mailer.wizFromEmailHint') ?></p>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label" for="reply_to"><?= lang('Mailer.wizReplyTo') ?></label>
                            <input type="email" name="reply_to" id="reply_to" class="form-input"
                                   value="<?= esc($campaign['reply_to'] ?? '', 'attr') ?>">
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    <?php endif; ?>

    <?php if ($step === 3): ?>
        <div class="ml-main">
            <div class="ml-wrap">
        <!-- ============================ PASO 3: revisión ================= -->
        <?php
        // Las comprobaciones se calculan en el servidor y se pintan como lo que
        // son: lo que impide enviar, y lo que solo conviene mirar.
        $frena = [];
        $avisa = [];

        if ((int) ($sendable ?? 0) === 0) {
            $frena[] = lang('Mailer.chkNoContacts');
        }
        if (trim((string) ($campaign['subject'] ?? '')) === '') {
            $frena[] = lang('Mailer.chkNoSubject');
        }
        if (trim((string) ($campaign['body_html'] ?? '')) === '') {
            $frena[] = lang('Mailer.chkNoBody');
        }
        if (($quota ?? null) !== null && $quota <= 0) {
            $frena[] = lang('Mailer.chkNoQuota');
        }
        if (($reputation['level'] ?? '') === 'block') {
            $frena[] = lang('Mailer.chkReputation', [$reputation['reason'] ?? '']);
        }

        if (trim((string) ($campaign['from_email'] ?? '')) === '') {
            $avisa[] = lang('Mailer.chkNoSender');
        }
        if (!empty($missingAddress)) {
            $avisa[] = lang('Mailer.chkNoAddress');
        }
        if (($reputation['level'] ?? '') === 'warn') {
            $avisa[] = lang('Mailer.chkRepWarn', [$reputation['reason'] ?? '']);
        }
        ?>

        <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
            <div class="card">
                <div class="card-header"><h6 class="font-semibold"><?= lang('Mailer.wizPreviewTitle') ?></h6></div>
                <div class="card-body">
                    <iframe src="<?= base_url('mailer/campanas/' . $id . '/preview') ?>"
                            title="<?= esc(lang('Mailer.previewAlt2'), 'attr') ?>" class="ml-preview"></iframe>
                </div>
            </div>

            <div class="space-y-4">
                <div class="card">
                    <div class="card-header"><h6 class="font-semibold"><?= lang('Mailer.wizChecks') ?></h6></div>
                    <div class="card-body space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-muted"><?= lang('Mailer.list') ?></span>
                            <strong><?= (int) ($sendable ?? 0) === 1 ? lang('Mailer.wizOneContact') : lang('Mailer.wizNContacts', [(int) ($sendable ?? 0)]) ?></strong></div>
                        <div class="flex justify-between"><span class="text-muted"><?= lang('Mailer.subject') ?></span>
                            <strong class="truncate max-w-[190px]"><?= esc($campaign['subject'] ?? '—') ?></strong></div>
                        <div class="flex justify-between"><span class="text-muted"><?= lang('Mailer.wizSender') ?></span>
                            <strong class="truncate max-w-[190px]"><?= esc($campaign['from_email'] ?: ($sesFrom ?: '—')) ?></strong></div>
                        <div class="flex justify-between"><span class="text-muted"><?= lang('Mailer.wizQuota') ?></span>
                            <strong><?= ($quota ?? null) === null ? lang('Mailer.wizNoLimit') : (int) $quota ?></strong></div>
                    </div>
                </div>

                <?php foreach ($frena as $m): ?>
                    <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i><span><?= esc($m) ?></span></div>
                <?php endforeach; ?>
                <?php foreach ($avisa as $m): ?>
                    <div class="alert alert-warning"><i class="icon icon-info-circle"></i><span><?= esc($m) ?></span></div>
                <?php endforeach; ?>

                <div class="card">
                    <div class="card-header"><h6 class="font-semibold"><?= lang('Mailer.wizTestTitle') ?></h6></div>
                    <div class="card-body">
                        <form method="post" action="<?= base_url('mailer/campanas/prueba/' . $id) ?>" class="flex gap-2">
                            <?= csrf_field() ?>
                            <input type="email" name="test_email" class="form-input" required placeholder="<?= esc(lang('Mailer.testPh'), 'attr') ?>">
                            <button class="btn btn-secondary"><i class="icon icon-send"></i></button>
                        </form>
                        <p class="mt-2 text-xs text-muted"><?= lang('Mailer.wizTestHint') ?></p>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <a href="<?= base_url('mailer/campanas/asistente/' . $id . '?paso=2') ?>" class="btn btn-secondary">
                        <i class="icon icon-chevron-left"></i> <?= lang('Mailer.wizBackContent') ?>
                    </a>
                    <?php if (!$frena): ?>
                        <form method="post" action="<?= base_url('mailer/campanas/encolar/' . $id) ?>"
                              onsubmit="return confirm('<?= esc(lang('Mailer.wizConfirmSend', [(int) ($sendable ?? 0)]), 'js') ?>')">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary w-full"><i class="icon icon-send"></i> <?= lang('Mailer.wizSend') ?></button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-primary w-full" disabled title="<?= esc(lang('Mailer.wizSendFix'), 'attr') ?>">
                            <i class="icon icon-send"></i> <?= lang('Mailer.wizSend') ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
            </div>
        </div>
    <?php endif; ?>

</div>
<?= $this->endSection() ?>

<?php if ($step === 2): ?>
<?= $this->section('scripts') ?>
<?= view('Components\Mailer\Views\_builder_i18n') ?>
<script type="application/json" id="mlRegistry"><?= json_encode($registry, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mlPresets"><?= json_encode(\Components\Mailer\Libraries\SectionLibrary::forJs(), JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mlGroups"><?= json_encode($groups, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mlDesign"><?= json_encode($campaign['design_json'] ?? '', JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mlMeta"><?= json_encode([
    'uploadUrl' => base_url('mailer/media/subir'),
    'csrfName'  => csrf_token(),
    'csrfHash'  => csrf_hash(),
    'company'   => [
        'name' => (string) ($company['name'] ?? ''),
        'logo' => (string) ($company['logo'] ?? ''),
    ],
], JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= base_url('mailer-publico/recurso/builder.js') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/js/builder.js') ?>"></script>
<?= $this->endSection() ?>
<?php endif; ?>
