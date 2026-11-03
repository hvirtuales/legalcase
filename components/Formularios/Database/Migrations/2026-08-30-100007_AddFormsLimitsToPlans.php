<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bandera y cupos del componente en `plans` (contrato 11).
 *
 * `plans` es una tabla del núcleo, pero LA COLUMNA ES NUESTRA: por eso la
 * migración vive aquí y no en `app/`. Así se instala y se revierte con el
 * componente, y una instalación que no lo tenga no arrastra columnas de un
 * módulo que nadie instaló.
 *
 * Dos reglas para no pisar al núcleo: solo se AÑADEN columnas propias (nunca se
 * redefine la tabla) y se comprueba `tableExists('plans')` al entrar, porque el
 * orden de ejecución entre namespaces no está garantizado.
 *
 * `0` = ilimitado, en los dos cupos.
 */
class AddFormsLimitsToPlans extends Migration
{
    private const COLUMNS = ['forms_enabled', 'max_forms', 'max_responses'];

    public function up()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        $fields = [];
        if (!$this->db->fieldExists('forms_enabled', 'plans')) {
            $fields['forms_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_forms', 'plans')) {
            $fields['max_forms'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0];
        }
        if (!$this->db->fieldExists('max_responses', 'plans')) {
            $fields['max_responses'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0];
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
