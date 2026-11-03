<?= $this->extend('Components\Formularios\Views\_editor_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * El editor. Tres columnas y una barra: los datos van al JavaScript en bloques
 * JSON, y todo lo demás lo pinta `editor.js`.
 *
 * @var array  $form
 * @var array  $schema
 * @var array  $settings
 * @var array  $registry
 * @var array  $groups
 * @var string $publicUrl
 */
$groupLabels = [
    'text'      => lang('Frm.gText'),
    'choice'    => lang('Frm.gChoice'),
    'advanced'  => lang('Frm.gAdvanced'),
    'structure' => lang('Frm.gStructure'),
];
$operators = [
    'equals' => lang('Frm.opEquals'), 'not_equals' => lang('Frm.opNotEquals'),
    'contains' => lang('Frm.opContains'), 'is_checked' => lang('Frm.opIsChecked'),
    'is_not_checked' => lang('Frm.opIsNotChecked'), 'greater_than' => lang('Frm.opGreater'),
    'less_than' => lang('Frm.opLess'), 'is_empty' => lang('Frm.opEmpty'),
    'is_not_empty' => lang('Frm.opNotEmpty'),
];
?>
<div class="frm-shell">

    <!-- ------------------------------------------------------- barra -->
    <header class="frm-bar">
        <a class="btn btn-ghost btn-sm" href="<?= base_url('formularios') ?>">
            <i class="icon icon-arrow-left"></i> <?= lang('Frm.backToList') ?>
        </a>

        <div class="frm-bar-title">
            <span id="frmTitleText"><?= esc($form['title']) ?></span>
            <span class="badge <?= (string) $form['status'] === 'published' ? 'badge-success' : 'badge-secondary' ?>">
                <?= (string) $form['status'] === 'published' ? lang('Frm.statusPublished') : ((string) $form['status'] === 'closed' ? lang('Frm.statusClosed') : lang('Frm.statusDraft')) ?>
            </span>
            <span class="frm-state" data-frm-state="saved"><?= lang('Frm.saved') ?></span>
        </div>

        <div class="frm-bar-actions">
            <button type="button" class="btn btn-ghost btn-sm" data-frm-undo title="<?= esc(lang('Frm.undo'), 'attr') ?> (Ctrl+Z)" disabled>
                <i class="icon icon-arrow-counterclockwise"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" data-frm-redo title="<?= esc(lang('Frm.redo'), 'attr') ?> (Ctrl+Y)" disabled>
                <i class="icon icon-arrow-repeat"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" data-frm-clear title="<?= esc(lang('Frm.clearCanvas'), 'attr') ?>">
                <i class="icon icon-trash"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" data-frm-code title="<?= esc(lang('Frm.codeTitle'), 'attr') ?>">
                <i class="icon icon-file-earmark"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" data-frm-toggle="left" title="<?= esc(lang('Frm.fields'), 'attr') ?>">
                <i class="icon icon-list"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" data-frm-toggle="right" title="<?= esc(lang('Frm.properties'), 'attr') ?>">
                <i class="icon icon-tools"></i>
            </button>
            <!--
              Vista previa por dispositivo. Abre la página REAL en una ventana
              del ancho pedido en vez de encoger el lienzo: las `@media` miran
              el ancho de la VENTANA, así que estrechar el lienzo no dispararía
              ni una regla responsive y enseñaría una mentira. Es la misma
              decisión que tomó el editor de Páginas.
            -->
            <a class="btn btn-secondary btn-sm" href="<?= esc($publicUrl, 'attr') ?>" target="_blank" rel="noopener"
               data-frm-see title="<?= esc(lang('Frm.seeDesktop'), 'attr') ?>">
                <i class="icon icon-eye"></i> <?= lang('Frm.preview') ?>
            </a>
            <button type="button" class="btn btn-ghost btn-sm" data-frm-see-w="820" title="<?= esc(lang('Frm.seeTablet'), 'attr') ?>">
                <i class="icon icon-book"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" data-frm-see-w="390" title="<?= esc(lang('Frm.seeMobile'), 'attr') ?>">
                <i class="icon icon-telephone"></i>
            </button>
            <a class="btn btn-secondary btn-sm" href="<?= base_url('formularios/compartir/' . $form['id']) ?>">
                <i class="icon icon-share"></i> <?= lang('Frm.share') ?>
            </a>
            <form method="post" action="<?= base_url('formularios/publicar/' . $form['id']) ?>" class="inline" data-frm-publish>
                <?= csrf_field() ?>
                <button class="btn btn-secondary btn-sm"><i class="icon icon-check-circle"></i> <?= lang('Frm.publish') ?></button>
            </form>
            <button type="button" class="btn btn-primary btn-sm" data-frm-save>
                <i class="icon icon-save"></i> <?= lang('Frm.save') ?>
            </button>
        </div>
    </header>

    <div class="frm-cols">

        <!-- ------------------------------------------ columna izquierda -->
        <aside class="frm-side frm-left" data-frm-panel="left">
            <div class="frm-tabs">
                <button type="button" class="frm-tab is-on" data-frm-ltab="fields"><?= lang('Frm.fields') ?></button>
                <button type="button" class="frm-tab" data-frm-ltab="structure"><?= lang('Frm.structure') ?></button>
                <button type="button" class="frm-x" data-frm-toggle="left" aria-label="<?= esc(lang('Frm.close'), 'attr') ?>">
                    <i class="icon icon-x-lg"></i>
                </button>
            </div>

            <div class="frm-side-body" data-frm-lpane="fields">
                <div class="frm-palette" data-frm-palette></div>
            </div>

            <div class="frm-side-body" data-frm-lpane="structure" hidden>
                <div data-frm-structure></div>
            </div>
        </aside>

        <!-- ------------------------------------------------- el lienzo -->
        <main class="frm-canvas">
            <div class="frm-pagebar" data-frm-pagebar></div>
            <!-- Ajustes de la página activa: nombre, instrucciones y su orden.
                 Estaban solo en la pestaña «Formulario», que es el sitio donde
                 nadie los busca mientras monta los campos. -->
            <div class="frm-pagetools" data-frm-pagetools></div>
            <div class="frm-paper">
                <!-- Título y descripción, editables donde se ven. -->
                <div class="frm-paper-head" data-frm-paperhead></div>
                <div class="frm-drop" data-frm-list></div>
            </div>
            <p class="frm-count" data-frm-count></p>
        </main>

        <!-- -------------------------------------------- columna derecha -->
        <aside class="frm-side frm-right" data-frm-panel="right">
            <div class="frm-tabs">
                <button type="button" class="frm-tab is-on" data-frm-rtab="props"><?= lang('Frm.properties') ?></button>
                <button type="button" class="frm-tab" data-frm-rtab="form"><?= lang('Frm.formTab') ?></button>
                <button type="button" class="frm-x" data-frm-toggle="right" aria-label="<?= esc(lang('Frm.close'), 'attr') ?>">
                    <i class="icon icon-x-lg"></i>
                </button>
            </div>

            <div class="frm-side-body" data-frm-rpane="props">
                <div data-frm-inspector></div>
            </div>

            <div class="frm-side-body" data-frm-rpane="form" hidden>
                <div id="frmTitleReminder" class="alert alert-info mb-3" hidden>
                    <i class="icon icon-info-circle"></i><span><?= lang('Frm.titleReminder') ?></span>
                </div>
                <div data-frm-settings></div>
            </div>
        </aside>
    </div>
</div>

<!--
  Copiar y pegar el formulario entero. Es lo que hace útil que el contenido sea
  un ARBOL y no HTML guardado: se lleva a otro formulario tal cual. Lo pegado no
  se cree: al guardar pasa por el mismo saneador que todo lo demás.
-->
<dialog id="frmCode" class="card m-auto w-full max-w-2xl rounded-xl border border-line p-0 backdrop:bg-black/40">
    <div class="card-header justify-between">
        <h5 class="font-semibold"><?= lang('Frm.codeTitle') ?></h5>
        <button type="button" class="btn btn-ghost btn-sm" data-frm-code-close><i class="icon icon-x-lg"></i></button>
    </div>
    <div class="card-body space-y-3">
        <p class="text-xs text-muted"><?= lang('Frm.codeHint') ?></p>
        <textarea class="form-input font-mono text-xs" rows="12" data-frm-code-box spellcheck="false"></textarea>
        <p class="text-xs text-danger" data-frm-code-msg hidden></p>
    </div>
    <div class="card-footer flex justify-end gap-2">
        <button type="button" class="btn btn-secondary" data-frm-code-copy><i class="icon icon-arrow-repeat"></i> <?= lang('Frm.copy') ?></button>
        <button type="button" class="btn btn-primary" data-frm-code-apply><?= lang('Frm.apply') ?></button>
    </div>
</dialog>

<?php
/*
 * Los datos viajan en bloques `application/json` y no interpolados en el
 * JavaScript: así no hay forma de que una comilla en el título de una página
 * rompa el guion, ni de que un texto acabe ejecutándose.
 */
?>
<script type="application/json" id="frmRegistry"><?= json_encode($registry, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="frmSchema"><?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="frmSettings"><?= json_encode($settings, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="frmMeta"><?= json_encode([
    'id'        => (int) $form['id'],
    'title'     => (string) $form['title'],
    'slug'      => (string) $form['slug'],
    'status'    => (string) $form['status'],
    'publicUrl' => $publicUrl,
    'maxResponses'  => (int) $form['max_responses'],
    'allowMultiple' => (int) $form['allow_multiple'] === 1,
    'opensAt'   => (string) ($form['opens_at'] ?? ''),
    'closesAt'  => (string) ($form['closes_at'] ?? ''),
    'hasPassword' => !empty($form['password_hash']),
    'saveUrl'     => base_url('formularios/editor/' . $form['id']),
    'settingsUrl' => base_url('formularios/editor/' . $form['id'] . '/ajustes'),
    'tokenUrl'    => base_url('formularios/token'),
    'csrfName'    => csrf_token(),
    'csrfHash'    => csrf_hash(),
    'groups'      => $groupLabels,
    'operators'   => $operators,
    'widths'      => ['100' => lang('Frm.w100'), '50' => lang('Frm.w50'), '33' => lang('Frm.w33')],
], JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="frmLang"><?= json_encode([
    'saved' => lang('Frm.saved'), 'saving' => lang('Frm.saving'), 'unsaved' => lang('Frm.unsaved'),
    'saveFailed' => lang('Frm.saveFailed'), 'emptyCanvas' => lang('Frm.emptyCanvas'),
    'selectField' => lang('Frm.selectField'), 'addPage' => lang('Frm.addPage'),
    'pageTitle' => lang('Frm.pageTitle'), 'pageDesc' => lang('Frm.pageDesc'),
    'deletePage' => lang('Frm.deletePage'), 'confirmDeletePage' => lang('Frm.confirmDeletePage'),
    'duplicateField' => lang('Frm.duplicateField'), 'deleteField' => lang('Frm.deleteField'),
    'moveUp' => lang('Frm.moveUp'), 'moveDown' => lang('Frm.moveDown'),
    'addOption' => lang('Frm.addOption'), 'optionLabel' => lang('Frm.optionLabel'),
    'optionValue' => lang('Frm.optionValue'), 'removeOption' => lang('Frm.removeOption'),
    'tabGeneral' => lang('Frm.tabGeneral'), 'tabValidation' => lang('Frm.tabValidation'),
    'tabLayout' => lang('Frm.tabLayout'), 'tabLogic' => lang('Frm.tabLogic'),
    'logicEnable' => lang('Frm.logicEnable'), 'logicHint' => lang('Frm.logicHint'),
    'logicField' => lang('Frm.logicField'), 'logicOperator' => lang('Frm.logicOperator'),
    'logicValue' => lang('Frm.logicValue'), 'logicNoFields' => lang('Frm.logicNoFields'),
    'display' => lang('Frm.display'), 'displayCard' => lang('Frm.displayCard'),
    'displayFull' => lang('Frm.displayFull'), 'displayCardHint' => lang('Frm.displayCardHint'),
    'displayFullHint' => lang('Frm.displayFullHint'), 'flow' => lang('Frm.flow'),
    'flowWizard' => lang('Frm.flowWizard'), 'flowSingle' => lang('Frm.flowSingle'),
    'fTitle' => lang('Frm.fTitle'), 'fSlug' => lang('Frm.fSlug'), 'fDescription' => lang('Frm.fDescription'),
    'fSubmitLabel' => lang('Frm.fSubmitLabel'), 'fSuccessTitle' => lang('Frm.fSuccessTitle'),
    'fSuccessMessage' => lang('Frm.fSuccessMessage'), 'fRedirect' => lang('Frm.fRedirect'),
    'fNotifyEmail' => lang('Frm.fNotifyEmail'), 'fAckField' => lang('Frm.fAckField'),
    'fAckNone' => lang('Frm.fAckNone'), 'fPrimaryColor' => lang('Frm.fPrimaryColor'),
    'fProgressBar' => lang('Frm.fProgressBar'), 'fShowBrand' => lang('Frm.fShowBrand'),
    'fOpensAt' => lang('Frm.fOpensAt'), 'fClosesAt' => lang('Frm.fClosesAt'),
    'fMaxResponses' => lang('Frm.fMaxResponses'), 'fAllowMultiple' => lang('Frm.fAllowMultiple'),
    'fPassword' => lang('Frm.fPassword'), 'fPasswordHint' => lang('Frm.fPasswordHint'),
    'publicUrl' => lang('Frm.publicUrl'), 'save' => lang('Frm.save'), 'stepOf' => lang('Frm.stepOf'),
    'pageEmpty' => lang('Frm.pageEmpty'), 'selectOne' => lang('Frm.selectOne'),
    'notRated' => lang('Frm.notRated'), 'signHere' => lang('Frm.signHere'),
    'fileDrop' => lang('Frm.fileDrop'), 'needsPublish' => lang('Frm.needsPublish'),
    'allPages' => lang('Frm.allPages'), 'pageDuplicate' => lang('Frm.pageDuplicate'),
    'pageBefore' => lang('Frm.pageBefore'), 'pageAfter' => lang('Frm.pageAfter'),
    'pageCopy' => lang('Frm.pageCopy'), 'nFields' => lang('Frm.nFields'),
    'oneField' => lang('Frm.oneField'), 'noFields' => lang('Frm.noFields'),
    'nPages' => lang('Frm.nPages'), 'onePage' => lang('Frm.onePage'),
    'canvasTitle' => lang('Frm.canvasTitle'), 'canvasDesc' => lang('Frm.canvasDesc'),
    'fieldPage' => lang('Frm.fieldPage'), 'fieldPageNew' => lang('Frm.fieldPageNew'),
    'newPageName' => lang('Frm.newPageName'), 'widthFull' => lang('Frm.widthFull'),
    'widthHalf' => lang('Frm.widthHalf'), 'widthThird' => lang('Frm.widthThird'),
    'confirmClear' => lang('Frm.confirmClear'), 'codeApplied' => lang('Frm.codeApplied'),
    'codeBad' => lang('Frm.codeBad'), 'copied' => lang('Frm.copied'),
], JSON_UNESCAPED_UNICODE) ?></script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('formulario/recurso/editor.js') ?>"></script>
<?= $this->endSection() ?>
