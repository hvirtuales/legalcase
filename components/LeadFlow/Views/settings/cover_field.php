<?php
/**
 * La portada del directorio, dentro de la Configuración del sistema.
 *
 * **Campos sueltos, sin `<form>`.** Esta vista se pinta dentro del formulario único de
 * la pantalla de Configuración, y uno anidado es HTML inválido: el navegador lo cierra
 * donde le parece y la mitad de los campos dejan de enviarse. Lo que se envía aquí lo
 * recoge `CoverService::handleSettings()`.
 *
 * Por eso quitar la portada es una **casilla** y no un botón: un botón necesitaría su
 * propio formulario.
 *
 * La vista previa se pinta con la dirección que sirve el componente (`/portada`), no
 * con una ruta de archivo: el archivo vive fuera de `public/`.
 *
 * @var array $settings  los ajustes del núcleo (no se usan aquí, llegan por la ranura)
 */
$cover = (new \Components\LeadFlow\Libraries\Directory\CoverService())->url();
?>
<div class="form-group">
    <label for="lfl_cover" class="form-label"><?= lang('Lfl.covTitle') ?></label>

    <?php if ($cover): ?>
        <div class="mb-2">
            <img src="<?= esc($cover, 'attr') ?>" alt=""
                class="aspect-[16/6] max-w-[320px] rounded border border-line object-cover">
        </div>
    <?php endif; ?>

    <input type="file" class="form-input" id="lfl_cover" name="lfl_cover" data-preview
        accept="image/jpeg,image/png,image/webp">
    <small class="form-hint"><?= lang('Lfl.covHint') ?></small>
    <small class="form-hint"><?= lang('Lfl.covFileHint') ?></small>

    <?php if ($cover): ?>
        <label class="form-check mt-2">
            <input type="checkbox" class="form-check-input" name="lfl_cover_remove" value="1">
            <span class="form-check-label"><?= lang('Lfl.covRemove') ?></span>
        </label>
    <?php endif; ?>
</div>
