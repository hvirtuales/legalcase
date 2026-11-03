<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Oportunidades abiertas (fase 3 de `proyectos/plan_directorio_leads_lawyers.md`).
 *
 * El directorio resuelve el caso en el que el visitante **elige** a un abogado: entra en
 * su perfil y le escribe. Pero mucha gente no sabe a quién acudir —es el primer problema
 * del §5 del insumo— y una solicitud así no puede ir a una firma concreta: nadie ha
 * elegido todavía.
 *
 * Por eso nace como **oportunidad**: un caso descrito por área, jurisdicción, idioma y
 * ciudad que las firmas suscritas a esa área ven en una bandeja y **reclaman**. Al
 * reclamar, y solo entonces, se crea el lead en esa firma con los datos de contacto.
 *
 * **La tabla es GLOBAL, sin `company_id`, y es la segunda excepción consciente del
 * componente** (la primera son las áreas): la oportunidad no pertenece a ninguna firma
 * hasta que alguien la reclama, y ponerle `company_id` obligaría a elegir dueño antes de
 * que exista. Lo que aísla aquí no es la columna: es `cmp_lfl_opportunity_claims`, con su
 * UNIQUE `(opportunity_id, company_id)`.
 *
 * **Los datos de contacto viven en esta tabla pero no se muestran** hasta el reclamo. Es
 * una decisión de producto y de datos personales a la vez: la bandeja enseña el caso, no
 * a la persona, y así una firma no puede recoger contactos sin comprometerse a atenderlos.
 *
 * **El tope de reclamos va en la fila** y no en una constante: lo que se promete al
 * prospecto —«te contactarán hasta tres despachos»— tiene que quedar escrito junto a su
 * solicitud, porque si mañana cambia el tope no puede cambiar retroactivamente lo que se
 * le dijo.
 */
class CreateLflOpportunityTables extends Migration
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

    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
    }

    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_opportunities')) {
            $this->forge->addField([
                'id'    => $this->id(),
                // Token aleatorio para que el prospecto pueda volver a ver el estado de
                // su solicitud sin cuenta. Nunca el id: un id se puede recorrer.
                'token' => ['type' => 'VARCHAR', 'constraint' => 64],

                'area_id'         => $this->fk(),
                'jurisdiction_id' => $this->fk(true),
                'language'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'city'            => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'summary'         => ['type' => 'TEXT', 'null' => true],

                // Contacto: se guarda y NO se muestra hasta el reclamo.
                'first_name' => ['type' => 'VARCHAR', 'constraint' => 80],
                'last_name'  => ['type' => 'VARCHAR', 'constraint' => 80],
                'email'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'email_norm' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'phone'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'phone_norm' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],

                'consent_at' => ['type' => 'DATETIME', 'null' => true],
                'consent_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],

                // open → claimed (alguien reclamó) → closed (tope alcanzado) | expired.
                'status'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'],
                'claims_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'max_claims'   => ['type' => 'INT', 'constraint' => 11, 'default' => 3],
                'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('token');
            $this->forge->addKey(['status', 'area_id']);
            $this->forge->addForeignKey('area_id', 'cmp_lfl_areas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('jurisdiction_id', 'cmp_lfl_jurisdictions', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_opportunities');
        }

        if (! $this->db->tableExists('cmp_lfl_opportunity_claims')) {
            $this->forge->addField([
                'id'             => $this->id(),
                'opportunity_id' => $this->fk(),
                'company_id'     => $this->fk(),
                'user_id'        => $this->fk(true),
                // El lead que se creó al reclamar: es la prueba de que el reclamo sirvió
                // de algo, y lo que permite medir qué firma convierte lo que reclama.
                'lead_id'        => $this->fk(true),
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            // **El freno de verdad contra el doble reclamo.** Dos peticiones a la vez
            // pasan las dos la comprobación previa; esto deja entrar solo a una.
            $this->forge->addUniqueKey(['opportunity_id', 'company_id']);
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('opportunity_id', 'cmp_lfl_opportunities', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_opportunity_claims');
        }
    }

    public function down()
    {
        foreach (['cmp_lfl_opportunity_claims', 'cmp_lfl_opportunities'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }
}
