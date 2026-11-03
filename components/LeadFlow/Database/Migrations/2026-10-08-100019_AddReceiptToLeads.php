<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El número de recibo de USCIS en el lead.
 *
 * Es el uso que más valor da de toda la integración: un prospecto escribe «mi caso
 * lleva meses parado» y, con su recibo, el asesor llega a la primera llamada sabiendo
 * el tipo de formulario, desde cuándo está en trámite y en qué punto va. Sin eso, la
 * primera llamada se gasta en averiguar lo que USCIS ya publica.
 *
 * **Se guarda en claro, a propósito.** Es dato personal y la tentación es cifrarlo,
 * pero sería teatro: `cmp_lfl_case_status` tiene que guardarlo en claro por fuerza
 * —busca por él— y la misma aplicación descifraría ambas. Cifrar solo este lado daría
 * sensación de protección sin añadir ninguna. Lo que de verdad lo protege es lo que ya
 * existe: el aislamiento por `company_id`, el alcance de `Access` y la purga de la
 * caché.
 *
 * `uscis_checked_at` recuerda cuándo se miró por última vez, para que `leadflow:tick`
 * sepa a cuáles les toca y no gaste cupo repitiendo los mismos.
 */
class AddReceiptToLeads extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_leads')) {
            return;
        }

        $nuevas = [];
        if (! $this->db->fieldExists('uscis_receipt', 'cmp_lfl_leads')) {
            $nuevas['uscis_receipt'] = ['type' => 'VARCHAR', 'constraint' => 13, 'null' => true];
        }
        if (! $this->db->fieldExists('uscis_status', 'cmp_lfl_leads')) {
            // Copia del último estado conocido, para listar y filtrar sin cruzar con la
            // caché en cada fila.
            $nuevas['uscis_status'] = ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true];
        }
        if (! $this->db->fieldExists('uscis_checked_at', 'cmp_lfl_leads')) {
            $nuevas['uscis_checked_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        if ($nuevas !== []) {
            $this->forge->addColumn('cmp_lfl_leads', $nuevas);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('cmp_lfl_leads')) {
            return;
        }
        foreach (['uscis_receipt', 'uscis_status', 'uscis_checked_at'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_lfl_leads')) {
                $this->forge->dropColumn('cmp_lfl_leads', $col);
            }
        }
        $this->db->resetDataCache();
    }
}
