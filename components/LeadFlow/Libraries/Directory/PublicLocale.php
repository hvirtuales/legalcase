<?php

namespace Components\LeadFlow\Libraries\Directory;

/**
 * Idioma del sitio público: `?lang=es|en` en cualquier página del directorio.
 *
 * El núcleo ya cambia de idioma con `/language/{es|en}`, pero lo guarda SOLO en la
 * sesión: para un buscador la versión inglesa no existiría, porque no tiene una URL
 * propia que indexar. Aquí cada página tiene las dos (`/buscar` y `/buscar?lang=en`),
 * el layout las anuncia con `hreflang` y el selector solo enlaza a ellas.
 *
 * El idioma elegido se guarda en la MISMA clave de sesión que usa el núcleo
 * (`language`), así que el resto de la navegación —y el panel, si después entra—
 * sigue en ese idioma sin que cada enlace tenga que arrastrar el parámetro.
 *
 * Se llama desde `initController()` de los controladores públicos, DESPUÉS del del
 * núcleo (que ya fijó el idioma de la sesión) y antes de cualquier `lang()`.
 */
class PublicLocale
{
    public const LOCALES = ['es', 'en'];

    public static function apply(): void
    {
        $lang = service('request')->getGet('lang');
        if (! is_string($lang) || ! in_array($lang, self::LOCALES, true)) {
            return;
        }
        session()->set('language', $lang);
        service('request')->setLocale($lang);
        // El servicio de idioma puede haberse creado ya con el idioma anterior.
        service('language')->setLocale($lang);
    }

    public static function current(): string
    {
        $l = service('request')->getLocale();

        return in_array($l, self::LOCALES, true) ? $l : 'es';
    }

    /**
     * La dirección de ESTA página en otro idioma, conservando el resto de la consulta.
     * El español es la versión sin parámetro: es la canónica de siempre.
     */
    public static function url(string $locale): string
    {
        $uri   = current_url(true);
        $query = [];
        parse_str($uri->getQuery(), $query);
        unset($query['lang']);
        if ($locale !== 'es') {
            $query['lang'] = $locale;
        }
        $uri->setQuery(http_build_query($query));

        return (string) $uri;
    }

    /** La canónica que pasa el controlador, en el idioma de la petición. */
    public static function canonical(string $canonical, ?string $locale = null): string
    {
        $locale ??= self::current();
        if ($canonical === '' || $locale === 'es') {
            return $canonical;
        }

        return $canonical . (str_contains($canonical, '?') ? '&' : '?') . 'lang=' . $locale;
    }
}
