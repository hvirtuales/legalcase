<?php

namespace Components\Mailer\Libraries;

/**
 * Limpia el HTML que llega del constructor **al guardar**, no al enviar: lo que
 * queda en la base de datos ya es seguro, así que ninguna otra salida —la vista
 * previa, la prueba, el envío real— puede olvidarse de limpiarlo.
 *
 * Copia propia y no un `use` del de Páginas: un componente no depende de otro
 * (contrato 0.4). La lista blanca es la de un CORREO, que es más corta que la de
 * una web: nada de scripts, iframes, formularios ni `video`, que además ningún
 * cliente de correo pinta.
 *
 * `strip_tags` no basta: deja pasar `onclick` o `href="javascript:"` dentro de
 * las etiquetas permitidas. Por eso, tras la lista blanca hay una pasada con
 * DOMDocument que borra todo atributo no declarado y toda URL cuyo esquema no
 * sea seguro.
 */
class HtmlSanitizer
{
    /** Lo que un cliente de correo pinta de forma fiable. */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><a>'
        . '<h1><h2><h3><h4><span><small><blockquote><hr>'
        . '<table><thead><tbody><tr><th><td><img><div><center><font>';

    /**
     * `style` se admite en el correo —los estilos van EN LINEA por obligación,
     * no hay hojas externas— pero se limpia de `expression()` y de `url(`, que
     * es por donde se cuela lo desagradable.
     */
    private const ALLOWED_ATTRS = [
        'a'     => ['href', 'target', 'rel', 'style'],
        'img'   => ['src', 'alt', 'width', 'height', 'style', 'border'],
        'td'    => ['colspan', 'rowspan', 'align', 'valign', 'width', 'style', 'bgcolor'],
        'th'    => ['colspan', 'rowspan', 'align', 'valign', 'width', 'style', 'bgcolor'],
        'tr'    => ['style', 'bgcolor'],
        'table' => ['width', 'align', 'border', 'cellpadding', 'cellspacing', 'style', 'bgcolor', 'role'],
        'div'   => ['style', 'align'],
        'p'     => ['style', 'align'],
        'span'  => ['style'],
        'h1'    => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'],
        'ul'    => ['style'], 'ol' => ['style'], 'li' => ['style'],
        'font'  => ['color', 'size', 'face'],
        'hr'    => ['style'],
    ];

    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);

        $doc  = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // El envoltorio evita que DOMDocument añada <html><body>.
        // LIBXML_NONET: que no intente resolver nada por red.
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="mailer-root">' . $html . '</div>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('mailer-root');
        if (!$root) {
            return strip_tags($html);
        }

        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $el) {
            $tag = strtolower($el->nodeName);
            if ($el === $root) {
                continue;
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

                    continue;
                }
                if ($name === 'style') {
                    $el->setAttribute('style', $this->safeStyle($attr->nodeValue));
                }
            }

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

    /** Fuera lo que ejecuta o carga algo desde un estilo en línea. */
    private function safeStyle(?string $style): string
    {
        $style = (string) $style;
        $style = preg_replace('/expression\s*\(/i', '', $style);
        $style = preg_replace('/url\s*\(/i', '', $style);
        $style = str_ireplace(['javascript:', 'behavior:', '@import'], '', $style);

        return mb_substr(trim($style), 0, 500);
    }

    /** Relativas y esquemas de la lista; fuera `javascript:`, `data:` y `//evil`. */
    public function safeUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false || $scheme === '') {
            return !str_starts_with($url, '//');
        }

        return in_array(strtolower($scheme), self::SAFE_SCHEMES, true);
    }
}
