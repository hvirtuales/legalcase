<?php

namespace Components\LegalCase\Database\Migrations;

use CodeIgniter\Database\Migration;
use Components\LegalCase\Database\Columns;

/**
 * Documentos del asunto y solicitudes de documentos al cliente.
 *
 * El archivo vive en `writable/uploads/lgc/<empresa>/`, fuera de `public/`, y se
 * sirve siempre por un controlador que comprueba permiso, empresa y asunto (o, en
 * el portal, que la cuenta tenga acceso a ese asunto y que el documento esté
 * clasificado como compartido).
 *
 * `classification` (documento maestro §6.9): internal | privileged | review | shared.
 * Solo `shared` llega al portal. El valor por defecto es `internal`: nada se
 * comparte por accidente.
 */
class CreateLgcDocumentTables extends Migration
{
    use Columns;

    public function up()
    {
        if (!$this->db->tableExists('cmp_lgc_doc_requests')) {
            $this->forge->addField([
                'id'           => $this->id(),
                'company_id'   => $this->fk(),
                'matter_id'    => $this->fk(),
                'title'        => $this->str(190),
                'instructions' => $this->text(),
                'due_at'       => $this->dt(),
                'status'       => $this->str(20, 'pending'),
                'review_note'  => $this->str(255),
                'submitted_at' => $this->dt(),
                'reviewed_by'  => $this->fk(true),
                'reviewed_at'  => $this->dt(),
                'created_by'   => $this->fk(true),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey('matter_id');
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_doc_requests');
        }

        if (!$this->db->tableExists('cmp_lgc_documents')) {
            $this->forge->addField([
                'id'                  => $this->id(),
                'company_id'          => $this->fk(),
                'matter_id'           => $this->fk(),
                // Sin FK: la solicitud se puede anular y el documento entregado se conserva.
                'request_id'          => $this->fk(true),
                'name'                => $this->str(190),
                'path'                => $this->str(255),
                'mime'                => $this->str(120),
                'size'                => $this->int(0),
                'sha256'              => $this->str(64),
                'classification'      => $this->str(20, 'internal'),
                'source'              => $this->str(10, 'staff'),
                'uploaded_by'         => $this->fk(true),
                'uploaded_by_account' => $this->fk(true),
                'shared_by'           => $this->fk(true),
                'shared_at'           => $this->dt(),
            ] + $this->stamps());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['matter_id', 'classification']);
            $this->forge->addKey(['company_id', 'sha256']);
            $this->forge->addForeignKey('matter_id', 'cmp_lgc_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lgc_documents');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lgc_documents', true);
        $this->forge->dropTable('cmp_lgc_doc_requests', true);
    }
}
