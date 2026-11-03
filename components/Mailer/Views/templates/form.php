<?= $this->extend('Components\Mailer\Views\_builder_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * Formulario de plantilla, a PANTALLA COMPLETA.
 *
 * Usa EL MISMO constructor y el mismo armazón que el asistente de campaña.
 * Antes tenía un editor propio (`editor.js`) que hacía lo mismo con otro código
 * y otros textos, y que además guardaba el HTML que armaba el NAVEGADOR: aquí el
 * navegador solo edita el árbol y el HTML lo produce `EmailRenderer` al guardar.
 */
$accion = $tpl ? base_url('mailer/plantillas/editar/' . $tpl['id']) : base_url('mailer/plantillas/crear');
?>
<div class="ml-shell">

    <header class="ml-bar">
        <a href="<?= base_url('mailer/plantillas') ?>" class="btn btn-ghost btn-sm" title="<?= esc(lang('Mailer.templates'), 'attr') ?>">
            <i class="icon icon-arrow-left"></i> <span class="hidden sm:inline"><?= lang('Mailer.templates') ?></span>
        </a>
        <div class="ml-bar-title"><strong><?= esc($title) ?></strong></div>

        <div class="ml-bar-actions">
            <?php if ($tpl): ?>
                <a href="<?= base_url('mailer/plantillas/' . $tpl['id'] . '/preview') ?>" target="_blank" class="btn btn-ghost btn-sm">
                    <i class="icon icon-box-arrow-up-right"></i> <span class="hidden md:inline"><?= lang('Mailer.openPreview') ?></span>
                </a>
            <?php endif; ?>
            <a href="<?= base_url('mailer/plantillas') ?>" class="btn btn-secondary btn-sm"><?= lang('Mailer.cancel') ?></a>
            <button form="mlForm" class="btn btn-primary btn-sm">
                <i class="icon icon-check"></i> <?= lang('Mailer.save') ?>
            </button>
        </div>
    </header>

    <form method="post" action="<?= $accion ?>" id="mlForm" class="ml-builder" data-ml-builder>
        <?= csrf_field() ?>
        <input type="hidden" name="design_json" id="design_json" value="<?= esc($tpl['design_json'] ?? '', 'attr') ?>">

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

            <?php if ($tpl): ?>
                <!-- La prueba de envío se queda BAJO el lienzo, no en la barra: usa
                     la última versión guardada, así que su sitio es después del correo. -->
                <div class="card mt-3">
                    <div class="card-header"><i class="icon icon-envelope"></i><h6 class="font-semibold"><?= lang('Mailer.sendTest') ?></h6></div>
                    <div class="card-body">
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="form-group mb-0 flex-1 min-w-[220px]">
                                <label class="form-label" for="test_email"><?= lang('Mailer.sendTestTo') ?></label>
                                <input type="email" form="mlTest" name="test_email" id="test_email" class="form-input" required
                                       placeholder="<?= esc(lang('Mailer.testPh'), 'attr') ?>">
                            </div>
                            <button type="submit" form="mlTest" class="btn btn-secondary"><i class="icon icon-send"></i> <?= lang('Mailer.sendTest') ?></button>
                        </div>
                        <p class="mt-2 text-xs text-muted"><?= lang('Mailer.previewSaved') ?></p>
                        <p class="mt-1 text-xs text-muted"><?= lang('Mailer.testNote') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Dos caras, como en el asistente: el bloque elegido y los datos de la
             plantilla. Antes ocupaban una tarjeta encima del lienzo. -->
        <aside class="ml-side ml-right">
            <div class="ml-panel-tabs">
                <button type="button" class="ml-panel-tab is-on" data-ml-panel="props"><?= lang('Mailer.panelBlock') ?></button>
                <button type="button" class="ml-panel-tab" data-ml-panel="mail"><?= lang('Mailer.panelTemplate') ?></button>
            </div>
            <div class="ml-side-body">
                <div class="ml-pane" data-ml-pane="props">
                    <div data-ml-inspector></div>
                </div>

                <div class="ml-pane ml-settings" data-ml-pane="mail" hidden>
                    <div class="form-group">
                        <label class="form-label" for="name"><?= lang('Mailer.name') ?></label>
                        <input type="text" name="name" id="name" class="form-input" required
                               value="<?= esc($tpl['name'] ?? old('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="subject"><?= lang('Mailer.subjectSug') ?></label>
                        <input type="text" name="subject" id="subject" class="form-input"
                               value="<?= esc($tpl['subject'] ?? old('subject'), 'attr') ?>">
                    </div>
                    <p class="text-xs text-muted"><?= lang('Mailer.designHint') ?></p>
                </div>
            </div>
        </aside>
    </form>

    <?php if ($tpl): ?>
        <form method="post" id="mlTest" action="<?= base_url('mailer/plantillas/prueba/' . $tpl['id']) ?>" hidden><?= csrf_field() ?></form>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('Components\Mailer\Views\_builder_i18n') ?>
<script type="application/json" id="mlRegistry"><?= json_encode($registry, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mlPresets"><?= json_encode(\Components\Mailer\Libraries\SectionLibrary::forJs(), JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mlGroups"><?= json_encode($groups, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mlDesign"><?= json_encode($tpl['design_json'] ?? '', JSON_UNESCAPED_UNICODE) ?></script>
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
