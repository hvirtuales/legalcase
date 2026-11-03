<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El diseño de la campaña, como árbol.
 *
 * Las plantillas ya guardaban `design_json` (migración 100008), pero la campaña
 * solo tenía `body_html`: por eso su pantalla era un `<textarea>` de HTML crudo
 * mientras la plantilla tenía editor visual. Sin esta columna no hay dónde
 * guardar lo que monta el constructor.
 *
 * `body_html` NO se retira: sigue siendo lo que se envía, y ahora lo produce el
 * servidor a partir de este árbol (`EmailRenderer`). Las campañas antiguas, que
 * solo tienen HTML, se siguen enviando igual.
 *
 * Portabilidad (contrato 8): TEXT y json_encode/decode, nada de JSON nativo.
 */
class AddDesignJsonToMailerCampaigns extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_mailer_campaigns')) {
            return;
        }
        if ($this->db->fieldExists('design_json', 'cmp_mailer_campaigns')) {
            return;
        }

        $this->forge->addColumn('cmp_mailer_campaigns', [
            'design_json' => ['type' => 'TEXT', 'null' => true, 'after' => 'body_html'],
        ]);
        $this->db->resetDataCache();
    }

    public function down()
    {
        if ($this->db->tableExists('cmp_mailer_campaigns')
            && $this->db->fieldExists('design_json', 'cmp_mailer_campaigns')) {
            $this->forge->dropColumn('cmp_mailer_campaigns', 'design_json');
        }
    }
}
