<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Models\AreaModel;
use Components\LeadFlow\Models\JurisdictionModel;

/**
 * Siembra el vocabulario del directorio: las áreas de práctica y las jurisdicciones.
 *
 * **Siembra perezosa y global**, no por firma: se llama al entrar a las pantallas
 * del directorio y a las páginas públicas, y cada bloque solo se siembra si está
 * vacío. Es el mismo criterio que `Catalog::ensure()` para los catálogos de una
 * firma, y por el mismo motivo: los eventos de ciclo de vida del contrato no se
 * disparan en el núcleo, así que no se puede sembrar «al instalar».
 *
 * El nombre visible de un área sale de su clave de idioma **la primera vez**, y a
 * partir de ahí vive en la fila: un administrador puede renombrarla sin que la
 * siguiente visita se lo pise. Igual que las etapas del pipeline.
 */
class Taxonomy
{
    private static bool $done = false;

    public static function ensure(): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        $areas = new AreaModel();
        if ($areas->countAllResults() === 0) {
            foreach (AreaModel::SYSTEM as $code => [$slug, $sort]) {
                $areas->insert([
                    'code'      => $code,
                    'slug'      => $slug,
                    'name'      => lang('Lfl.area_' . $code),
                    'sort'      => $sort,
                    'is_active' => 1,
                ]);
            }
        }

        $jur = new JurisdictionModel();
        if ($jur->countAllResults() === 0) {
            foreach (JurisdictionModel::SYSTEM as $code => [$name, $country, $sort]) {
                $jur->insert([
                    'code'      => $code,
                    'name'      => $name,
                    'country'   => $country,
                    'sort'      => $sort,
                    'is_active' => 1,
                ]);
            }
        }
    }

    /** Para las pruebas y el CLI: olvida que ya se sembró. */
    public static function flush(): void
    {
        self::$done = false;
    }
}
