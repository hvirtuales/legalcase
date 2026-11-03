<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * De dónde salió cada contacto, y cuántas veces ha rebotado sin llegar a morir.
 *
 * **El consentimiento.** La política de uso aceptable de SES exige enviar solo a
 * quien pidió recibir, y **poder demostrarlo** si AWS revisa la cuenta. Hasta
 * ahora solo constaba `subscribed_at`: una fecha no dice de dónde vino la
 * dirección. Con una importación de CSV no quedaba ninguna prueba. Mismo
 * criterio que usa PQRSF con `consent_at`/`consent_ip`/`policy_version`.
 *
 * **Los rebotes temporales.** Un rebote blando —buzón lleno, servidor caído un
 * martes— no debe suprimir al contacto, y por eso no lo hacía. Pero sin contador
 * una dirección que rebota SIEMPRE se reintentaba en cada campaña, para siempre,
 * sumando rebotes a la reputación de la cuenta. Esta columna es lo que permite
 * cortar al cabo de unos cuantos.
 *
 * Portabilidad (contrato 8): VARCHAR/DATETIME/INT por Forge, nada específico de
 * un motor. Idempotente, columna por columna: si una migración anterior falló a
 * medias, esta la completa sin tropezar.
 */
class AddConsentToMailerContacts extends Migration
{
    private const COLUMNS = [
        // Cómo llegó: form | import | manual | api. VARCHAR y no ENUM (contrato 8).
        'consent_source' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'entity'],
        'consent_at'     => ['type' => 'DATETIME', 'null' => true, 'after' => 'consent_source'],
        'consent_ip'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true, 'after' => 'consent_at'],
        // La prueba en texto: «formulario de la web», «lista del evento de mayo».
        'consent_note'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'consent_ip'],
        'soft_bounces'   => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0, 'after' => 'status'],
    ];

    public function up()
    {
        if (!$this->db->tableExists('cmp_mailer_contacts')) {
            return;
        }

        foreach (self::COLUMNS as $name => $def) {
            if ($this->db->fieldExists($name, 'cmp_mailer_contacts')) {
                continue;
            }
            $this->forge->addColumn('cmp_mailer_contacts', [$name => $def]);
            // `fieldExists()` cachea los nombres de columna: sin esto, la
            // siguiente vuelta del bucle pregunta por la lista de ANTES.
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('cmp_mailer_contacts')) {
            return;
        }
        foreach (array_keys(self::COLUMNS) as $name) {
            if ($this->db->fieldExists($name, 'cmp_mailer_contacts')) {
                $this->forge->dropColumn('cmp_mailer_contacts', $name);
                $this->db->resetDataCache();
            }
        }
    }
}
