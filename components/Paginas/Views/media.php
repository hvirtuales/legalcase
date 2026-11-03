<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Biblioteca de medios de la empresa activa.
 *
 * Una rejilla, no una `DataTable`: lo que se busca aquí es **reconocer una
 * imagen de un vistazo**, y para eso la miniatura es el dato. La búsqueda es por
 * el nombre con el que se subió, que es lo único legible que tiene un archivo
 * cuyo nombre real es un identificador.
 *
 * @var array|null $items
 * @var array      $usedIn  ruta => en cuántas páginas se usa
 */
helper('media');
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-image"></i>
            <h5 class="font-semibold"><?= lang('Pag.media') ?></h5>
        </div>
        <?php if ($items !== null): ?>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= base_url('paginas') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-journal-text"></i> <?= lang('Pag.pages') ?>
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-media-upload>
                    <i class="icon icon-upload"></i> <?= lang('Pag.uploadImages') ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if ($items === null): ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i><span><?= lang('Pag.noCompanyHint') ?></span>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i>
                <span><?= lang('Pag.mediaHint', [(int) $maxWidth]) ?></span>
            </div>

            <form method="get" class="mb-4 flex gap-2">
                <input type="search" name="q" class="form-input" value="<?= esc($q ?? '') ?>"
                       placeholder="<?= esc(lang('Pag.searchMedia'), 'attr') ?>">
                <button class="btn btn-secondary btn-sm"><i class="icon icon-search"></i></button>
            </form>

            <?php if (!$items): ?>
                <p class="py-8 text-center text-sm text-muted"><?= lang('Pag.noMediaYet') ?></p>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <?php foreach ($items as $m): ?>
                        <?php $uso = (int) ($usedIn[$m['path']] ?? 0); ?>
                        <figure class="overflow-hidden rounded-md border border-line">
                            <a href="<?= esc(media_url($m['path'])) ?>" target="_blank" rel="noopener"
                               class="block bg-surface-muted">
                                <img src="<?= esc(media_url($m['path'])) ?>" alt="<?= esc($m['name'] ?? '') ?>"
                                     loading="lazy" class="h-36 w-full object-contain">
                            </a>
                            <figcaption class="p-2 text-xs">
                                <span class="block truncate font-medium" title="<?= esc($m['name'] ?? '', 'attr') ?>">
                                    <?= esc($m['name'] ?: basename((string) $m['path'])) ?>
                                </span>
                                <span class="block text-faint">
                                    <?= (int) $m['width'] ?>×<?= (int) $m['height'] ?>
                                    <?php if (!empty($m['bytes'])): ?>
                                        · <?= round(((int) $m['bytes']) / 1024) ?> KB
                                    <?php endif; ?>
                                </span>
                                <div class="mt-2 flex items-center justify-between gap-1">
                                    <?php if ($uso > 0): ?>
                                        <span class="badge badge-primary"><?= lang('Pag.usedInPages', [$uso]) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= lang('Pag.usedNowhere') ?></span>
                                    <?php endif; ?>
                                    <?php
                                    // El aviso dice en cuántas páginas está: borrar
                                    // una imagen en uso deja huecos, y eso hay que
                                    // saberlo ANTES, no después.
                                    echo view('partials/delete_button', [
                                        'action'  => base_url('paginas/medios/eliminar/' . (int) $m['id']),
                                        'confirm' => $uso > 0
                                            ? lang('Pag.confirmDeleteMediaUsed', [$uso])
                                            : lang('Pag.confirmDeleteMedia'),
                                        'label'   => lang('Pag.delete'),
                                    ]);
                                    ?>
                                </div>
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($items !== null): ?>
<!-- La subida va por la MISMA ruta que usa el editor: una sola política de
     tipos, tamaño y optimización, y una sola forma de anotar la imagen. -->
<form id="pagMediaForm" class="hidden">
    <?= csrf_field() ?>
</form>
<input type="file" id="pagMediaFile" accept="image/*" multiple class="hidden">

<script>
(function () {
    var btn  = document.querySelector('[data-media-upload]');
    var file = document.getElementById('pagMediaFile');
    var form = document.getElementById('pagMediaForm');
    if (!btn || !file || !form) { return; }

    var campo = form.querySelector('input[name^="csrf"]');
    var url   = <?= json_encode(base_url('paginas/subir'), JSON_UNESCAPED_SLASHES) ?>;
    var subiendo = <?= json_encode(lang('Pag.uploading')) ?>;
    var error    = <?= json_encode(lang('Pag.errUpload')) ?>;

    btn.addEventListener('click', function () { file.click(); });

    file.addEventListener('change', function () {
        if (!file.files || !file.files.length) { return; }
        btn.disabled = true;
        btn.textContent = subiendo;

        // En serie, no en paralelo: el token de CSRF rota en cada POST y hay
        // que usar el que devolvió la subida anterior.
        var pendientes = Array.prototype.slice.call(file.files);
        (function siguiente() {
            if (!pendientes.length) { window.location.reload(); return; }

            var datos = new FormData();
            datos.append('file', pendientes.shift());
            datos.append(campo.name, campo.value);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': campo.value, 'X-Requested-With': 'XMLHttpRequest' },
                body: datos
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.csrf) { campo.value = res.csrf; }
                if (!res || !res.ok) { window.alert((res && res.error) || error); }
                siguiente();
            }).catch(function () { window.alert(error); siguiente(); });
        }());
    });
}());
</script>
<?php endif; ?>
<?= $this->endSection() ?>
