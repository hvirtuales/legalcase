<?= $this->extend('Components\Paginas\Views\_editor_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * Editor drag & drop, **a pantalla completa**.
 *
 * Usa el lienzo propio del componente (`_editor_layout`) y no `layouts/admin`:
 * con la barra lateral y el encabezado del panel, las tres columnas del editor
 * dejaban para la página una tira demasiado estrecha para juzgar nada. Aquí el
 * ancho es el material de trabajo. La salida está arriba a la izquierda.
 *
 * Tres paneles: **paleta** (tipos de bloque y bloques guardados), **lienzo**
 * (el árbol, donde se suelta y se reordena) e **inspector** (los campos del
 * bloque seleccionado, generados desde `BlockRegistry`).
 *
 * El JS y el CSS del editor se sirven por `Asset` (`/pagina/recurso/…`) en vez de
 * ir incrustados: son estáticos, se cachean y así el editor se puede depurar
 * como un archivo normal. Es también lo que recomienda el informe de calidad
 * para las pantallas internas.
 *
 * El lienzo incrusta la **misma hoja de estilos que la página publicada**
 * (`public/_styles.php`), así lo que se ve mientras se arma es lo que se
 * publica. La aproximación que dibuja el JS es una ayuda de edición: la salida
 * de verdad la produce `Libraries\Renderer` en el servidor, y por eso «Vista
 * previa» abre la página renderizada.
 *
 * @var array  $page
 * @var array  $tree      árbol actual
 * @var array  $registry  catálogo traducido para el navegador
 * @var array  $groups    orden de los grupos de la paleta
 * @var array  $saved     bloques personalizados de la empresa
 * @var bool   $canSaveBlock
 */
// Nota: `$groups` ya no se usa para pintar rótulos —los elementos van sin
// agrupar—, solo para ORDENAR la rejilla por afinidad.

// Textos que necesita el JS. Van en un bloque JSON, no interpolados en el
// código: así el editor no tiene ni una cadena visible incrustada (§7.2).
$i18n = [
    'empty'          => lang('Pag.canvasEmpty'),
    'emptyHint'      => lang('Pag.canvasEmptyHint'),
    'dropHere'       => lang('Pag.dropHere'),
    'column'         => lang('Pag.bColumn'),
    'noSelection'    => lang('Pag.noSelection'),
    'noSelectionHint' => lang('Pag.noSelectionHint'),
    'moveUp'         => lang('Pag.moveUp'),
    'moveDown'       => lang('Pag.moveDown'),
    'duplicate'      => lang('Pag.duplicate'),
    'remove'         => lang('Pag.remove'),
    'saveAsBlock'    => lang('Pag.saveAsBlock'),
    'saveBlockName'  => lang('Pag.saveBlockName'),
    'confirmRemove'  => lang('Pag.confirmRemove'),
    'saved'          => lang('Pag.saved'),
    'saving'         => lang('Pag.saving'),
    'unsaved'        => lang('Pag.unsaved'),
    'saveError'      => lang('Pag.errSaveFailed'),
    'needsTitle'     => lang('Pag.needsTitle'),
    'uploading'      => lang('Pag.uploading'),
    'uploadError'    => lang('Pag.errUpload'),
    'chooseImage'    => lang('Pag.chooseImage'),
    'removeImage'    => lang('Pag.removeImage'),
    'addItem'        => lang('Pag.addItem'),
    'removeItem'     => lang('Pag.removeItem'),
    'blockSaved'     => lang('Pag.okBlockSaved'),
    'bold'           => lang('Pag.rtBold'),
    'italic'         => lang('Pag.rtItalic'),
    'underline'      => lang('Pag.rtUnderline'),
    'bullets'        => lang('Pag.rtBullets'),
    'numbers'        => lang('Pag.rtNumbers'),
    'link'           => lang('Pag.rtLink'),
    'linkPrompt'     => lang('Pag.rtLinkPrompt'),
    'clearFormat'    => lang('Pag.rtClear'),
    'emptyBlock'     => lang('Pag.emptyBlock'),
    'qrHere'         => lang('Pag.qrInPreview'),
    'noCompanyData'  => lang('Pag.noCompanyDataShort'),
    'taxId'          => lang('Pag.taxIdLabel'),
    // Vista en cascada
    'treeEmpty'      => lang('Pag.treeEmpty'),
    'collapse'       => lang('Pag.treeCollapse'),
    'expand'         => lang('Pag.treeExpand'),
    'outlineTitle'   => lang('Pag.outlineTitle'),
    'outlineEmpty'   => lang('Pag.outlineEmpty'),
    'outlineSkip'    => lang('Pag.outlineSkip'),
    'outlineOk'      => lang('Pag.outlineOk'),
    'blocksInside'   => lang('Pag.treeChildren'),
    // Paneles plegables del inspector (los campos comunes a todo bloque).
    'panel_spacing'  => lang('Pag.panelSpacing'),
    'panel_advanced' => lang('Pag.panelAdvanced'),
    'pageSaved'      => lang('Pag.okPageSettings'),
    'noColor'        => lang('Pag.noColor'),
    'opacity'        => lang('Pag.opacity'),
    // Código de la página
    'codeCopied'     => lang('Pag.codeCopied'),
    'codeCopyManual' => lang('Pag.codeCopyManual'),
    'codeBadJson'    => lang('Pag.codeBadJson'),
    'codeBadShape'   => lang('Pag.codeBadShape'),
    'codeEmpty'      => lang('Pag.codeEmpty'),
    'codeConfirm'    => lang('Pag.codeConfirm'),
];
?>

<!-- Barra superior: salida a la izquierda, acciones a la derecha. Es lo único
     que envuelve al editor; todo lo demás del alto es lienzo. -->
<header class="pag-topbar">
    <a href="<?= base_url('paginas') ?>" class="pag-back" title="<?= esc(lang('Pag.backToPages')) ?>">
        <i class="icon icon-arrow-left"></i>
        <span><?= lang('Pag.pages') ?></span>
    </a>

    <div class="pag-topinfo">
        <i class="icon icon-puzzle"></i>
        <h1 class="pag-toptitle"><?= esc($page['title']) ?></h1>
        <span id="pagStatus" class="badge badge-light"><?= lang('Pag.saved') ?></span>
    </div>

    <div class="pag-topactions">
        <!-- Mostrar/ocultar las columnas laterales. Van aquí y no solo dentro de
             cada panel porque, una vez oculto, el panel ya no tiene dónde poner
             su propio botón: la vuelta tiene que estar siempre a la vista. -->
        <button type="button" id="pagToggleLeft" class="btn btn-ghost btn-sm is-on"
            title="<?= esc(lang('Pag.togglePalette')) ?>" aria-pressed="true">
            <i class="icon icon-list-columns-reverse"></i>
        </button>
        <button type="button" id="pagToggleRight" class="btn btn-ghost btn-sm is-on"
            title="<?= esc(lang('Pag.toggleInspector')) ?>" aria-pressed="true">
            <i class="icon icon-gear"></i>
        </button>
        <span class="pag-topsep" aria-hidden="true"></span>

        <!-- Vista previa a tamaño de dispositivo.
             Abre la página YA RENDERIZADA por el servidor en una ventana del
             ancho elegido. Se hace así, y no encogiendo el lienzo, porque las
             consultas de medios (`@media`) miran el ancho de la VENTANA, no el
             del elemento: estrechar el lienzo cambiaría su tamaño sin disparar
             ni una regla responsive, y enseñaría una mentira. -->
        <div class="pag-devices" role="group" aria-label="<?= esc(lang('Pag.previewDevice')) ?>">
            <button type="button" class="btn btn-ghost btn-sm pag-device"
                data-w="1440" data-h="900" title="<?= esc(lang('Pag.deviceDesktop')) ?>">
                <i class="icon icon-hdd-network"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm pag-device"
                data-w="820" data-h="1100" title="<?= esc(lang('Pag.deviceTablet')) ?>">
                <i class="icon icon-journal-text"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-sm pag-device"
                data-w="390" data-h="844" title="<?= esc(lang('Pag.deviceMobile')) ?>">
                <i class="icon icon-telephone"></i>
            </button>
        </div>

        <a href="<?= base_url('paginas/previsualizar/' . (int) $page['id']) ?>" target="_blank" rel="noopener"
            id="pagPreview" class="btn btn-ghost btn-sm">
            <i class="icon icon-eye"></i> <span class="pag-hidesm"><?= lang('Pag.preview') ?></span>
        </a>
        <!-- El código de la página: para copiarla entera y pegarla en otra. -->
        <button type="button" id="pagCode" class="btn btn-ghost btn-sm" title="<?= esc(lang('Pag.codeTitle')) ?>">
            <i class="icon icon-cpu"></i> <span class="pag-hidesm"><?= lang('Pag.code') ?></span>
        </button>
        <!-- Ya no sale del editor: abre la pestaña «Página» del inspector, que
             es donde viven esos campos desde que se retiró `ajustes/{id}`. -->
        <button type="button" id="pagOpenSettings" class="btn btn-secondary btn-sm">
            <i class="icon icon-gear"></i> <span class="pag-hidesm"><?= lang('Pag.pageSettings') ?></span>
        </button>
        <button type="button" id="pagSave" class="btn btn-primary btn-sm">
            <i class="icon icon-save"></i> <?= lang('Pag.save') ?>
        </button>
    </div>
</header>

<main class="pag-stage">
        <div id="pagEditor" class="pag-editor"
            data-save-url="<?= esc(base_url('paginas/editor/' . (int) $page['id'])) ?>"
            data-upload-url="<?= esc($uploadUrl) ?>"
            data-block-url="<?= esc($blockUrl) ?>"
            data-token-url="<?= esc(base_url('paginas/token')) ?>"
            data-library-url="<?= esc(base_url('paginas/medios/lista')) ?>"
            data-csrf-name="<?= esc(csrf_token()) ?>"
            data-csrf-hash="<?= esc(csrf_hash()) ?>"
            data-can-save-block="<?= $canSaveBlock ? '1' : '0' ?>">

            <!-- Columna izquierda: paleta y estructura, en pestañas -->
            <aside class="pag-side">
                <!-- Pestañas sin icono: tres palabras cortas se leen antes que
                     tres siluetas, y el icono solo les quitaba ancho. -->
                <div class="pag-tabs" role="tablist">
                    <button type="button" class="pag-tab is-active" data-tab="elements" role="tab" aria-selected="true">
                        <?= lang('Pag.tabElements') ?>
                    </button>
                    <button type="button" class="pag-tab" data-tab="blocks" role="tab" aria-selected="false">
                        <?= lang('Pag.tabBlocks') ?>
                    </button>
                    <button type="button" class="pag-tab" data-tab="tree" role="tab" aria-selected="false">
                        <?= lang('Pag.tabTree') ?>
                    </button>
                    <button type="button" class="pag-close" data-close="left"
                        title="<?= esc(lang('Pag.hidePanel')) ?>" aria-label="<?= esc(lang('Pag.hidePanel')) ?>">
                        <i class="icon icon-x-lg"></i>
                    </button>
                </div>

            <!-- ELEMENTOS: los tipos de bloque sueltos, en una sola rejilla sin
                 agrupar. Icono arriba y nombre debajo: con veinte tipos se
                 reconocen por la silueta, no leyendo veinte etiquetas. El orden
                 sigue siendo el de `BlockRegistry::GROUPS` (estructura →
                 contenido → empresa → acción → avanzado), que agrupa por
                 afinidad aunque no se pinten los rótulos. -->
            <div class="pag-panel is-active" data-panel="elements">
                <div class="pag-plist pag-plist-grid">
                    <?php foreach ($groups as $group): ?>
                        <?php // `hidden` = el tipo existe y se edita, pero ya no se ofrece
                              // (el botón suelto, sustituido por «Botones»). ?>
                        <?php foreach (array_filter($registry, static fn ($d) => ($d['group'] ?? null) === $group && empty($d['hidden'])) as $type => $def): ?>
                            <button type="button" class="pag-pitem" draggable="true"
                                data-add-type="<?= esc($type) ?>"
                                title="<?= esc($def['hint'] ?: $def['label']) ?>">
                                <i class="icon <?= esc($def['icon']) ?>"></i>
                                <span><?= esc($def['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- BLOQUES: lo que ya viene armado — secciones prearmadas y lo que
                 haya guardado el usuario. Los elementos sueltos ya no están
                 aquí: viven en su propia pestaña. -->
            <div class="pag-panel" data-panel="blocks">
                <p class="pag-treehelp"><?= lang('Pag.blocksHelp') ?></p>

                <!-- Secciones prearmadas: se sueltan ya configuradas. -->
                <?php foreach ($sectionGroups as $sg): ?>
                    <?php
                    $secs = array_values(array_filter($sections, static fn ($s) => ($s['group'] ?? '') === $sg));
                    if (!$secs) {
                        continue;
                    }
                    ?>
                    <details class="pag-pgroup pag-pgroup-sec" <?= $sg === 'banner' ? 'open' : '' ?>>
                        <summary><?= esc(lang('Pag.secGroup' . ucfirst($sg))) ?></summary>
                        <div class="pag-plist">
                            <?php foreach ($secs as $sec): ?>
                                <button type="button" class="pag-pitem" draggable="true"
                                    data-add-section="<?= esc($sec['code']) ?>"
                                    title="<?= esc($sec['hint'] ?: $sec['label']) ?>">
                                    <i class="icon <?= esc($sec['icon']) ?>"></i>
                                    <span><?= esc($sec['label']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

                <details class="pag-pgroup" <?= $saved ? 'open' : '' ?>>
                    <summary><?= lang('Pag.groupSaved') ?></summary>
                    <div class="pag-plist">
                        <?php if (!$saved): ?>
                            <p class="pag-pempty"><?= lang('Pag.noSavedBlocks') ?></p>
                        <?php else: ?>
                            <?php foreach ($saved as $b): ?>
                                <button type="button" class="pag-pitem" draggable="true"
                                    data-add-saved="<?= (int) $b['id'] ?>">
                                    <i class="icon icon-puzzle"></i>
                                    <span><?= esc($b['name']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            </div><!-- /panel bloques -->

            <!-- Estructura: el árbol de la página en cascada -->
            <div class="pag-panel" data-panel="tree">
                <p class="pag-treehelp"><?= lang('Pag.treeHelp') ?></p>
                <div id="pagTreeView" class="pag-tree"></div>
                <div id="pagOutline" class="pag-outline"></div>
            </div>
            </aside>

            <!-- Lienzo -->
            <div class="pag-canvas-wrap">
                <div id="pagCanvas" class="pag-canvas pg-root" data-pg-theme="light"></div>
            </div>

            <!-- Inspector: propiedades del bloque · ajustes de la página -->
            <aside class="pag-inspector">
                <div class="pag-insphead">
                    <div class="pag-itabs" role="tablist">
                        <button type="button" class="pag-itab is-active" data-itab="block" role="tab" aria-selected="true">
                            <?= lang('Pag.properties') ?>
                        </button>
                        <button type="button" class="pag-itab" data-itab="page" role="tab" aria-selected="false">
                            <?= lang('Pag.tabPage') ?>
                        </button>
                    </div>
                    <button type="button" class="pag-close" data-close="right"
                        title="<?= esc(lang('Pag.hidePanel')) ?>" aria-label="<?= esc(lang('Pag.hidePanel')) ?>">
                        <i class="icon icon-x-lg"></i>
                    </button>
                </div>

                <div class="pag-ipane is-active" data-ipane="block">
                    <div id="pagInspector" class="pag-inspbody"></div>
                </div>

                <!-- Ajustes de la página, aquí mismo. Son los de la pantalla
                     `ajustes/{id}`: se guardan por su propia ruta JSON y no
                     obligan a abandonar lo que se está montando. -->
                <div class="pag-ipane" data-ipane="page">
                    <form id="pagPageForm" class="pag-inspbody"
                        data-url="<?= esc(base_url('paginas/editor/' . (int) $page['id'] . '/ajustes')) ?>">

                        <div class="pag-ifield">
                            <label for="pfTitle"><?= lang('Pag.fPageTitle') ?> <span class="text-danger">*</span></label>
                            <input type="text" id="pfTitle" name="title" class="form-input" required maxlength="180"
                                value="<?= esc($page['title']) ?>">
                        </div>

                        <div class="pag-ifield">
                            <label for="pfSlug"><?= lang('Pag.fSlug') ?></label>
                            <input type="text" id="pfSlug" name="slug" class="form-input" maxlength="180"
                                value="<?= esc($page['slug']) ?>">
                            <p class="pag-ihint"><?= lang('Pag.fSlugHint') ?></p>
                            <?php if (($companySlug ?? '') !== ''): ?>
                                <?php // La dirección que se comparte. La enseñaba la pantalla de
                                      // ajustes, que se retiró; sin esto habría que componerla a mano.
                                $publica = base_url('pagina/' . rawurlencode((string) $companySlug) . '/' . rawurlencode((string) $page['slug'])); ?>
                                <p class="pag-ihint">
                                    <a id="pfPublicUrl" href="<?= esc($publica) ?>" target="_blank" rel="noopener"
                                        data-base="<?= esc(base_url('pagina/' . rawurlencode((string) $companySlug)) . '/') ?>">
                                        <?= esc($publica) ?> <i class="icon icon-box-arrow-up-right"></i>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>

                        <details class="pag-ipanel">
                            <summary><?= lang('Pag.secSeo') ?></summary>
                            <div class="pag-ifield">
                                <label for="pfMetaTitle"><?= lang('Pag.fMetaTitle') ?></label>
                                <input type="text" id="pfMetaTitle" name="meta_title" class="form-input" maxlength="180"
                                    value="<?= esc($page['meta_title']) ?>">
                            </div>
                            <div class="pag-ifield">
                                <label for="pfMetaDesc"><?= lang('Pag.fMetaDesc') ?></label>
                                <textarea id="pfMetaDesc" name="meta_description" class="form-textarea" rows="3"
                                    maxlength="255"><?= esc($page['meta_description']) ?></textarea>
                            </div>
                            <div class="pag-ifield">
                                <label><?= lang('Pag.fOgImage') ?></label>
                                <div class="pag-img">
                                    <img id="pfOgPreview" alt=""
                                        <?= !empty($page['og_image']) ? 'src="' . esc(media_url($page['og_image'])) . '"' : 'style="display:none"' ?>>
                                    <div class="pag-imgbtns">
                                        <button type="button" id="pfOgPick" class="btn btn-secondary btn-sm">
                                            <i class="icon icon-upload"></i> <?= lang('Pag.chooseImage') ?>
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="og_image" id="pfOgImage" value="">
                                <p class="pag-ihint"><?= lang('Pag.fOgImageHint') ?></p>
                            </div>
                        </details>

                        <?php
                        $set   = is_array($page['settings'] ?? null) ? $page['settings'] : [];
                        $viejo = ['narrow' => 720, 'boxed' => 1100, 'wide' => 1340];
                        $base  = $viejo[$set['width'] ?? 'boxed'] ?? 1100;
                        ?>
                        <details class="pag-ipanel" open>
                            <summary><?= lang('Pag.secLook') ?></summary>

                            <div class="pag-ifield">
                                <label for="pfAccent"><?= lang('Pag.fAccent') ?></label>
                                <input type="color" id="pfAccent" name="accent" class="form-input"
                                    value="<?= esc($set['accent'] ?? '' ?: '#2563eb') ?>">
                                <p class="pag-ihint"><?= lang('Pag.fAccentHint') ?></p>
                            </div>

                            <div class="pag-ifield">
                                <label for="pfContentWidth"><?= lang('Pag.fContentWidth') ?></label>
                                <input type="number" id="pfContentWidth" name="contentWidth" class="form-input"
                                    min="360" max="1600" step="10"
                                    value="<?= esc((int) ($set['contentWidth'] ?? 0) ?: $base) ?>">
                            </div>

                            <div class="pag-ifield">
                                <label for="pfWideWidth"><?= lang('Pag.fWideWidth') ?></label>
                                <input type="number" id="pfWideWidth" name="wideWidth" class="form-input"
                                    min="360" max="1920" step="10"
                                    value="<?= esc((int) ($set['wideWidth'] ?? 0) ?: (int) round($base * 1.25)) ?>">
                                <p class="pag-ihint"><?= lang('Pag.fWideWidthHint') ?></p>
                            </div>

                            <div class="pag-ifield">
                                <div class="form-check">
                                    <input type="checkbox" id="pfHeader" name="header" value="1" class="form-check-input"
                                        <?= ($set['header'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pfHeader"><?= lang('Pag.fShowHeader') ?></label>
                                </div>
                                <div class="form-check mt-2">
                                    <input type="checkbox" id="pfFooter" name="footer" value="1" class="form-check-input"
                                        <?= ($set['footer'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pfFooter"><?= lang('Pag.fShowFooter') ?></label>
                                </div>
                            </div>
                        </details>

                        <!-- Sin botón propio: el «Guardar» de la barra guarda
                             TODO (ajustes y bloques). Tener dos era la causa de
                             que el título no se guardara con el de arriba y de
                             que hubiera que guardar dos veces. El formulario
                             sigue enviándose con Intro dentro de un campo. -->
                        <div class="pag-pagesave">
                            <p class="pag-ihint"><?= lang('Pag.settingsSaveHint') ?></p>
                            <p id="pfMsg" class="pag-ihint"></p>
                        </div>
                    </form>
                </div>
            </aside>
        </div>

        <!-- ============================================================
             CÓDIGO DE LA PÁGINA
             La página no es HTML guardado: es un árbol de bloques. Eso la
             hace **portable de verdad** — este cuadro la enseña tal cual, se
             copia y se pega en otra página, que queda igual.

             Lo que se pega NO se cree: al guardar pasa por el mismo
             `PageService::sanitizeTree()` que todo lo demás, que reconstruye
             el árbol campo por campo contra el registro. Un tipo inventado o
             un `href="javascript:"` pegados aquí se quedan fuera igual.
             ============================================================ -->
        <div id="pagCodeBox" class="pag-modal" hidden>
            <div class="pag-modal-back" data-close-code></div>
            <div class="pag-modal-box" role="dialog" aria-modal="true" aria-labelledby="pagCodeTitle">
                <div class="pag-modal-head">
                    <h2 id="pagCodeTitle"><i class="icon icon-cpu"></i> <?= lang('Pag.codeTitle') ?></h2>
                    <button type="button" class="pag-close" data-close-code
                        aria-label="<?= esc(lang('Pag.close')) ?>"><i class="icon icon-x-lg"></i></button>
                </div>
                <div class="pag-modal-body">
                    <p class="pag-ihint"><?= lang('Pag.codeHelp') ?></p>
                    <textarea id="pagCodeText" class="form-textarea pag-code" spellcheck="false"
                        aria-label="<?= esc(lang('Pag.codeTitle')) ?>"></textarea>
                    <p id="pagCodeMsg" class="pag-ihint"></p>
                </div>
                <div class="pag-modal-foot">
                    <button type="button" id="pagCodeCopy" class="btn btn-secondary btn-sm">
                        <i class="icon icon-clipboard"></i> <?= lang('Pag.codeCopy') ?>
                    </button>
                    <button type="button" id="pagCodeApply" class="btn btn-primary btn-sm">
                        <i class="icon icon-check-circle"></i> <?= lang('Pag.codeApply') ?>
                    </button>
                </div>
            </div>
        </div>
</main>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('pagina/recurso/editor.css') ?>">
<style>
/* La misma hoja que la página publicada, acotada al lienzo: lo que se ve
   mientras se maqueta es lo que se publica. */
<?= $this->include('Components\Paginas\Views\public\_styles') ?>
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="application/json" id="pagTree"><?= json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="pagRegistry"><?= json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="pagSaved"><?= json_encode(array_map(static fn ($b) => [
    'id'     => (int) $b['id'],
    'name'   => (string) $b['name'],
    'blocks' => is_array($b['blocks'] ?? null) ? $b['blocks'] : [],
], $saved), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<!-- Secciones prearmadas (código, no BD): banner, tarjetas, contenido y pie. -->
<script type="application/json" id="pagSections"><?= json_encode(array_map(static fn ($s) => [
    'code'   => $s['code'],
    'blocks' => $s['blocks'],
], $sections), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<!-- Iconos SVG en línea, los MISMOS que se publican (`Renderer::iconSet()`).
     El lienzo llevaba su propia copia dibujada a mano y no coincidía: el de
     WhatsApp era solo el bocadillo, sin el auricular dentro, y las redes eran
     la inicial en negrita. -->
<script type="application/json" id="pagIcons"><?= json_encode(\Components\Paginas\Libraries\Renderer::iconSet(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="pagI18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<!-- Datos de la empresa (ya recortados y con las imágenes resueltas) para que
     los bloques de empresa se dibujen con contenido real en el lienzo. -->
<script type="application/json" id="pagCompany"><?= json_encode($company ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script src="<?= base_url('pagina/recurso/editor.js') ?>" defer></script>
<?= $this->endSection() ?>
