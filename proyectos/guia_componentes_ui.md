# Guía de Componentes UI - Sistema de Gestión

Esta guía documenta los componentes UI del sistema para mantener la consistencia visual al crear nuevas
pantallas y componentes.

- **Stack:** Tailwind CSS v4, compilado con el binario standalone. La fuente es `resources/css/app.css` y la
  salida `public/css/tailwind.css`.
- **Iconos:** SVG Lucide auto-hospedados.
- **Tipografía:** Inter, auto-hospedada.
- **La plataforma no usa Bootstrap en ninguna forma.** No se usan sus clases (`col-*`, `row`, `d-none`,
  `form-control`, `table-responsive`, `btn-outline-primary`…), ni Bootstrap Icons (`bi bi-*`), ni su
  JavaScript (`data-bs-*`). Aunque alguna clase propia se llame parecido (`.btn`, `.badge`, `.card`), está
  definida con Tailwind en `@layer components` y **solo existen las variantes listadas aquí**.

> Después de añadir o cambiar clases en una vista, ejecuta `composer tailwind` o las utilidades nuevas no
> aparecerán.

## 🎨 Filosofía de Diseño

**Estilo:** Executive Clean: minimalista, profesional, limpio y orientado a negocios. Superficies planas y
sombras suaves.

**Colores: nunca hex ni rgb en las vistas.** Solo tokens semánticos del bloque `@theme` de `app.css`:

| Token (utilidad) | Uso |
|---|---|
| `bg-primary`, `text-primary`, `border-primary`, `bg-primary-light`, `text-primary-contrast` | Acción principal, enlaces activos, foco |
| `bg-surface`, `bg-surface-muted` | Tarjetas / fondos secundarios |
| `text-ink`, `text-muted`, `text-faint` | Texto principal / secundario / terciario |
| `border-line`, `border-line-strong` | Bordes y divisores |
| `success`, `danger`, `warning`, `info` (+ `*-soft`) | Estados |

El color primario lo sobrescribe `/theme.css` en runtime, y el modo oscuro cambia los tokens bajo
`[data-theme="dark"]`. Por eso una vista que solo usa tokens funciona en los dos temas sin tocar nada.

---

## 📦 Componentes

### 1. Iconos

```php
<i class="icon icon-pencil"></i>
```

- El color sale de `currentColor` y el tamaño de `1em`, así que `text-primary` o `text-2xl` los afectan.
- **Solo existen los que están en `public/css/icons.css`.** Un nombre inexistente no da error: simplemente no
  pinta nada.
- La separación con el texto la da el `gap` del contenedor (`.btn` ya lo trae), no un margen en el icono.

### 2. Botones

```php
<button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('App.save') ?></button>
<a href="<?= base_url('users') ?>" class="btn btn-secondary"><i class="icon icon-arrow-left"></i> <?= lang('App.back') ?></a>
<button class="btn btn-outline btn-sm">…</button>
```

- **Variantes:** `btn-primary`, `btn-secondary`, `btn-outline`, `btn-ghost`, `btn-success` y `btn-danger`.
- **Tamaños y ancho:** `btn-sm` y `btn-block`.
- **Botones pegados:** `.btn-group`.
- **Botones en fila:** `<div class="flex gap-2">…</div>`.

### 3. Tarjetas y cabecera de pantalla

```php
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-box-seam"></i>
            <h5 class="font-semibold"><?= lang('App.title') ?></h5>
        </div>
        <a href="<?= base_url('x/create') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i> <?= lang('App.create') ?>
        </a>
    </div>
    <div class="card-body">…</div>
</div>
```

La acción principal de la pantalla va a la derecha de la cabecera.

### 4. Listados (siempre server-side)

Para listar entidades se usa `App\Libraries\DataTable` en el controlador y el partial `partials/datatable` en la
vista, que dan búsqueda, orden y paginación. No se pinta un `<table>` a mano. La referencia es
`app/Views/plans/index.php`:

```php
<?= view('partials/datatable', [
    'dt'                => $dt,
    'baseUrl'           => base_url('plans'),
    'searchPlaceholder' => lang('App.search'),
    'emptyText'         => lang('App.noRecords'),
    'columns'           => [
        ['key' => 'name', 'label' => lang('App.name'), 'sortable' => true,
            'render' => fn ($r) => '<strong>' . esc($r['name']) . '</strong>'],
    ],
    'actions' => function ($r) {
        return '<div class="flex justify-end gap-1">'
            . '<a href="' . base_url('plans/edit/' . $r['id']) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
            . view('partials/delete_button', ['action' => base_url('plans/delete/' . $r['id'])])
            . '</div>';
    },
]) ?>
```

Tabla simple, solo si no es un listado de entidades (resúmenes o detalles):

```php
<div class="table-card overflow-x-auto">
    <table class="table">
        <thead><tr><th>…</th><th class="text-right">…</th></tr></thead>
        <tbody><tr><td>…</td><td class="text-right">…</td></tr></tbody>
    </table>
</div>
```

### 5. Borrados

Siempre con POST, token CSRF y confirmación, a través del partial. Nunca con un `<a href>`:

```php
<?= view('partials/delete_button', ['action' => base_url('x/delete/' . $id), 'confirm' => '¿Eliminar?']) ?>
```

### 6. Formularios

Los campos se agrupan en `.form-section`, en este orden: decisión → datos → contacto → publicación. La
referencia es `app/Views/plans/_form.php`.

```php
<fieldset class="form-section">
    <legend class="form-section-title"><?= lang('X.secData') ?></legend>
    <p class="form-section-hint"><?= lang('X.secDataHint') ?></p>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="form-group">
            <label for="name" class="form-label"><?= lang('App.name') ?> <span class="text-danger">*</span></label>
            <input type="text" id="name" name="name" class="form-input" required>
            <p class="form-hint"><?= lang('X.nameHint') ?></p>
        </div>
        <div class="form-group">
            <label for="role" class="form-label"><?= lang('App.role') ?></label>
            <select id="role" name="role" class="form-select">…</select>
        </div>
    </div>

    <div class="form-group">
        <textarea name="notes" rows="3" class="form-textarea"></textarea>
    </div>

    <label class="form-check">
        <input type="checkbox" name="is_active" value="1" class="form-check-input">
        <span class="form-check-label"><?= lang('App.active') ?></span>
    </label>
</fieldset>
```

- **Campos:** `form-input`, `form-select` y `form-textarea`.
- **Tamaño pequeño:** no existe `form-select-sm` ni `form-control-sm`. Se añaden utilidades
  (`class="form-select py-1.5 text-xs"`).
- **Input con icono:** `<div class="input-group"><span class="input-group-text"><i class="icon icon-search"></i></span><input class="form-input"></div>`.
- **Teléfono:** `<input data-phone>`. Ya tiene banderas y normaliza el valor. No lo pongas dentro de
  `.input-group`.
- **Imagen:** `<input type="file" data-preview>` muestra una miniatura.

### 7. Badges

```php
<span class="badge badge-success">Activo</span>
```

Variantes: `badge-primary`, `badge-success`, `badge-danger`, `badge-warning`, `badge-info`, `badge-secondary` y
`badge-light`.

### 8. Alertas

```php
<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><i class="icon icon-check-circle"></i> <?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
```

Variantes: `alert-success`, `alert-danger` (o `alert-error`), `alert-warning` y `alert-info`.

### 9. Pestañas

```php
<nav class="tabs">
    <a class="tab-link active" href="#">…</a>
    <a class="tab-link" href="#">…</a>
</nav>
<div class="tab-panel">…</div>
```

### 10. Listas

```php
<div class="list-group">
    <div class="list-group-item"><span>…</span><span class="badge badge-success">…</span></div>
</div>
```

### 11. Sidebar

Lo pinta `layouts/admin.php` con `.sidebar-link` y el estado `active`, calculado con `url_is()`. Los menús de los
componentes salen de su `Manifest.php`: no se editan a mano en el layout.

---

## 🎯 Reglas de Consistencia

- **Reutilizar:** antes de crear una pantalla, abre una equivalente que ya exista y replica su estructura.
- **Componentes nuevos:** si hace falta uno, se añade a `@layer components` de `app.css` como clase
  reutilizable, no como utilidades sueltas repetidas en cada vista.
- **Espaciado:** `gap-2`/`gap-3` entre elementos y `mb-4` entre bloques (`.form-group` ya lo trae).
- **Responsive:** breakpoints de Tailwind (`sm:`, `md:`, `lg:`) y rejillas (`grid md:grid-cols-2`). Para ocultar
  en móvil, `hidden md:block`. Las tablas van en un contenedor `overflow-x-auto`.
- **Textos:** siempre con `lang('Archivo.clave')`, con la clave en `es` y en `en`. Nunca texto incrustado.
- **Escapado:** toda salida pasa por `esc()`.
- **Cero CDN:** todo recurso es auto-hospedado. Las vistas públicas de componentes llevan su CSS y sus SVG en
  línea (contrato §7.3).

---

## ✅ Checklist al Crear Nuevos Elementos

- [ ] ¿Revisé una pantalla equivalente existente y seguí su estructura?
- [ ] ¿Usa solo tokens (sin hex ni rgb)? ¿Se ve bien en modo claro y oscuro?
- [ ] ¿Cero clases de Bootstrap (`col-*`, `row`, `d-*`, `form-control`, `bi bi-*`, `data-bs-*`…)?
- [ ] ¿Todos los `icon-*` existen en `public/css/icons.css`?
- [ ] ¿Los listados usan `DataTable` + `partials/datatable`, y los borrados `partials/delete_button`?
- [ ] ¿Los formularios usan `.form-section`, `.form-group`, `.form-label` y `.form-input`/`select`/`textarea`?
- [ ] ¿Todos los textos usan `lang()` con claves en `es` y en `en`? ¿Se escapan las variables con `esc()`?
- [ ] ¿Funciona en móvil (~390 px) sin desbordes horizontales?
- [ ] ¿Ejecuté `composer tailwind` y lo revisé en el navegador?

---

**Última actualización:** 2026-09-11
