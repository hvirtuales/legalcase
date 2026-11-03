<?php

namespace Components\Mailer\Libraries;

/**
 * Secciones ya armadas: la CABECERA y el PIE del correo.
 *
 * No son tipos de bloque nuevos, son **secciones prearmadas** hechas con los
 * bloques que ya existen. Es la lección que dejó Páginas al retirar sus
 * `siteHeader`/`siteFooter`: un tipo monolítico con veinte campos no se puede
 * mover, ni reordenar, ni editar como lo que es, y en el lienzo aparece como una
 * fila opaca. Aquí la cabecera se suelta hecha, pero por dentro son un logo y un
 * divisor, y cada pieza se edita por su cuenta.
 *
 * Viven en código y no en una tabla porque no tienen dueño: son estructura, no
 * datos de una empresa.
 *
 * **El pie NO lleva el enlace de baja ni la dirección postal.** Esos los añade
 * `MailerService::renderBody()` a todo correo que sale, porque son obligatorios
 * (CAN-SPAM) y no pueden depender de que alguien se acuerde de ponerlos.
 * Repetirlos aquí saldría dos veces.
 */
class SectionLibrary
{
    /** Las secciones ofrecidas, en el orden en que se pintan. */
    public static function all(): array
    {
        return [
            'header' => [
                'label'   => lang('Mailer.presetHeader'),
                'hint'    => lang('Mailer.presetHeaderHint'),
                'icon'    => 'icon-buildings',
                // La cabecera va ARRIBA del todo; el pie, al final. Lo decide la
                // biblioteca y no quien la suelta, que es lo que significan.
                'at'      => 'start',
                'section' => self::header(),
            ],
            'footer' => [
                'label'   => lang('Mailer.presetFooter'),
                'hint'    => lang('Mailer.presetFooterHint'),
                'icon'    => 'icon-geo-alt',
                'at'      => 'end',
                'section' => self::footer(),
            ],
        ];
    }

    /** Cabecera: el logo de la empresa y una línea. */
    public static function header(): array
    {
        return [
            'cols'   => 1,
            'bg'     => '#ffffff',
            'pad'    => 24,
            'blocks' => [[
                self::block('logo', ['width' => 160, 'align' => 'center']),
                self::block('divider', ['color' => '#e5e7eb', 'thickness' => 1, 'gap' => 12]),
            ]],
        ];
    }

    /** Pie: redes y ficha de la empresa, sobre fondo gris para separarlo. */
    public static function footer(): array
    {
        return [
            'cols'   => 1,
            'bg'     => '#f4f4f5',
            'pad'    => 24,
            'blocks' => [[
                self::block('social', ['align' => 'center', 'size' => 24, 'color' => '#6b7280']),
                self::block('company', [
                    'showNit' => true, 'showAddress' => true, 'showContact' => true,
                    'align' => 'center', 'size' => 12, 'color' => '#6b7280',
                ]),
            ]],
        ];
    }

    /**
     * Un bloque del registro con algunos valores cambiados. Parte SIEMPRE de
     * `EmailRegistry::make()`: si mañana un bloque gana una propiedad, estas
     * secciones la heredan sin tocarlas.
     */
    private static function block(string $type, array $cambios = []): array
    {
        return array_merge(EmailRegistry::make($type) ?? ['type' => $type], $cambios);
    }

    /** Lo que consume el constructor: etiqueta, icono y el árbol a insertar. */
    public static function forJs(): array
    {
        $out = [];
        foreach (self::all() as $code => $def) {
            $out[$code] = [
                'label'   => $def['label'],
                'hint'    => $def['hint'],
                'icon'    => $def['icon'],
                'at'      => $def['at'],
                'section' => $def['section'],
            ];
        }

        return $out;
    }
}
