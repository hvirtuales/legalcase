<?php

namespace Components\Pqrsf\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dos cosas que faltaban:
 *
 * 1. **Los tipos dejan de estar en el código.** Eran una constante y sus plazos,
 *    una columna por tipo en `cmp_pqr_settings` — que es justo lo que impedía
 *    añadir uno nuevo. Pasan a `cmp_pqr_types`, por empresa. La solicitud sigue
 *    guardando el **código** (no una FK): un tipo que se borre o se renombre no
 *    puede reescribir lo que ya se radicó.
 * 2. **La respuesta puede llevar soportes.** `cmp_pqr_attachments.is_public`
 *    marca lo que el ciudadano puede descargar desde su ticket, y la solicitud
 *    guarda además un enlace externo (`answer_link`) para lo que no es un
 *    archivo: una guía de envío, un formulario, una página.
 *
 * Portable (§8): sin ENUM ni JSON nativo, todo por Forge, `up()` idempotente.
 */
class AddTypesAndAnswerLinks extends Migration
{
    /** Los cinco de siempre, que se siembran para cada empresa. */
    private const DEFAULTS = [
        ['peticion', 'Petición', 15, 1],
        ['queja', 'Queja', 15, 2],
        ['reclamo', 'Reclamo', 15, 3],
        ['sugerencia', 'Sugerencia', 30, 4],
        ['felicitacion', 'Felicitación', 30, 5],
    ];

    public function up()
    {
        // ---- Catálogo de tipos por empresa ---------------------------------
        if (!$this->db->tableExists('cmp_pqr_types')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'code'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
                'days'       => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'code']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_pqr_types');

            $this->seedFromSettings();
        }

        // ---- Adjuntos que el ciudadano puede ver ------------------------------
        if ($this->db->tableExists('cmp_pqr_attachments')
            && !$this->db->fieldExists('is_public', 'cmp_pqr_attachments')) {
            $this->forge->addColumn('cmp_pqr_attachments', [
                'is_public' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'size'],
            ]);
        }

        // ---- Enlace que acompaña a la respuesta --------------------------------
        if ($this->db->tableExists('cmp_pqr_requests') && !$this->db->fieldExists('answer_link', 'cmp_pqr_requests')) {
            $this->forge->addColumn('cmp_pqr_requests', [
                'answer_link'       => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => '', 'after' => 'answer'],
                'answer_link_label' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => '', 'after' => 'answer_link'],
            ]);
        }

        // ---- Los plazos ya viven en el catálogo -----------------------------------
        foreach (['days_peticion', 'days_queja', 'days_reclamo', 'days_sugerencia', 'days_felicitacion'] as $col) {
            if ($this->db->tableExists('cmp_pqr_settings') && $this->db->fieldExists($col, 'cmp_pqr_settings')) {
                $this->forge->dropColumn('cmp_pqr_settings', $col);
            }
        }
    }

    /**
     * Siembra los tipos de cada empresa que ya tenga configuración, respetando
     * los plazos que hubiera ajustado antes de esta migración.
     */
    private function seedFromSettings(): void
    {
        if (!$this->db->tableExists('cmp_pqr_settings')) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = $this->db->table('cmp_pqr_settings')->get()->getResultArray();

        foreach ($rows as $cfg) {
            $insert = [];

            foreach (self::DEFAULTS as [$code, $name, $days, $order]) {
                $insert[] = [
                    'company_id' => (int) $cfg['company_id'],
                    'code'       => $code,
                    'name'       => $name,
                    'days'       => (int) ($cfg['days_' . $code] ?? $days),
                    'sort_order' => $order,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $this->db->table('cmp_pqr_types')->insertBatch($insert);
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_pqr_types', true);

        if ($this->db->fieldExists('is_public', 'cmp_pqr_attachments')) {
            $this->forge->dropColumn('cmp_pqr_attachments', 'is_public');
        }
        foreach (['answer_link', 'answer_link_label'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_pqr_requests')) {
                $this->forge->dropColumn('cmp_pqr_requests', $col);
            }
        }

        // Devuelve los plazos a la configuración, con los valores por defecto.
        $cols = [];

        foreach (self::DEFAULTS as [$code, , $days]) {
            if (!$this->db->fieldExists('days_' . $code, 'cmp_pqr_settings')) {
                $cols['days_' . $code] = ['type' => 'INT', 'constraint' => 11, 'default' => $days];
            }
        }
        if ($cols) {
            $this->forge->addColumn('cmp_pqr_settings', $cols);
        }
    }
}
