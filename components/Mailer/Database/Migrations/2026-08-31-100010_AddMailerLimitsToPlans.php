<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bandera y cupo del componente en `plans` (contrato 11).
 *
 * FALTABA: el Manifest declaraba `plan_flag => mailer_enabled` y el `PlanGuard`
 * leía `mailer_enabled` y `max_emails`, pero **ninguna migración creaba esas
 * columnas**. El formulario de planes no podía graduar el servicio —el núcleo
 * solo ofrece las columnas que existen de verdad— y el guardia leía una clave
 * ausente, con lo que el componente quedaba apagado para toda empresa con plan.
 *
 * `plans` es del núcleo, pero LA COLUMNA ES NUESTRA: por eso la migración vive
 * aquí y se revierte con el componente. Solo se AÑADEN columnas propias, y se
 * comprueba `tableExists('plans')` porque el orden entre namespaces no está
 * garantizado.
 *
 * `max_emails` es el tope de correos AL MES (0 = ilimitado); lo cuenta
 * `PlanGuard::sentThisMonth()` sobre los destinatarios ya enviados.
 */
class AddMailerLimitsToPlans extends Migration
{
    private const COLUMNS = ['mailer_enabled', 'max_emails'];

    public function up()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        $fields = [];
        if (!$this->db->fieldExists('mailer_enabled', 'plans')) {
            $fields['mailer_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_emails', 'plans')) {
            $fields['max_emails'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0];
        }

        if ($fields) {
            $this->forge->addColumn('plans', $fields);
            // `fieldExists()` cachea los nombres de columna: sin esto, cualquier
            // pregunta posterior recibiría la lista de ANTES del ALTER.
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        foreach (self::COLUMNS as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
