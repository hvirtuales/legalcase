<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El formulario. Su contenido es un ARBOL JSON, no columnas fijas: quien lo arma
 * decide que preguntas tiene, asi que no puede haber una columna por campo.
 *
 * Dos esquemas y no uno:
 *   - `schema_json`      lo que se esta editando.
 *   - `published_schema` lo que ven quienes responden.
 * Editar un formulario vivo mientras alguien lo esta contestando no puede
 * cambiarle las preguntas a mitad. Publicar copia el primero al segundo y sube
 * `version`, que la respuesta guarda para saber a que version contesto.
 *
 * `public_token` (32 bytes aleatorios en hex) y NO el id en la URL publica: con
 * el id se podrian recorrer los formularios de todas las empresas.
 *
 * Portabilidad (contrato 8): sin ENUM (VARCHAR + validacion en el modelo) y sin
 * JSON nativo (TEXT + json_encode/decode via el trait `JsonColumns`).
 */
class CreateFrmFormsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_frm_forms')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 180],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 180],
            'public_token'     => ['type' => 'VARCHAR', 'constraint' => 64],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'schema_json'      => ['type' => 'TEXT', 'null' => true],
            'published_schema' => ['type' => 'TEXT', 'null' => true],
            'settings'         => ['type' => 'TEXT', 'null' => true],
            'version'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'password_hash'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'opens_at'         => ['type' => 'DATETIME', 'null' => true],
            'closes_at'        => ['type' => 'DATETIME', 'null' => true],
            'max_responses'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'allow_multiple'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'views'            => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'published_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('public_token');
        $this->forge->addKey(['company_id', 'deleted_at', 'created_at']);
        $this->forge->addKey(['company_id', 'slug']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_frm_forms');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_frm_forms', true);
    }
}
