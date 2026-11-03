<?php

if (!function_exists('media_url')) {
    /**
     * Devuelve la URL para un recurso almacenado en app_settings u otro campo.
     *
     * - Vacío → cadena vacía.
     * - URL absoluta (http/https, ej. S3/Cloudflare R2) → se devuelve tal cual.
     * - Ruta relativa (ej. "/uploads/settings/x.png") → se resuelve con base_url().
     *
     * Permite convivir archivos locales y remotos sin tocar cada vista.
     */
    function media_url($path): string
    {
        $path = (string) ($path ?? '');

        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return base_url($path);
    }
}
