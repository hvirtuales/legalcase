<?php

namespace Components\Paginas\Libraries;

use Components\Paginas\Models\TemplateModel;

/**
 * Plantillas de arranque: las que ya están ahí la primera vez que alguien entra,
 * para que nadie empiece con un lienzo en blanco.
 *
 * Son **globales** (`company_id` NULL) porque son estructura, no contenido: los
 * textos son de relleno y se cambian al usarlas. Las que una empresa guarda
 * desde sus propias páginas sí llevan su `company_id` y solo las ve ella.
 *
 * La siembra es idempotente por `code`: se puede repetir sin duplicar. La
 * dispara el botón «Recuperar plantillas de arranque» de la pantalla de
 * plantillas — a propósito **no** se auto-siembra al listar: una escritura
 * escondida en un GET rompe la idempotencia de la lectura y obliga a comprobar
 * en cada visita algo que casi siempre ya está hecho.
 */
class TemplateLibrary
{
    /** @return int cuántas se crearon */
    public function seed(): int
    {
        $model = new TemplateModel();
        $count = 0;

        foreach ($this->definitions() as $def) {
            $exists = $model->where('code', $def['code'])->where('company_id', null)->first();
            if ($exists) {
                continue;
            }
            $model->insert([
                'company_id'  => null,
                'code'        => $def['code'],
                'name'        => $def['name'],
                'description' => $def['description'],
                'category'    => $def['category'],
                'blocks'      => $def['blocks'],
                'settings'    => $def['settings'] ?? [],
                'sort_order'  => $def['sort_order'],
            ]);
            $count++;
        }

        return $count;
    }

    /** ¿Hay alguna plantilla de arranque ya sembrada? */
    public function seeded(): bool
    {
        return (new TemplateModel())->where('company_id', null)->countAllResults() > 0;
    }

    /**
     * Las cuatro plantillas de arranque. Los textos salen de `Language/`, así
     * que una empresa en inglés recibe la plantilla en inglés.
     */
    /**
     * El catálogo de arranque está **vacío a propósito**.
     *
     * Había cinco plantillas sembradas (blanco, landing, quiénes somos,
     * contacto y perfil ejecutivo). Se retiraron porque el diseño no daba la
     * talla y una plantilla fea es peor que ninguna: la primera impresión de
     * quien entra a montar su web sale de ahí. Al crear una página se ofrece
     * **empezar en blanco** o partir de una de las que la empresa haya
     * guardado desde sus propias páginas, que son las que sí valen.
     *
     * Lo que se conserva es la maquinaria (`seed()` es idempotente por
     * `code`): añadir aquí un juego nuevo y volver a sembrar es todo lo que
     * haría falta. La migración `RemoveStarterTemplates` retira las que ya
     * estaban en la base.
     */
    private function definitions(): array
    {
        return [];
    }
}
