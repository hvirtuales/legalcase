<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La etapa terminal del embudo deja de llamarse por su destino.
 *
 * Con el expediente interno, salir del embudo ya no significa «transferido a
 * Docketwise»: significa que el lead se convirtió en un caso. El código de la etapa
 * (`converted`) y su `kind` NO cambian —de ellos dependen `StageModel::byCode()`,
 * `ConversionService` y `MatterService`—; cambia solo el rótulo.
 *
 * ── Por qué hace falta una migración y no basta la clave de idioma ───────────
 *
 * `Catalog::ensure()` siembra el nombre resolviendo `lang()` UNA VEZ, al crear la
 * firma, y lo guarda como literal en `cmp_lfl_stages.name`. Cambiar la clave de
 * idioma no toca lo ya sembrado.
 *
 * La prueba está en esta misma instalación: la clave `Lfl.stgConverted` decía
 * «Convertido a Docketwise» mientras la fila de la firma demo decía «Transferido a
 * Docketwise». Son dos redacciones distintas de la misma clave en dos momentos, así
 * que hay que contemplar LAS CUATRO (dos redacciones × dos idiomas).
 *
 * ── Por qué se respeta un nombre que no coincida ─────────────────────────────
 *
 * Si la firma renombró la etapa a mano, ninguno de los cuatro literales coincide y la
 * fila se queda como está. Eso no es un fallo de la migración: la etapa es
 * configurable, y pisar el nombre que alguien eligió sería perderlo sin avisar.
 *
 * El renombrado preserva el idioma del literal que encuentra, en vez de imponer el
 * locale de quien ejecuta la migración por consola.
 */
class RenameConvertedStage extends Migration
{
    /** Literal antiguo => literal nuevo, en el mismo idioma. */
    private const RENAMES = [
        'Transferido a Docketwise'   => 'Expediente abierto',
        'Convertido a Docketwise'    => 'Expediente abierto',
        'Transferred to Docketwise'  => 'Case opened',
        'Converted to Docketwise'    => 'Case opened',
    ];

    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_stages')) {
            return;
        }

        foreach (self::RENAMES as $viejo => $nuevo) {
            $this->db->table('cmp_lfl_stages')
                ->where('code', 'converted')
                ->where('name', $viejo)
                ->update(['name' => $nuevo]);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('cmp_lfl_stages')) {
            return;
        }

        // Se repone la redacción que tenía la clave de idioma antes del cambio, no la
        // de la siembra más antigua: es la que volvería a escribir `Catalog::ensure()`.
        foreach (['Expediente abierto' => 'Convertido a Docketwise',
            'Case opened' => 'Converted to Docketwise'] as $nuevo => $viejo) {
            $this->db->table('cmp_lfl_stages')
                ->where('code', 'converted')
                ->where('name', $nuevo)
                ->update(['name' => $viejo]);
        }
    }
}
