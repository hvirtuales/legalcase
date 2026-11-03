<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Canal de denuncia sobre una firma o un abogado del directorio.
 *
 * Es la idea que la vitrina de referencia resuelve con una columna `kind` en la misma
 * tabla de reseñas. **Aquí va en tabla aparte, y es una divergencia deliberada**: allí
 * una reseña y una denuncia comparten casi todas las columnas, pero aquí una opinión
 * exige `lead_id` NOT NULL con UNIQUE y un `token` de invitación —es el final de una
 * relación verificada con un cliente—, mientras que una denuncia la escribe cualquiera
 * y no tiene ni cliente ni invitación. Meterlas juntas obligaría a hacer `lead_id`
 * nullable, y ese UNIQUE es justo la garantía de «una opinión por cliente» que
 * sostiene el módulo entero.
 *
 * ── Lo que sí se copia, que es lo importante ───────────────────────────────────
 *
 * **(a) Una denuncia NO se publica nunca.** No tiene listado público, no entra en
 * ninguna media y no baja la calificación de nadie. Si la bajara, denunciar sería el
 * botón más barato para hundir a un competidor, y encima sin escribir una estrella.
 * Se actúa sobre ella, no se enseña.
 *
 * **(b) El estado se conserva.** `atendida` y `descartada` no se borran: ante una
 * reclamación hay que poder decir qué se recibió, qué se decidió y cuándo.
 *
 * **(c) La IP va HASHEADA, nunca en claro.** Hace falta para el tope de «una por IP y
 * firma», y para eso basta compararla consigo misma. La sal sale de la clave de la
 * instalación, así que dos plataformas no producen el mismo valor para la misma IP.
 * Es distinto de `cmp_lfl_reviews.consent_ip`, que sí se guarda entera porque ahí es
 * la prueba de un consentimiento que alguien dio.
 *
 * **Sin FK a `companies`**: esa tabla usa soft-delete, así que un `ON DELETE CASCADE`
 * casi nunca dispararía y daría una falsa sensación de limpieza.
 */
class CreateLflComplaintsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_lfl_complaints')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // Si la denuncia salió de la ficha de un abogado, queda dicho de quién se
            // habla. Nullable: también se puede denunciar a la firma sin señalar a nadie.
            'profile_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // VARCHAR + validación en el modelo, no ENUM: el componente es portable a
            // MySQL, PostgreSQL y SQL Server.
            'category'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'other'],
            'reason'       => ['type' => 'TEXT'],
            // Opcional: sin él la plataforma no puede pedir detalles, pero obligarlo
            // dejaría fuera a quien teme represalias, que es quien más necesita el canal.
            'contact'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'ip_hash'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'handled_at'   => ['type' => 'DATETIME', 'null' => true],
            'handled_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'handled_note' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // El índice que usa la bandeja: siempre pregunta por estado y ordena por fecha.
        $this->forge->addKey(['status', 'created_at']);
        // Y el del tope por IP, que mira firma + huella.
        $this->forge->addKey(['company_id', 'ip_hash']);
        $this->forge->createTable('cmp_lfl_complaints', true);
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_complaints', true);
    }
}
