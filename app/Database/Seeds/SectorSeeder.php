<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Sectores de arranque.
 *
 * Un catálogo vacío no sirve de nada: sin sectores no se puede clasificar
 * ninguna empresa y el Directorio se queda sin su modo «por sector». Estos
 * doce cubren lo que se ve en un centro comercial y en una cámara de comercio,
 * que son los dos casos para los que existe el nivel tenant.
 *
 * Es **idempotente**: se reconoce por el slug, así que volver a sembrar no
 * duplica ni pisa lo que el Super Admin haya renombrado.
 */
class SectorSeeder extends Seeder
{
    public function run()
    {
        $sectores = [
            ['Alimentos y bebidas', 'icon-shop', '#e11d48'],
            ['Moda y calzado', 'icon-bag', '#db2777'],
            ['Tecnología', 'icon-cpu', '#2563eb'],
            ['Salud y bienestar', 'icon-heart', '#059669'],
            ['Educación', 'icon-journal-text', '#7c3aed'],
            ['Servicios profesionales', 'icon-briefcase', '#0891b2'],
            ['Construcción', 'icon-tools', '#ea580c'],
            ['Hogar y decoración', 'icon-house', '#a16207'],
            ['Transporte y logística', 'icon-truck', '#475569'],
            ['Finanzas y seguros', 'icon-bank', '#1d4ed8'],
            ['Turismo y ocio', 'icon-globe', '#0d9488'],
            ['Industria', 'icon-gear', '#57534e'],
        ];

        /*
         * Los iconos tienen que EXISTIR en `public/css/icons.css`.
         *
         * El catálogo de la plataforma es un juego cerrado de reglas CSS, no una
         * fuente de iconos con todos los nombres imaginables: un `icon-` que no
         * esté ahí no da error, simplemente **no pinta nada**, y el sector se
         * queda con un hueco que nadie relaciona con el nombre del icono. Pasó
         * con siete de estos doce.
         *
         * Se avisa aquí en vez de fallar: el sector es útil igual sin icono, y
         * un seeder que se niega a sembrar por un adorno sería peor. Para
         * añadir uno nuevo, ver «Iconos» en CLAUDE.md.
         */
        $css     = @file_get_contents(FCPATH . 'css/icons.css') ?: '';
        $sinIcono = [];

        foreach ($sectores as [$nombre, $icono, $color]) {
            if ($css !== '' && !str_contains($css, '.' . $icono . '{')) {
                $sinIcono[] = $icono;
            }
        }

        if ($sinIcono) {
            echo "AVISO: estos iconos no existen en public/css/icons.css y no se verán: "
                . implode(', ', array_unique($sinIcono)) . "\n";
        }

        $model = new \App\Models\SectorModel();
        $orden = 10;
        $nuevos = 0;

        foreach ($sectores as [$nombre, $icono, $color]) {
            // Se reconoce por el nombre: si ya está, no se toca —puede tener
            // cambios del Super Admin— y se sigue.
            if ($model->where('name', $nombre)->first()) {
                $orden += 10;

                continue;
            }

            $model->skipValidation(true);
            $model->insert([
                'name'       => $nombre,
                'slug'       => $model->uniqueSlug($nombre),
                'icon'       => $icono,
                'color'      => $color,
                'sort_order' => $orden,
                'is_active'  => 1,
            ]);
            $model->skipValidation(false);
            $orden += 10;
            $nuevos++;
        }

        if (defined('CI_ENVIRONMENT')) {
            echo "Sectores sembrados: {$nuevos} nuevos.\n";
        }
    }
}
