<?php

namespace Components\Paginas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retira las plantillas de arranque sembradas (`company_id` NULL).
 *
 * Eran cinco (blanco, landing, quiénes somos, contacto y perfil ejecutivo) y se
 * quitan porque el diseño no daba la talla: una plantilla fea es peor que
 * ninguna, porque es la primera impresión de quien entra a montar su web. Al
 * crear una página se ofrece empezar **en blanco** o partir de una de las que
 * la propia empresa haya guardado.
 *
 * **Solo toca las globales.** Las de cada empresa (`company_id` no nulo) son
 * suyas y no se rozan.
 *
 * `down()` no las devuelve a propósito: el catálogo que las generaba ya no
 * existe (`TemplateLibrary::definitions()` está vacío), así que recrearlas aquí
 * sería duplicar en una migración un contenido que se decidió retirar.
 */
class RemoveStarterTemplates extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_pag_templates')) {
            return;
        }

        $this->db->table('cmp_pag_templates')->where('company_id', null)->delete();
    }

    public function down()
    {
        // Ver la nota de arriba: no se recrean.
    }
}
