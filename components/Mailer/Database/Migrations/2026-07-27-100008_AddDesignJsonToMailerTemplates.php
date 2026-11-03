<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Guarda el diseño del editor drag-and-drop (JSON) para poder reeditar la plantilla. Idempotente. */
class AddDesignJsonToMailerTemplates extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('design_json', 'cmp_mailer_templates')) {
            $this->forge->addColumn('cmp_mailer_templates', [
                'design_json' => ['type' => 'TEXT', 'null' => true, 'after' => 'body_html'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('design_json', 'cmp_mailer_templates')) {
            $this->forge->dropColumn('cmp_mailer_templates', 'design_json');
        }
    }
}
