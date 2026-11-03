<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El INDICE de respuestas por campo.
 *
 * Es la única desnormalización del diseño y se justifica sola: con las
 * respuestas solo en JSON, contar cuántos eligieron la opción B o promediar un
 * NPS obliga a decodificar cada fila en PHP, que es lo que hacen hoy Páginas y
 * PQRSF (decenas de registros, no miles). Con esta tabla el informe agrupa en
 * SQL portable en los tres motores.
 *
 * `cmp_frm_responses.data` sigue siendo la verdad; esto es índice derivado y
 * desechable: se reconstruye entero en cada guardado de la respuesta.
 *
 * Tres columnas de valor y no una: `option_value` para agrupar (selección),
 * `value_num` para promediar (calificación, NPS, deslizador, número) y
 * `value_text` para lo demás. Así ninguna consulta tiene que castear.
 */
class CreateFrmAnswersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_frm_answers')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'response_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'form_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'field_id'     => ['type' => 'VARCHAR', 'constraint' => 64],
            'field_type'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => ''],
            'option_value' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'value_text'   => ['type' => 'TEXT', 'null' => true],
            'value_num'    => ['type' => 'DECIMAL', 'constraint' => '14,4', 'null' => true],
            'value_date'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['form_id', 'field_id']);
        $this->forge->addKey(['form_id', 'field_id', 'option_value']);
        $this->forge->addKey('response_id');
        $this->forge->addForeignKey('response_id', 'cmp_frm_responses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_frm_answers');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_frm_answers', true);
    }
}
