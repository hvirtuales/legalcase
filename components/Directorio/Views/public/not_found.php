<?php
/** 404 del directorio público. Mismo lienzo autosuficiente que la página. */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Directorio no disponible</title>
<style>
    :root { --dir-bg: #f8fafc; --dir-text: #111827; --dir-muted: #6b7280; }
    @media (prefers-color-scheme: dark) { :root { --dir-bg: #0b1120; --dir-text: #f3f4f6; --dir-muted: #9ca3af; } }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--dir-bg);
        color: var(--dir-text); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 2rem; }
    .box { text-align: center; max-width: 26rem; }
    h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
    p { color: var(--dir-muted); margin: 0; font-size: .9375rem; }
</style>
</head>
<body>
    <div class="box">
        <h1>Este directorio no está disponible</h1>
        <p>El enlace puede haber cambiado, o el grupo ya no publica su directorio.</p>
    </div>
</body>
</html>
