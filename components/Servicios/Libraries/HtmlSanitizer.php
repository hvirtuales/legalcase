<?php

namespace Components\Servicios\Libraries;

/**
 * Limpia el HTML que llega del editor enriquecido (introducción, términos y carta
 * de presentación) antes de guardarlo y antes de imprimirlo.
 *
 * La app original solo limpiaba al generar el PDF (`generar_pdf_auto.php:28`), así
 * que el HTML sucio quedaba almacenado. Aquí se limpia **al guardar**, que es
 * donde importa: lo que está en BD ya es seguro.
 *
 * No basta `strip_tags`: deja pasar atributos como `onclick` o `href="javascript:"`
 * en las etiquetas permitidas. Por eso, tras la lista blanca de etiquetas se hace
 * una pasada con DOMDocument que borra todo atributo que no esté permitido y
 * cualquier URL que no sea http/https/mailto/tel.
 */
class HtmlSanitizer
{
    /** Etiquetas que el editor puede producir. */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a><h3><h4><span>';

    /** Únicos atributos que sobreviven, y solo en las etiquetas indicadas. */
    private const ALLOWED_ATTRS = ['a' => ['href', 'target', 'rel']];

    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);
        if (trim(strip_tags($html)) === '' && stripos($html, '<br') === false) {
            return ''; // solo etiquetas vacías: no vale la pena guardarlo
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // El wrapper evita que DOMDocument añada <html><body> al resultado.
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="quo-root">' . $html . '</div>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('quo-root');
        if (!$root) {
            return strip_tags($html); // algo raro: devuelve texto plano
        }

        /** @var \DOMElement $el */
        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $el) {
            $tag = strtolower($el->nodeName);
            if ($tag === 'div') {
                continue; // el wrapper
            }
            $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $name = strtolower($attr->nodeName);
                if (!in_array($name, $allowed, true)) {
                    $el->removeAttribute($attr->nodeName);

                    continue;
                }
                if ($name === 'href' && !$this->safeUrl($attr->nodeValue)) {
                    $el->removeAttribute('href');
                }
            }

            // Los enlaces salen siempre a pestaña nueva y sin filtrar referer.
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

    private function safeUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        // Relativas y anclas: se descartan, no aportan nada en un documento.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::SAFE_SCHEMES, true);
    }
}
