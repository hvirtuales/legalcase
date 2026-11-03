<?php

namespace Components\Paginas\Libraries;

/**
 * Limpia el HTML que llega del editor **al guardar**, no al pintar: lo que queda
 * en la base de datos ya es seguro, así que ninguna otra vía de salida (el
 * render público, una previsualización, un futuro exportador) puede olvidarse de
 * limpiarlo.
 *
 * Copia propia y no un `use` del sanitizador de Servicios: un componente no
 * depende de otro (contrato §0.4). La lista blanca sí es distinta —aquí se
 * construyen páginas, así que se admiten encabezados, tablas y figuras que en
 * una cotización no tenían sentido.
 *
 * `strip_tags` por sí solo no basta: deja pasar `onclick` o `href="javascript:"`
 * dentro de las etiquetas permitidas. Por eso, tras la lista blanca hay una
 * pasada con DOMDocument que borra todo atributo no declarado y toda URL cuyo
 * esquema no sea seguro.
 */
class HtmlSanitizer
{
    /** Etiquetas que puede producir el editor de texto enriquecido. */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><a>'
        . '<h2><h3><h4><h5><blockquote><span><small><code><pre><hr>'
        . '<table><thead><tbody><tr><th><td><figure><figcaption><img>';

    /** Únicos atributos que sobreviven, y solo en las etiquetas indicadas. */
    private const ALLOWED_ATTRS = [
        'a'   => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'td'  => ['colspan', 'rowspan'],
        'th'  => ['colspan', 'rowspan'],
    ];

    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Solo etiquetas vacías: no vale la pena guardarlo.
        if (trim(strip_tags($html)) === '' && !preg_match('/<(br|hr|img)\b/i', $html)) {
            return '';
        }

        $doc  = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // El envoltorio evita que DOMDocument añada <html><body> al resultado.
        // LIBXML_NONET: que no intente resolver nada por red.
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="pag-root">' . $html . '</div>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('pag-root');
        if (!$root) {
            return strip_tags($html); // algo salió raro: texto plano
        }

        /** @var \DOMElement $el */
        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $el) {
            $tag = strtolower($el->nodeName);
            if ($tag === 'div') {
                continue; // el envoltorio
            }
            $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $name = strtolower($attr->nodeName);
                if (!in_array($name, $allowed, true)) {
                    $el->removeAttribute($attr->nodeName);

                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && !$this->safeUrl($attr->nodeValue)) {
                    $el->removeAttribute($attr->nodeName);
                }
            }

            // Los enlaces salen a pestaña nueva y sin filtrar el referer.
            if ($tag === 'a' && $el->hasAttribute('href')) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * ¿La URL es segura para emitirla en la página?
     *
     * Se admiten las **relativas** (`/uploads/…`, que es donde acaban las
     * imágenes subidas) y los esquemas de la lista; se rechaza todo lo demás,
     * empezando por `javascript:` y `data:`.
     */
    public function safeUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false || $scheme === '') {
            // Relativa o ancla. `//evil.com` es protocolo-relativa: fuera.
            return !str_starts_with($url, '//');
        }

        return in_array(strtolower($scheme), self::SAFE_SCHEMES, true);
    }
}
