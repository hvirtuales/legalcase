<?php

namespace Components\Paginas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Biblioteca de medios de la empresa.
 *
 * Las imágenes ya se subían —desde el inspector del editor— pero **no se
 * anotaba en ninguna parte que existieran**: una vez que un bloque dejaba de
 * apuntar a una, el archivo quedaba en el disco sin forma de encontrarlo, y no
 * había manera de reutilizar una imagen ya subida en otra página. Esta tabla es
 * ese registro.
 *
 * Guarda la **referencia** (`path`), no el archivo: puede ser una ruta relativa
 * (`/uploads/paginas/…`) o una URL absoluta si el almacenamiento es remoto
 * (S3/R2), exactamente lo mismo que se guarda en el bloque y que resuelve
 * `media_url()`.
 *
 * Portable (contrato §8): sin ENUM ni JSON nativo, y todo por Forge.
 */
class CreatePagMediaTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_pag_media')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // La referencia tal cual se guarda en el bloque.
            'path'       => ['type' => 'VARCHAR', 'constraint' => 500],
            // El nombre con el que llegó, para poder buscarla por él: el del
            // archivo guardado es un identificador y no dice nada.
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mime'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'width'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'height'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'bytes'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->createTable('cmp_pag_media');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_pag_media', true);
    }
}
