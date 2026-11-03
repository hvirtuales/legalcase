<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición de una vista del directorio.
 *
 * @var array|null $board
 * @var array      $sectors    sectores en uso por las empresas del grupo, con su total
 * @var array      $companies  todas las empresas del grupo (para el modo manual)
 * @var array      $picked     ids ya marcados
 */
$v      = static fn (string $k, $def = '') => old($k, $board[$k] ?? $def);
$accion = $board
    ? base_url('directorio/editar/' . (int) $board['id'])
    : base_url('directorio/nueva');
$modo   = old('mode', $board['mode'] ?? 'sector');
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-signpost"></i>
        <h5 class="font-semibold"><?= $board ? lang('Dir.editBoard') : lang('Dir.newBoard') ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= $accion ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Dir.secBoardIdentity') ?></legend>

                <div class="form-group">
                    <label for="title" class="form-label"><?= lang('Dir.fBoardTitle') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" required maxlength="150"
                        value="<?= esc($v('title')) ?>">
                    <?php if ($board): ?>
                        <p class="form-hint"><?= lang('Dir.fBoardSlugHint') ?> <code><?= esc($board['slug']) ?></code></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="intro" class="form-label"><?= lang('Dir.fIntro') ?></label>
                    <textarea id="intro" name="intro" class="form-textarea" rows="2"><?= esc($v('intro')) ?></textarea>
                    <p class="form-hint"><?= lang('Dir.fIntroHint') ?></p>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Dir.secWhoAppears') ?></legend>
                <p class="form-section-hint"><?= lang('Dir.secWhoAppearsHint') ?></p>

                <div class="form-group">
                    <div class="form-check">
                        <input type="radio" id="mode_sector" name="mode" value="sector" class="form-check-input"
                            <?= $modo !== 'manual' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mode_sector"><?= lang('Dir.modeSector') ?></label>
                    </div>
                    <p class="form-hint ml-6"><?= lang('Dir.modeSectorHint') ?></p>

                    <div class="form-check mt-2">
                        <input type="radio" id="mode_manual" name="mode" value="manual" class="form-check-input"
                            <?= $modo === 'manual' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mode_manual"><?= lang('Dir.modeManual') ?></label>
                    </div>
                    <p class="form-hint ml-6"><?= lang('Dir.modeManualHint') ?></p>
                </div>

                <!-- Bloque del modo «por sector» -->
                <div class="form-group" data-mode-block="sector">
                    <label for="sector_id" class="form-label"><?= lang('Dir.fSector') ?></label>
                    <select id="sector_id" name="sector_id" class="form-select">
                        <option value=""><?= lang('Dir.allCompanies') ?></option>
                        <?php foreach ($sectors as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $v('sector_id') === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['name']) ?> (<?= (int) $s['total'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint"><?= lang('Dir.fSectorHint') ?></p>
                </div>

                <!-- Bloque del modo «a mano» -->
                <div class="form-group" data-mode-block="manual">
                    <label class="form-label"><?= lang('Dir.fCompanies') ?></label>
                    <?php if (!$companies): ?>
                        <p class="text-muted"><?= lang('Dir.noCompaniesYet') ?></p>
                    <?php else: ?>
                        <div class="max-h-80 overflow-y-auto rounded border border-line p-2">
                            <?php foreach ($companies as $c): ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="co<?= (int) $c['id'] ?>"
                                        name="companies[]" value="<?= (int) $c['id'] ?>"
                                        <?= in_array((int) $c['id'], $picked, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="co<?= (int) $c['id'] ?>">
                                        <?= esc($c['name']) ?>
                                        <?php if (!empty($c['sector_name'])): ?>
                                            <span class="text-xs text-muted">· <?= esc($c['sector_name']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Dir.secLayout') ?></legend>
                <p class="form-section-hint"><?= lang('Dir.secLayoutHint') ?></p>

                <?php $disp = old('layout', $board['layout'] ?? 'both'); ?>
                <div class="form-group">
                    <?php foreach (['both', 'list', 'map'] as $op): ?>
                        <div class="form-check<?= $op === 'both' ? '' : ' mt-2' ?>">
                            <input type="radio" id="lay_<?= $op ?>" name="layout" value="<?= $op ?>"
                                class="form-check-input" <?= $disp === $op ? 'checked' : '' ?>>
                            <label class="form-check-label" for="lay_<?= $op ?>"><?= lang('Dir.layout_' . $op) ?></label>
                        </div>
                        <p class="form-hint ml-6"><?= lang('Dir.layout_' . $op . 'Hint') ?></p>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <?php // Los ajustes del mapa no pintan nada si la vista no lo lleva. ?>
            <fieldset class="form-section" data-layout-block="map">
                <legend class="form-section-title"><?= lang('Dir.secMap') ?></legend>
                <p class="form-section-hint"><?= lang('Dir.secMapHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="center_lat" class="form-label"><?= lang('Dir.fCenterLat') ?></label>
                        <input type="text" id="center_lat" name="center_lat" class="form-input" inputmode="decimal"
                            value="<?= esc($v('center_lat')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="center_lng" class="form-label"><?= lang('Dir.fCenterLng') ?></label>
                        <input type="text" id="center_lng" name="center_lng" class="form-input" inputmode="decimal"
                            value="<?= esc($v('center_lng')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="zoom" class="form-label"><?= lang('Dir.fZoom') ?></label>
                        <input type="number" id="zoom" name="zoom" class="form-input" min="1" max="19"
                            value="<?= esc($v('zoom')) ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Dir.secPublish') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="sort_order" class="form-label"><?= lang('Dir.fOrder') ?></label>
                        <input type="number" id="sort_order" name="sort_order" class="form-input"
                            value="<?= esc((string) $v('sort_order', '0')) ?>">
                    </div>
                    <div class="form-group">
                        <div class="form-check mt-6">
                            <input type="checkbox" id="is_published" name="is_published" value="1" class="form-check-input"
                                <?= old('is_published', $board['is_published'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_published"><?= lang('Dir.fPublished') ?></label>
                        </div>
                    </div>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i> <?= lang('Dir.save') ?>
                </button>
                <a href="<?= base_url('directorio') ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i> <?= lang('Dir.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Solo se enseña el bloque del modo elegido. Los dos siguen en el formulario:
// la selección manual se guarda aunque la vista esté en modo «por sector», para
// que cambiar de idea y volver no pierda lo que se había marcado.
(function () {
    var radios = document.querySelectorAll('input[name="mode"]');
    var bloques = document.querySelectorAll('[data-mode-block]');
    function pinta() {
        var modo = document.querySelector('input[name="mode"]:checked');
        modo = modo ? modo.value : 'sector';
        Array.prototype.forEach.call(bloques, function (b) {
            b.style.display = b.dataset.modeBlock === modo ? '' : 'none';
        });
    }
    Array.prototype.forEach.call(radios, function (r) { r.addEventListener('change', pinta); });
    pinta();

    // Los ajustes del mapa solo se ofrecen si la vista lleva mapa: pedir un
    // centro y un zoom para una vista que es solo listado es pedir un dato
    // que no gobierna nada.
    var lays = document.querySelectorAll('input[name="layout"]');
    var mapaBlock = document.querySelector('[data-layout-block="map"]');
    function pintaMapa() {
        var el = document.querySelector('input[name="layout"]:checked');
        if (mapaBlock) { mapaBlock.style.display = (el && el.value === 'list') ? 'none' : ''; }
    }
    Array.prototype.forEach.call(lays, function (r) { r.addEventListener('change', pintaMapa); });
    pintaMapa();
}());
</script>
<?= $this->endSection() ?>
