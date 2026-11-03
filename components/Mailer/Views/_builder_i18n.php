<?php

/**
 * El diccionario que consume `builder.js`.
 *
 * El constructor no lleva ni una palabra incrustada: los textos salen de aquí,
 * ya traducidos por `lang()`, y el JavaScript solo los busca por clave. Lo usan
 * las dos pantallas que montan un correo —el asistente de campaña y el formulario
 * de plantilla—, así que vive en un partial y no copiado en cada una.
 */

$claves = [
    'uiNoBlocks', 'uiOneBlock', 'uiNBlocks', 'uiOneSection', 'uiNSections',
    'uiCol', 'uiCols', 'uiColsHint',
    'uiSecUp', 'uiSecDown', 'uiSecCopy', 'uiSecDel', 'uiSecConfirm',
    'uiDropHere', 'uiUp', 'uiDown', 'uiDuplicate', 'uiRemove',
    'uiPickBlock', 'uiNoProps', 'uiContent', 'uiStyle',
    'uiLeft', 'uiCenter', 'uiRight',
    'uiEmptyText', 'uiUpload', 'uiUploading', 'uiUploadFail',
    'uiSocialSample', 'uiCompanySample', 'presets', 'upDone',
    'dButton', 'phImage', 'phVideo', 'phNoLogo', 'bHtml',
];

$dic = [];
foreach ($claves as $k) {
    $dic[$k] = lang('Mailer.' . $k);
}
?>
<script type="application/json" id="mlI18n"><?= json_encode($dic, JSON_UNESCAPED_UNICODE) ?></script>
