<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A qué sector pertenece la empresa y **dónde está**.
 *
 * Las dos cosas son atributos de la empresa, de la misma naturaleza que
 * `address` y `city`, así que viven en `companies` y no en la tabla de ningún
 * componente. El Directorio las **lee**; el contrato de componentes de tenant
 * (§8) le prohíbe expresamente escribir en `companies`.
 *
 * Las coordenadas se guardan a mano y no se geocodifican: geocodificar exige
 * llamar a un servicio externo, y hacerlo al pintar la página convertiría cada
 * visita en una petición de red a un tercero (y en una página caída el día que
 * ese tercero no responda).
 *
 * DECIMAL y no FLOAT: `10,7` da ~1 cm de resolución y, a diferencia del coma
 * flotante, el valor que se guarda es el que se lee.
 */
class AddSectorAndLocationToCompanies extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('companies')) {
            return;
        }

        $campos = [];

        if (!$this->db->fieldExists('sector_id', 'companies')) {
            $campos['sector_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'city'];
        }
        if (!$this->db->fieldExists('latitude', 'companies')) {
            $campos['latitude'] = ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'address'];
        }
        if (!$this->db->fieldExists('longitude', 'companies')) {
            $campos['longitude'] = ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'latitude'];
        }

        if ($campos) {
            $this->forge->addColumn('companies', $campos);
        }

        // `fieldExists()` cachea los nombres de columna: sin resetear, la
        // pregunta de abajo recibiría la lista de ANTES del ALTER y el índice
        // no se crearía (así se perdió en silencio `companies_parent_id`).
        $this->db->resetDataCache();

        if ($this->db->fieldExists('sector_id', 'companies')) {
            // Sin FK a propósito: `sectors` usa soft-delete, así que un
            // `ON DELETE SET NULL` casi nunca dispararía. Desclasificar las
            // empresas de un sector borrado lo hace explícitamente
            // `SectorController::delete()`, que además avisa de cuántas son.
            $this->forge->addKey('sector_id');
            $this->db->query('CREATE INDEX companies_sector_id ON ' . $this->db->prefixTable('companies') . ' (sector_id)');
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('companies')) {
            return;
        }

        foreach (['sector_id', 'latitude', 'longitude'] as $c) {
            if ($this->db->fieldExists($c, 'companies')) {
                $this->forge->dropColumn('companies', $c);
            }
        }
    }
}
