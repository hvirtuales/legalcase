<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Opiniones verificadas (fase 4 de `proyectos/plan_directorio_leads_lawyers.md`).
 *
 * **La reputación es la parte regulada del producto.** En publicidad legal los
 * testimonios están sujetos a las reglas 7.1–7.3 del modelo de la ABA y a las de cada
 * estado: no pueden ser engañosos, no pueden crear expectativas injustificadas sobre
 * resultados y en varios estados exigen descargos. El esquema está hecho para que esas
 * reglas se puedan cumplir, no para dejarlas a la buena voluntad de quien escribe:
 *
 *  · **`lead_id` con UNIQUE y obligatorio.** No hay opiniones anónimas ni abiertas: solo
 *    opina quien llegó a ser cliente de esa firma, una vez por caso. Es el equivalente a
 *    la opinión anclada a una cita completada del componente Salud.
 *  · **`token` de 32 bytes** para que el cliente entre sin cuenta. Nunca el id del lead.
 *  · **Tres notas sobre LA ATENCIÓN** —claridad, respuesta y trato— y ninguna sobre el
 *    resultado del caso. Preguntar «¿ganó?» convertiría la media en una promesa de
 *    resultado, que es justo lo que las reglas prohíben insinuar.
 *  · **`status` nace en `pending`.** Se publica cuando la plataforma la aprueba; así hay
 *    un sitio donde frenar un texto que afirme resultados o identifique a un tercero.
 *  · **La firma no puede borrarla ni editarla, solo responder** (`reply`). Si pudiera
 *    quitar las malas, la media no significaría nada — y eso sí sería engañoso.
 *
 * `company_id` y `profile_id` van los dos: la opinión es de la firma que atendió, pero
 * se muestra en el perfil por el que entró el cliente, y un lead puede no tener perfil
 * (alta manual) mientras la opinión sigue siendo de la firma.
 */
class CreateLflReviewTables extends Migration
{
    private function ts(bool $soft = true): array
    {
        $f = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        if ($soft) {
            $f['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        return $f;
    }

    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_reviews')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'profile_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'lead_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'token'      => ['type' => 'VARCHAR', 'constraint' => 64],

                // Cómo quiere aparecer firmada: su nombre completo, solo el nombre o
                // «Cliente verificado». Lo elige quien opina, no la firma.
                'author_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],

                // Las tres notas de ATENCIÓN (1 a 5) y la media que se publica.
                'score_clarity'  => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
                'score_response' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
                'score_care'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
                'score'          => ['type' => 'DECIMAL', 'constraint' => '3,2', 'null' => true],

                'comment' => ['type' => 'TEXT', 'null' => true],

                // pending → published | rejected. Nace pendiente, siempre.
                'status'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'],
                'moderated_at' => ['type' => 'DATETIME', 'null' => true],
                'moderated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'reject_note'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

                // La respuesta de la firma: pública, una sola, y no borra la opinión.
                'reply'      => ['type' => 'TEXT', 'null' => true],
                'replied_at' => ['type' => 'DATETIME', 'null' => true],

                'consent_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('token');
            // **Una opinión por caso.** Sin esto, un enlace reenviado deja varias.
            $this->forge->addUniqueKey('lead_id');
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey(['profile_id', 'status']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('profile_id', 'cmp_lfl_profiles', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('lead_id', 'cmp_lfl_leads', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('moderated_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_reviews');
        }

        /*
         * El interruptor de las opiniones, en `plans`.
         *
         * **Por defecto 0, al revés que las demás banderas del componente.** La regla
         * general es «una columna ausente significa permitido», pero aquí el valor por
         * defecto es NO: hay jurisdicciones donde los testimonios están restringidos, y
         * encender eso solo porque nadie lo configuró sería tomar una decisión legal en
         * nombre de la firma.
         */
        if ($this->db->tableExists('plans') && ! $this->db->fieldExists('lfl_reviews_enabled', 'plans')) {
            $this->forge->addColumn('plans', [
                'lfl_reviews_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if ($this->db->tableExists('plans') && $this->db->fieldExists('lfl_reviews_enabled', 'plans')) {
            $this->forge->dropColumn('plans', 'lfl_reviews_enabled');
            $this->db->resetDataCache();
        }
        if ($this->db->tableExists('cmp_lfl_reviews')) {
            $this->forge->dropTable('cmp_lfl_reviews', true);
        }
    }
}
