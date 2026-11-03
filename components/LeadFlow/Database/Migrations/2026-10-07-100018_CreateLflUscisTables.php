<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Caché de estados de caso y contador de consumo de USCIS.
 *
 * Son las dos piezas que convierten una integración que funciona en una que se puede
 * abrir al público sin que la primera tarde floja deje a la firma sin servicio.
 *
 * ── Por qué la caché es POR FIRMA ─────────────────────────────────────────────
 *
 * Una caché global gastaría menos llamadas: el mismo recibo consultado por dos firmas
 * se pagaría una sola vez. Se descarta a propósito, por lo mismo que las credenciales
 * son por firma: **el cupo de una no puede pagar las consultas de otra**. Además el
 * aislamiento por `company_id` es la regla de toda la plataforma, y una tabla que la
 * rompe «porque es dato público» es la que luego se usa para algo que no lo es.
 *
 * ── Por qué el contador es una tabla y no un recuento de la caché ─────────────
 *
 * Un acierto de caché NO es una llamada. Contar filas de la caché daría un consumo
 * mucho menor que el real y el cupo se desbordaría sin avisar. El contador cuenta lo
 * que de verdad salió a la red, por día natural, que es como lo cuenta USCIS.
 */
class CreateLflUscisTables extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_case_status')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'receipt'      => ['type' => 'VARCHAR', 'constraint' => 13],
                'form_type'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'status_es'    => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'detail'       => ['type' => 'TEXT', 'null' => true],
                'detail_es'    => ['type' => 'TEXT', 'null' => true],
                'submitted_at' => ['type' => 'DATE', 'null' => true],
                'case_updated' => ['type' => 'DATE', 'null' => true],
                // TEXT + json_encode, no JSON nativo: el componente es portable a
                // MySQL, PostgreSQL y SQL Server.
                'history'      => ['type' => 'TEXT', 'null' => true],
                // Cuándo se preguntó a USCIS. Es lo que decide si el dato está fresco,
                // y lo que se le enseña al visitante cuando se sirve uno viejo.
                'checked_at'   => ['type' => 'DATETIME', 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            // Un recibo, una fila por firma: sin el UNIQUE, dos consultas simultáneas
            // del mismo caso dejarían dos filas y la caché nunca acertaría.
            $this->forge->addUniqueKey(['company_id', 'receipt']);
            $this->forge->addKey('checked_at');
            $this->forge->createTable('cmp_lfl_case_status', true);
        }

        if (! $this->db->tableExists('cmp_lfl_uscis_usage')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                // Día natural, como lo cuenta USCIS. DATE y no DATETIME: el cupo se
                // repone a medianoche, no 24 horas después de la primera consulta.
                'day'        => ['type' => 'DATE'],
                'calls'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'day']);
            $this->forge->createTable('cmp_lfl_uscis_usage', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_uscis_usage', true);
        $this->forge->dropTable('cmp_lfl_case_status', true);
    }
}
