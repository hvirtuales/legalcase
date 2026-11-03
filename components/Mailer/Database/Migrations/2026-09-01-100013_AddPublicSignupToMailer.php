<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El alta pública con confirmación por correo (doble opt-in).
 *
 * **Por qué con confirmación.** Un formulario abierto que da de alta sin
 * confirmar deja que cualquiera suscriba la dirección de otro. El registro de
 * consentimiento diría entonces «formulario, esta IP, esta fecha» para alguien
 * que nunca lo pidió: una prueba falsa, que ante una revisión de AWS es peor
 * que no tener ninguna. La confirmación es lo que hace que ese registro
 * signifique algo.
 *
 * En la lista:
 *   - `public_token`   la URL del formulario. Aleatorio y no el id, para que no
 *                      se puedan recorrer las listas de todas las empresas.
 *   - `public_enabled` el alta pública se apaga sin perder el token ni el
 *                      histórico, igual que se desactiva un enlace de venta.
 *   - `consent_text`   lo que la persona acepta, con sus palabras. Es lo que se
 *                      guarda como prueba, así que lo escribe la empresa.
 *
 * En el contacto:
 *   - `confirm_token`  el enlace del correo de confirmación.
 *   - `confirmed_at`   cuándo pulsó. Distinto de `consent_at`, que es cuándo
 *                      envió el formulario: la prueba fuerte es esta.
 *
 * Un contacto sin confirmar se queda en `pending`, y como `SENDABLE` es
 * `subscribed`, **no entra en ninguna cola**: no hace falta tocar el encolado.
 *
 * Portabilidad (contrato 8): VARCHAR/TINYINT/DATETIME por Forge. Idempotente
 * columna por columna.
 */
class AddPublicSignupToMailer extends Migration
{
    private const LISTS = [
        'public_token'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        'public_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        'consent_text'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
    ];

    private const CONTACTS = [
        'confirm_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        'confirmed_at'  => ['type' => 'DATETIME', 'null' => true],
    ];

    public function up()
    {
        $this->add('cmp_mailer_lists', self::LISTS);
        $this->add('cmp_mailer_contacts', self::CONTACTS);
    }

    public function down()
    {
        $this->drop('cmp_mailer_lists', array_keys(self::LISTS));
        $this->drop('cmp_mailer_contacts', array_keys(self::CONTACTS));
    }

    private function add(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        foreach ($columns as $name => $def) {
            if ($this->db->fieldExists($name, $table)) {
                continue;
            }
            $this->forge->addColumn($table, [$name => $def]);
            // `fieldExists()` cachea los nombres: sin esto la siguiente vuelta
            // del bucle pregunta por la lista de ANTES del ALTER.
            $this->db->resetDataCache();
        }
    }

    private function drop(string $table, array $names): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        foreach ($names as $name) {
            if ($this->db->fieldExists($name, $table)) {
                $this->forge->dropColumn($table, $name);
                $this->db->resetDataCache();
            }
        }
    }
}
