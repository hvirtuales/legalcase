<?php

namespace Components\Marketplace\Libraries;

use Components\Marketplace\Models\CategoryModel;

/**
 * Datos semilla del catálogo: las **categorías base** con las que arranca el
 * marketplace. Se siembran al instalar el componente y se pueden restaurar desde
 * «Mis publicaciones» si alguien las desactiva.
 *
 * Son globales a propósito: si cada empresa inventara las suyas, el catálogo
 * dejaría de ser comparable y la búsqueda por categoría no cruzaría oferta con
 * demanda entre empresas distintas.
 */
class CatalogService
{
    /** [slug, nombre, icono] — orden de presentación = orden del array. */
    public const BASE_CATEGORIES = [
        ['tecnologia-software', 'Tecnología y software', 'icon-cpu'],
        ['servicios-profesionales', 'Servicios profesionales', 'icon-person-badge'],
        ['contabilidad-finanzas', 'Contabilidad y finanzas', 'icon-calculator'],
        ['legal', 'Legal y jurídico', 'icon-journal-text'],
        ['marketing-publicidad', 'Marketing y publicidad', 'icon-star'],
        ['recursos-humanos', 'Recursos humanos', 'icon-people'],
        ['logistica-transporte', 'Logística y transporte', 'icon-box-seam'],
        ['construccion-inmuebles', 'Construcción e inmuebles', 'icon-buildings'],
        ['manufactura-industria', 'Manufactura e industria', 'icon-gear'],
        ['comercio-distribucion', 'Comercio y distribución', 'icon-cash-stack'],
        ['salud-bienestar', 'Salud y bienestar', 'icon-life-preserver'],
        ['educacion-formacion', 'Educación y formación', 'icon-book'],
        ['turismo-eventos', 'Turismo y eventos', 'icon-globe'],
        ['agroindustria', 'Agroindustria', 'icon-folder'],
        ['otros', 'Otros', 'icon-puzzle'],
    ];

    /**
     * Siembra las categorías que falten (idempotente: no toca las existentes ni
     * pisa los cambios de nombre/icono que haya hecho el administrador).
     *
     * @return int cuántas se crearon
     */
    public function seedCategories(): int
    {
        $model = new CategoryModel();
        $now   = date('Y-m-d H:i:s');
        $made  = 0;

        foreach (self::BASE_CATEGORIES as $i => [$slug, $name, $icon]) {
            if ($model->where('slug', $slug)->first()) {
                continue;
            }
            $model->insert([
                'name'       => $name,
                'slug'       => $slug,
                'icon'       => $icon,
                'sort_order' => ($i + 1) * 10,
                'is_active'  => 1,
                'created_at' => $now,
            ]);
            $made++;
        }

        return $made;
    }

    /** Reactiva las categorías base que estuvieran desactivadas y siembra las que falten. */
    public function restoreCategories(): int
    {
        $model  = new CategoryModel();
        $made   = $this->seedCategories();
        $slugs  = array_column(self::BASE_CATEGORIES, 0);
        $model->whereIn('slug', $slugs)->set('is_active', 1)->update();

        return $made;
    }
}
