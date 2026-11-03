<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Documentos asociados al inmueble — y, por el mismo precio, a la persona, al
 * vehículo, a la mascota y a la autorización.
 *
 * Tabla **polimórfica** (`owner_type` + `owner_id`), como manda el contrato §11 para
 * los adjuntos. Se hizo así y no con cinco tablas de adjuntos porque el trabajo es
 * idéntico en los cinco casos —validar, guardar fuera de `public/`, servir por
 * controlador— y cinco copias del mismo código son cinco sitios donde olvidarse de
 * comprobar la empresa.
 *
 * Los archivos viven en `writable/uploads/copropiedad`, **fuera de `public/`**: una
 * escritura o una cédula no pueden quedar en una URL que se adivine. La descarga va
 * por `Controllers\Documents::download()`, que comprueba permiso **y** que el dueño
 * del documento pertenezca a la empresa activa.
 *
 * `expires_at` existe porque media carpeta de una copropiedad caduca (SOAT,
 * tecnicomecánica, póliza de áreas comunes, contrato de arriendo, carné de vacunas);
 * el panel avisa de lo que vence en 30 días.
 */
class CreateCopDocumentsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_documents')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // unit|person|vehicle|pet|authorization (validado en el modelo)
            'owner_type'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'owner_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'kind'          => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'otro'],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 140],
            'file_path'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'size'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'issued_at'     => ['type' => 'DATE', 'null' => true],
            'expires_at'    => ['type' => 'DATE', 'null' => true],
            'uploaded_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'notes'         => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // «Los documentos de este inmueble»: el acceso natural.
        $this->forge->addKey(['company_id', 'owner_type', 'owner_id', 'deleted_at']);
        // «Qué se vence este mes».
        $this->forge->addKey(['company_id', 'expires_at']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_cop_documents');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_documents', true);
    }
}
