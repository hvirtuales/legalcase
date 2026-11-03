<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lista blanca: qué planes de EMPRESA puede repartir cada tenant.
 *
 * El administrador del centro comercial asigna plan a sus locales, pero no
 * elige del catálogo completo: solo de los planes que el Super Admin le habilitó
 * aquí. Sin esta tabla nada le impediría ponerle a sus doce empresas el plan más
 * caro sin haberlo contratado.
 *
 *   - `tenant_id`  fila de `companies` con `type = 'tenant'`
 *   - `plan_id`    plan de `scope = 'company'` que puede repartir
 *   - `is_default` el que recibe automáticamente una empresa recién colgada
 *
 * Se borra en cascada con el tenant y con el plan: si el plan desaparece del
 * catálogo, desaparece de todas las listas blancas.
 */
class CreateTenantPlansTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tenant_plans')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'plan_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'plan_id']);
        $this->forge->addForeignKey('tenant_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('plan_id', 'plans', 'id', '', 'CASCADE');
        $this->forge->createTable('tenant_plans', true);
    }

    public function down()
    {
        $this->forge->dropTable('tenant_plans', true);
    }
}
