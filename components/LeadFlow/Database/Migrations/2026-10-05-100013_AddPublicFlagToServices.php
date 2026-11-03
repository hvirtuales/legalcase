<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `is_public` en el catálogo de servicios.
 *
 * El catálogo nació como configuración interna: clasifica leads, elige formulario,
 * asigna responsable y guarda el tipo de Matter de Docketwise. La ficha pública de la
 * firma necesita enseñar sus servicios, pero eso **no convierte el catálogo en una
 * carta**: ahí dentro puede haber nombres de uso interno, servicios en preparación o
 * tarifas que la firma no quiere anunciar.
 *
 * Por eso nace en **0**, igual que un perfil nace en borrador y hay que publicarlo. La
 * firma decide qué enseña, servicio por servicio. El coste es que el bloque aparece
 * vacío hasta que alguien lo marca; la alternativa era publicar de golpe el catálogo
 * entero de todas las firmas que ya existen, que es una decisión que no es nuestra.
 *
 * Idempotente y por Forge, como todas: se puede re-ejecutar sobre una BD recreada.
 */
class AddPublicFlagToServices extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_services')) {
            return;
        }

        if (! $this->db->fieldExists('is_public', 'cmp_lfl_services')) {
            $this->forge->addColumn('cmp_lfl_services', [
                'is_public' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if ($this->db->tableExists('cmp_lfl_services') && $this->db->fieldExists('is_public', 'cmp_lfl_services')) {
            $this->forge->dropColumn('cmp_lfl_services', 'is_public');
            $this->db->resetDataCache();
        }
    }
}
