<?= $this->extend('Components\Mapas\Views\_editor_layout') ?>

<?= $this->section('content') ?>
<div class="map-shell" data-map-editor>

    <header class="map-bar">
        <a href="<?= base_url('mapas') ?>" class="btn btn-ghost btn-sm" title="<?= esc(lang('Map.maps'), 'attr') ?>">
            <i class="icon icon-arrow-left"></i> <span class="hidden sm:inline"><?= lang('Map.maps') ?></span>
        </a>
        <div class="map-bar-title"><strong><?= esc($map['name']) ?></strong></div>

        <div class="map-bar-actions">
            <select class="form-select py-1.5 text-xs" data-map-filter aria-label="<?= esc(lang('Map.filterByCategory'), 'attr') ?>">
                <option value=""><?= lang('Map.allCategories') ?></option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="<?= base_url('mapas/categorias') ?>" class="btn btn-ghost btn-sm">
                <i class="icon icon-palette"></i> <span class="hidden md:inline"><?= lang('Map.categories') ?></span>
            </a>
            <!-- Publicar es un POST, no un enlace: cambia el estado del mapa.
                 El enlace público sale al lado en cuanto existe. -->
            <?php if ($link !== ''): ?>
                <a href="<?= esc($link, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                    <i class="icon icon-box-arrow-up-right"></i> <span class="hidden md:inline"><?= lang('Map.openPublic') ?></span>
                </a>
            <?php endif; ?>
            <form method="post" action="<?= base_url('mapas/publicar/' . $map['id']) ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="icon icon-<?= $map['is_published'] ? 'eye-slash' : 'send' ?>"></i>
                    <span class="hidden md:inline"><?= $map['is_published'] ? lang('Map.unpublish') : lang('Map.publish') ?></span>
                </button>
            </form>
        </div>
    </header>

    <div class="map-cols">
        <!--
          Izquierda: DÓNDE se está trabajando. Los pisos y la lista de lo que hay
          en el que está abierto. Son dos vistas de lo mismo —la estructura del
          mapa— y por eso comparten columna en vez de repartirse por la pantalla.
        -->
        <aside class="map-side">
            <div class="map-tabs" data-map-tabs="izq" role="tablist">
                <button type="button" class="map-tab is-on" data-map-tab="levels" role="tab" aria-selected="true"><?= lang('Map.levels') ?></button>
                <button type="button" class="map-tab" data-map-tab="items" role="tab" aria-selected="false"><span data-map-points-title></span></button>
            </div>

            <div class="map-side-body" data-map-pane="levels">
                <button type="button" class="btn btn-secondary btn-sm w-full mb-2" data-map-newlevel>
                    + <?= lang('Map.levelNew') ?>
                </button>
                <div data-map-levels></div>
            </div>

            <div class="map-side-body" data-map-pane="items" hidden>
                <div data-map-points></div>
            </div>
        </aside>

        <main class="map-canvas">
            <!--
              Los elementos, encima del lienzo y no en la barra de la página:
              se eligen mirando el plano y se usan sobre él, así que es donde la
              mano ya está. Cada uno es un alternador; volver a pulsarlo sale.
              «Pin» conserva `data-map-add` porque la clase `.map-tool.is-on` y
              las pruebas ya lo señalan por ese nombre.
            -->
            <div class="map-tools-top" data-map-tools role="toolbar" aria-label="<?= esc(lang('Map.elements'), 'attr') ?>">
                <?php
                // El icono es un SVG en línea y no un `icon-*`: el juego del
                // núcleo no tiene ni polilínea ni polígono, y un `icon-` que no
                // existe no da error — simplemente no pinta nada.
                $herramientas = [
                    ['pin', 'toolPin', '<path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11Z"/><circle cx="12" cy="10" r="2.4"/>', 'data-map-add'],
                    ['rect', 'toolRect', '<rect x="4" y="6" width="16" height="12" rx="1"/>', ''],
                    ['circle', 'toolCircle', '<circle cx="12" cy="12" r="7.5"/>', ''],
                    ['polygon', 'toolPolygon', '<path d="M12 4l7.5 5.5-2.9 8.5H7.4L4.5 9.5z"/>', ''],
                    ['line', 'toolLine', '<path d="M4 17l5-8 4 4 7-9"/>', ''],
                    ['text', 'toolText', '<path d="M5 6h14M12 6v12M9 18h6"/>', ''],
                ];
                foreach ($herramientas as [$kind, $clave, $svg, $extra]): ?>
                    <button type="button" class="map-tool" data-map-tool="<?= esc($kind, 'attr') ?>" <?= esc($extra, 'attr') ?>
                            title="<?= esc(lang('Map.' . $clave), 'attr') ?>"
                            aria-label="<?= esc(lang('Map.' . $clave), 'attr') ?>" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $svg ?></svg>
                        <span><?= lang('Map.' . $clave) ?></span>
                    </button>
                <?php endforeach; ?>

                <!-- Solo mientras se traza: cerrar la forma o dejarlo. -->
                <span class="map-tools-sep" data-map-draw-actions hidden>
                    <button type="button" class="btn btn-primary btn-sm" data-map-finish><?= lang('Map.shapeFinish') ?></button>
                    <button type="button" class="btn btn-ghost btn-sm" data-map-cancel><?= lang('Map.shapeCancel') ?></button>
                </span>
            </div>

            <!-- El visor se monta aquí dentro; los controles van encima. -->
            <div class="mv-stage-host" data-map-stage style="width:100%;height:100%"></div>
            <div class="mv-tools">
                <button type="button" data-map-in title="<?= esc(lang('Map.zoomIn'), 'attr') ?>">+</button>
                <button type="button" data-map-out title="<?= esc(lang('Map.zoomOut'), 'attr') ?>">−</button>
                <button type="button" data-map-fit title="<?= esc(lang('Map.fit'), 'attr') ?>">⤢</button>
            </div>
        </main>

        <!-- La columna derecha: los puntos del nivel, con lo que los define.
             Colocarlos en el plano dice DÓNDE están; esta lista dice QUÉ son, y
             es la única forma de repasarlos sin ir pinchando pin por pin. -->
        <!--
          Derecha: CÓMO se ve. Las propiedades de lo que está elegido y los datos
          del mapa entero. Elegir un elemento salta solo a su pestaña: si no, se
          pulsa algo en el plano y no pasa nada visible en esta columna.
        -->
        <aside class="map-side map-right">
            <div class="map-tabs" data-map-tabs="der" role="tablist">
                <button type="button" class="map-tab is-on" data-map-tab="props" role="tab" aria-selected="true"><?= lang('Map.props') ?></button>
                <button type="button" class="map-tab" data-map-tab="mapcfg" role="tab" aria-selected="false"><?= lang('Map.mapSettings') ?></button>
            </div>

            <div class="map-side-body" data-map-pane="props">
                <div class="map-props" data-map-props hidden></div>
                <p class="map-empty" data-map-props-empty><?= lang('Map.propNone') ?></p>
            </div>

            <div class="map-side-body" data-map-pane="mapcfg" hidden>
                <form data-map-settings>
                    <div class="form-group">
                        <label class="form-label" for="mapname"><?= lang('Map.name') ?></label>
                        <input type="text" name="name" id="mapname" class="form-input" required
                               value="<?= esc($map['name'], 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mapdesc"><?= lang('Map.description') ?></label>
                        <textarea name="description" id="mapdesc" class="form-input" rows="3"><?= esc($map['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="maplogo"><?= lang('Map.mapLogo') ?></label>
                        <?php if (!empty($map['logo'])): ?>
                            <div class="map-cfg-logo">
                                <img src="<?= esc(base_url(ltrim($map['logo'], '/')), 'attr') ?>" alt="">
                                <label class="map-prop-check">
                                    <input type="checkbox" name="logo_clear" value="1">
                                    <span><?= lang('Map.mapLogoClear') ?></span>
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" id="maplogo" class="form-input" accept="image/*">
                        <p class="map-prop-hint"><?= lang('Map.mapLogoHint') ?></p>
                    </div>

                    <div class="form-group">
                        <label class="map-prop-check">
                            <input type="checkbox" name="show_header" value="1"
                                   <?= (!isset($map['show_header']) || $map['show_header']) ? 'checked' : '' ?>>
                            <span><?= lang('Map.showHeader') ?></span>
                        </label>
                        <p class="map-prop-hint"><?= lang('Map.showHeaderHint') ?></p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-full"><?= lang('Map.save') ?></button>
                </form>

                <div class="map-cfg-block">
                    <span class="map-cfg-lab"><?= lang('Map.status') ?></span>
                    <span class="badge <?= $map['is_published'] ? 'badge-success' : 'badge-secondary' ?>">
                        <?= $map['is_published'] ? lang('Map.published') : lang('Map.draft') ?>
                    </span>
                </div>

                <?php if ($link !== ''): ?>
                    <div class="map-cfg-block">
                        <span class="map-cfg-lab"><?= lang('Map.publicLink') ?></span>
                        <input type="text" class="form-input" readonly value="<?= esc($link, 'attr') ?>"
                               data-map-link onclick="this.select()">
                    </div>
                <?php endif; ?>

                <a href="<?= base_url('mapas/categorias') ?>" class="btn btn-ghost btn-sm w-full">
                    <i class="icon icon-palette"></i> <?= lang('Map.categories') ?>
                </a>
            </div>
        </aside>
    </div>
</div>

<div class="map-flash" data-map-flash hidden></div>

<!-- Diálogo del nivel -->
<dialog class="map-dialog" data-map-level-dialog>
    <form method="dialog" onsubmit="return false">
        <div class="map-dialog-head" data-level-title></div>
        <div class="map-dialog-body">
            <div class="form-group mb-0">
                <label class="form-label" for="lvname"><?= lang('Map.levelName') ?></label>
                <input type="text" name="name" id="lvname" class="form-input" required
                       placeholder="<?= esc(lang('Map.levelNamePh'), 'attr') ?>">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="lvimg"><?= lang('Map.levelPlan') ?></label>
                <input type="file" name="image" id="lvimg" class="form-input" accept="image/*">
                <p class="mt-1 text-xs text-muted"><?= lang('Map.levelPlanHint', [\Components\Mapas\Libraries\PlanImage::MAX_WIDTH]) ?></p>
            </div>
        </div>
        <div class="map-dialog-foot">
            <button type="button" class="btn btn-secondary" onclick="this.closest('dialog').close()"><?= lang('Map.cancel') ?></button>
            <button type="submit" class="btn btn-primary"><?= lang('Map.save') ?></button>
        </div>
    </form>
</dialog>

<!-- Diálogo del punto -->
<dialog class="map-dialog" data-map-point-dialog>
    <form method="dialog" onsubmit="return false">
        <div class="map-dialog-head" data-point-title></div>
        <div class="map-dialog-body">
            <div class="form-group mb-0">
                <label class="form-label" for="ptitle"><?= lang('Map.pointTitle') ?></label>
                <input type="text" name="title" id="ptitle" class="form-input" required>
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="pdesc"><?= lang('Map.pointDesc') ?></label>
                <textarea name="description" id="pdesc" class="form-input" rows="3"></textarea>
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="pcat"><?= lang('Map.category') ?></label>
                <select name="category_id" id="pcat" class="form-select">
                    <option value=""><?= lang('Map.noCategory') ?></option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!--
                De quién cuelga. Un sub-elemento no se dibuja con los demás: sale
                de la ficha de su padre, como botón. Es lo que permite que un
                local ofrezca su «cómo llegar» sin que cuarenta rutas dibujadas a
                la vez conviertan el plano en una maraña.
            -->
            <div class="form-group mb-0">
                <label class="form-label" for="pparent"><?= lang('Map.pointParent') ?></label>
                <select name="parent_id" id="pparent" class="form-select" data-point-parent>
                    <option value=""><?= lang('Map.pointParentNone') ?></option>
                </select>
                <p class="mt-1 text-xs text-muted" data-point-parent-hint><?= lang('Map.pointParentHint') ?></p>
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="purl"><?= lang('Map.pointUrl') ?></label>
                <input type="url" name="url" id="purl" class="form-input" placeholder="https://…">
                <p class="mt-1 text-xs text-muted"><?= lang('Map.pointUrlHint') ?></p>
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="pimg"><?= lang('Map.pointImage') ?></label>
                <input type="file" name="image" id="pimg" class="form-input" accept="image/*">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" checked>
                <?= lang('Map.pointActive') ?>
            </label>
        </div>
        <div class="map-dialog-foot">
            <button type="button" class="btn btn-danger is-left" data-point-delete hidden><?= lang('Map.delete') ?></button>
            <button type="button" class="btn btn-secondary" onclick="this.closest('dialog').close()"><?= lang('Map.cancel') ?></button>
            <button type="submit" class="btn btn-primary"><?= lang('Map.save') ?></button>
        </div>
    </form>
</dialog>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php
/**
 * Lo que el editor necesita saber, en bloques JSON.
 *
 * Las URL van con `__ID__` como marcador y NO se arman en el JavaScript: así
 * las rutas siguen viviendo en `Config/Routes.php` y cambiarlas ahí no obliga a
 * tocar el guion. Los textos también salen de aquí, traducidos: el constructor
 * no lleva una palabra incrustada.
 */
$i18n = [];
foreach ([
    'more', 'edit', 'close', 'okSaved', 'okMoved', 'okDeleted', 'errSave', 'needPlan',
    'levelNew', 'levelEdit', 'levelDelete', 'levelConfirm', 'levelNoPlan',
    'pointNew', 'pointEdit', 'pointConfirm', 'points', 'noPoints', 'noCategory', 'delete',
    'pointLocate', 'pointRelocate', 'pointRelocateOff', 'pointClickNew',
    'pointHide', 'pointShow', 'hidden', 'hasImage', 'hasLink',
    'pointDuplicate', 'okPointCopied',
    // Sub-elementos: el selector del diálogo y la cascada de la lista.
    'pointParent', 'pointParentNone', 'pointParentHint', 'pointParentBlocked',
    'subElements', 'subsOpen', 'subOf',
    // --- las formas
    'shapeFinish', 'shapeCancel', 'shapeFew', 'okGeom',
    'shapeHintPin', 'shapeHintText', 'shapeHintLine', 'shapeHintPolygon',
    'shapeHintRect', 'shapeHintCircle',
    'toolPin', 'toolRect', 'toolCircle', 'toolPolygon', 'toolLine', 'toolText',
    'save', 'cancel',
    'props', 'propFill', 'propOpacity', 'propStroke', 'propWidth', 'propBorder',
    'propFont', 'propInherit', 'propNone', 'okStyle',
    'borderSolid', 'borderDashed', 'borderDotted', 'borderNone',
    'propFlow', 'propFlowHint', 'propArrows', 'propArrowsHint', 'okSettings', 'elements',
] as $k) {
    $i18n[$k] = lang('Map.' . $k);
}
?>
<script type="application/json" id="mapData"><?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mapI18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mapMeta"><?= json_encode([
    'csrfName'        => csrf_token(),
    'csrfHash'        => csrf_hash(),
    'tokenUrl'        => base_url('mapas/editor/' . $map['id'] . '/datos'),
    'payloadUrl'      => base_url('mapas/editor/' . $map['id'] . '/datos'),
    'levelStoreUrl'   => base_url('mapas/' . $map['id'] . '/niveles'),
    'levelUpdateUrl'  => base_url('mapas/niveles/__ID__'),
    'levelDeleteUrl'  => base_url('mapas/niveles/__ID__/eliminar'),
    'pointStoreUrl'   => base_url('mapas/niveles/__ID__/puntos'),
    'pointUpdateUrl'  => base_url('mapas/puntos/__ID__'),
    'pointDeleteUrl'  => base_url('mapas/puntos/__ID__/eliminar'),
    'pointCopyUrl'    => base_url('mapas/puntos/__ID__/duplicar'),
    'moveUrl'         => base_url('mapas/puntos/__ID__/mover'),
    'geomUrl'         => base_url('mapas/puntos/__ID__/geometria'),
    'settingsUrl'     => base_url('mapas/editor/' . $map['id'] . '/ajustes'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= base_url('mapa/recurso/viewer.js') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/js/viewer.js') ?>"></script>
<script src="<?= base_url('mapa/recurso/editor.js') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/js/editor.js') ?>"></script>
<?= $this->endSection() ?>
