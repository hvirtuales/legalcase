<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La calificación, desnormalizada en el perfil.
 *
 * El buscador **ordena en PHP**, sobre las filas ya traídas, y las opiniones se
 * decoran después de ordenar: sin estos dos campos, ordenar por calificación obligaría
 * a consultar las opiniones de cada perfil antes de poder decidir el orden.
 *
 * Va en `cmp_lfl_profiles` —tabla propia— y no en `companies`, que es del núcleo y el
 * contrato prohíbe tocar. Es la diferencia con la vitrina de referencia, que sí
 * desnormaliza en `companies` porque allí la vitrina ES el núcleo.
 *
 * **`rating_avg` queda en NULL sin opiniones, nunca en 0**: «no se sabe» y «lo
 * puntuaron con un cero» son cosas distintas, y las vistas las pintan distinto.
 *
 * Se rellena al vuelo con lo que ya haya publicado, para que una instalación con
 * opiniones no tenga que esperar a la próxima moderación para ordenar bien.
 */
class AddRatingToProfiles extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_profiles')) {
            return;
        }

        $nuevas = [];
        if (! $this->db->fieldExists('rating_avg', 'cmp_lfl_profiles')) {
            $nuevas['rating_avg'] = ['type' => 'DECIMAL', 'constraint' => '3,2', 'null' => true];
        }
        if (! $this->db->fieldExists('rating_count', 'cmp_lfl_profiles')) {
            $nuevas['rating_count'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0];
        }
        if ($nuevas !== []) {
            $this->forge->addColumn('cmp_lfl_profiles', $nuevas);
            $this->db->resetDataCache();
        }

        // Relleno inicial, solo si ya existe la tabla de opiniones.
        if (! $this->db->tableExists('cmp_lfl_reviews')) {
            return;
        }

        foreach ($this->db->table('cmp_lfl_reviews')
            ->select('profile_id, AVG(score) AS p, COUNT(*) AS n')
            ->where('status', 'published')
            ->where('profile_id IS NOT NULL', null, false)
            ->where('score IS NOT NULL', null, false)
            ->groupBy('profile_id')->get()->getResultArray() as $r) {
            $this->db->table('cmp_lfl_profiles')->where('id', (int) $r['profile_id'])->update([
                'rating_avg'   => round((float) $r['p'], 2),
                'rating_count' => (int) $r['n'],
            ]);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('cmp_lfl_profiles')) {
            return;
        }
        foreach (['rating_avg', 'rating_count'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_lfl_profiles')) {
                $this->forge->dropColumn('cmp_lfl_profiles', $col);
            }
        }
        $this->db->resetDataCache();
    }
}
