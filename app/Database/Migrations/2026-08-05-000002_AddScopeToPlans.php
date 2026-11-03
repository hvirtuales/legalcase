<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ámbito del plan: los planes de **tenant** y los de **empresa** no son la misma
 * cosa y no se pueden mezclar.
 *
 *   - `scope = 'company'` (por defecto, todos los planes que ya existen): lo de
 *     siempre. Sus banderas y cupos son los de los componentes de ámbito empresa
 *     (Contabilidad, Servicios, Marketplace, PQRSF, Networking…).
 *   - `scope = 'tenant'`: lo que contrata el centro comercial o la cámara. Sus
 *     banderas son las de los componentes de **ámbito tenant**, y `max_companies`
 *     pasa a significar **cuántas empresas puede agrupar**.
 *
 * El formulario de planes muestra las banderas del ámbito que corresponda, así
 * que un plan de tenant no ofrece «Comprobantes» ni un plan de empresa ofrece
 * los cupos del grupo.
 *
 * Los planes existentes quedan como `company`: es el comportamiento de hoy y
 * nada cambia para ellos.
 */
class AddScopeToPlans extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('plans') || $this->db->fieldExists('scope', 'plans')) {
            return;
        }

        // VARCHAR + validación en el modelo (nada de ENUM: portabilidad §8).
        $this->forge->addColumn('plans', [
            'scope' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'company'],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('scope', 'plans')) {
            $this->forge->dropColumn('plans', 'scope');
        }
    }
}
