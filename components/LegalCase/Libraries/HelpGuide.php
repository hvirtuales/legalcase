<?php

namespace Components\LegalCase\Libraries;

/**
 * Guía de uso de LegalCase (`legalcase/ayuda`): **la única definición**.
 *
 * Ordenada como trabaja un abogado —cómo se organiza, abrir un caso, la ficha, las
 * partes, los términos, la agenda, los documentos, la comunicación, la aprobación, lo
 * que ve el cliente, los mensajes y la configuración—, no por pantallas: quien llega
 * nuevo todavía no sabe cómo se llaman.
 *
 * De aquí beben la vista, la lista blanca de imágenes (`Help::image`) y `lgc:contrato`.
 * Añadir un paso es una entrada aquí, su captura en `Assets/help/<imagen>-<idioma>.webp`
 * y sus claves de idioma `Lgc.hlp_<key>`, `…Body`, `…P1…Pn` y `…Cap`.
 *
 * Cada paso declara:
 *  - `icon`   → un icono que EXISTA en `public/css/icons.css`.
 *  - `image`  → la captura de referencia (sin idioma ni extensión).
 *  - `langs`  → idiomas en los que existe la captura; si falta el del lector se usa
 *               el primero.
 *  - `route`  → la pantalla a la que lleva el botón, o null si vive dentro de la ficha.
 *  - `perm`   → el permiso que exige esa pantalla (el botón solo sale si se tiene).
 *  - `points` → cuántos puntos tiene la explicación.
 *
 * **Las capturas usan los datos demo** (`spark lgc:demo`) con nombres y correos
 * ficticios: viajan con el componente a cualquier instalación.
 */
final class HelpGuide
{
    public const STEPS = [
        'organize' => ['icon' => 'icon-speedometer2', 'image' => 'mi-dia', 'langs' => ['es', 'en'], 'route' => 'legalcase', 'perm' => 'lgc.view', 'points' => 4],
        'open'     => ['icon' => 'icon-plus-circle', 'image' => 'caso-nuevo', 'langs' => ['es', 'en'], 'route' => 'legalcase/asuntos/nuevo', 'perm' => 'lgc.create', 'points' => 4],
        'file'     => ['icon' => 'icon-briefcase', 'image' => 'ficha', 'langs' => ['es', 'en'], 'route' => 'legalcase/asuntos', 'perm' => 'lgc.view', 'points' => 4],
        'parties'  => ['icon' => 'icon-people', 'image' => 'partes', 'langs' => ['es', 'en'], 'route' => null, 'perm' => 'lgc.view', 'points' => 4],
        'terms'    => ['icon' => 'icon-clock', 'image' => 'plazos', 'langs' => ['es', 'en'], 'route' => null, 'perm' => 'lgc.view', 'points' => 5],
        'agenda'   => ['icon' => 'icon-calendar', 'image' => 'calendario', 'langs' => ['es', 'en'], 'route' => 'legalcase/agenda/calendario', 'perm' => 'lgc.view', 'points' => 3],
        'docs'     => ['icon' => 'icon-folder', 'image' => 'documentos', 'langs' => ['es', 'en'], 'route' => null, 'perm' => 'lgc.view', 'points' => 4],
        'comms'    => ['icon' => 'icon-send', 'image' => 'comunicacion', 'langs' => ['es', 'en'], 'route' => 'legalcase/comunicaciones', 'perm' => 'lgc.view', 'points' => 4],
        'approve'  => ['icon' => 'icon-patch-check', 'image' => 'actualizacion', 'langs' => ['es', 'en'], 'route' => null, 'perm' => 'lgc.approve', 'points' => 3],
        'portal'   => ['icon' => 'icon-globe', 'image' => 'portal', 'langs' => ['es'], 'route' => null, 'perm' => 'lgc.share', 'points' => 4],
        'inbox'    => ['icon' => 'icon-chat', 'image' => 'mensajes', 'langs' => ['es', 'en'], 'route' => 'legalcase/mensajes', 'perm' => 'lgc.view', 'points' => 3],
        'settings' => ['icon' => 'icon-gear', 'image' => 'configuracion', 'langs' => ['es', 'en'], 'route' => 'legalcase/configuracion', 'perm' => 'lgc.configure', 'points' => 4],
    ];

    /** Vocabulario, en el orden en que aparece en el trabajo. */
    public const TERMS = ['Case', 'Proceeding', 'Event', 'Term', 'Hearing', 'Task', 'Update', 'Portal', 'Audit'];

    /** Los siete permisos del Manifest, de menos a más. */
    public const PERMS = ['view', 'create', 'edit', 'delete', 'share', 'approve', 'configure'];

    public const FAQ = 6;

    /** El archivo de la captura de un paso en el idioma pedido (o el primero que exista). */
    public static function imageFile(string $step, string $locale): ?string
    {
        $s = self::STEPS[$step] ?? null;
        if (! $s) {
            return null;
        }
        $lang = in_array($locale, $s['langs'], true) ? $locale : $s['langs'][0];

        return $s['image'] . '-' . $lang . '.webp';
    }

    /** Lista blanca de imágenes que puede servir `Help::image`: nada fuera de aquí toca el disco. */
    public static function allowedImages(): array
    {
        $out = [];
        foreach (self::STEPS as $s) {
            foreach ($s['langs'] as $l) {
                $out[$s['image'] . '-' . $l . '.webp'] = true;
            }
        }

        return $out;
    }
}
