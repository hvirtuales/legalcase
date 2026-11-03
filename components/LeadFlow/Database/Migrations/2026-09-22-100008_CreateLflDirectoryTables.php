<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El directorio público (fase 1 de `proyectos/plan_directorio_leads_lawyers.md`):
 * perfiles publicables, y la taxonomía con la que se buscan.
 *
 * **Áreas y jurisdicciones son GLOBALES, sin `company_id`, y es a propósito.** Un
 * directorio en el que cada firma nombra sus propias áreas no se puede filtrar: el
 * visitante que busca «inmigración» tiene que encontrar a todos, no a los de la
 * firma que escribió esa palabra igual. Los servicios de cada firma
 * (`cmp_lfl_services`) siguen siendo suyos y ahora pueden apuntar a un área, de modo
 * que el lead que entra por el directorio ya trae el servicio correcto para la
 * conversión. Es la misma clase de excepción que la red global de Networking, y por
 * eso se escribe aquí: una excepción al aislamiento que no se explica es
 * indistinguible de un olvido.
 *
 * `cmp_lfl_profiles` sí lleva `company_id`: un perfil es de una firma, y el de un
 * abogado lleva además `user_id`. Con `user_id` nulo, el perfil es el de la firma.
 *
 * Cada tabla con su guarda `tableExists()`: la migración se puede reejecutar.
 */
class CreateLflDirectoryTables extends Migration
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
        // ── Áreas de práctica (global) ────────────────────────────────────────
        if (! $this->db->tableExists('cmp_lfl_areas')) {
            $this->forge->addField([
                'id'        => $this->id(),
                'code'      => ['type' => 'VARCHAR', 'constraint' => 40],
                'slug'      => ['type' => 'VARCHAR', 'constraint' => 60],
                'name'      => ['type' => 'VARCHAR', 'constraint' => 120],
                'sort'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->addUniqueKey('slug');
            $this->forge->createTable('cmp_lfl_areas');
        }

        // ── Jurisdicciones (global) ───────────────────────────────────────────
        if (! $this->db->tableExists('cmp_lfl_jurisdictions')) {
            $this->forge->addField([
                'id'        => $this->id(),
                'code'      => ['type' => 'VARCHAR', 'constraint' => 20],
                'name'      => ['type' => 'VARCHAR', 'constraint' => 120],
                'country'   => ['type' => 'VARCHAR', 'constraint' => 2, 'default' => 'US'],
                'sort'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts(false));
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('cmp_lfl_jurisdictions');
        }

        // ── Perfiles publicables ──────────────────────────────────────────────
        if (! $this->db->tableExists('cmp_lfl_profiles')) {
            $this->forge->addField([
                'id'          => $this->id(),
                'company_id'  => $this->fk(),
                // Nulo = el perfil de la firma; con valor = el de uno de sus abogados.
                'user_id'     => $this->fk(true),
                'kind'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'lawyer'],
                'slug'        => ['type' => 'VARCHAR', 'constraint' => 120],
                'display_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'headline'    => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'bio'         => ['type' => 'TEXT', 'null' => true],
                'photo'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'email'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'phone'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'whatsapp'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'website'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'city'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                // Listas cortas guardadas como texto separado por comas: son etiquetas
                // para pintar y filtrar, no entidades con vida propia (y §8 pide que el
                // esquema sea portable: nada de JSON nativo).
                'languages'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'consult_modes' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'experience_years' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                // Confianza (§8 del insumo de contenidos).
                'bar_number'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'bar_state'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'bar_url'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'verified_at' => ['type' => 'DATETIME', 'null' => true],
                'verified_by' => $this->fk(true),
                // Publicación. Nace en borrador: nadie sale al directorio sin decidirlo.
                'status'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'draft'],
                'published_at' => ['type' => 'DATETIME', 'null' => true],
                'seo_description' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'leads_count'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'views_count'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('slug');
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey('user_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_profiles');
        }

        // ── Pivotes perfil ↔ taxonomía ────────────────────────────────────────
        foreach ([
            ['cmp_lfl_profile_areas', 'area_id', 'cmp_lfl_areas'],
            ['cmp_lfl_profile_jurisdictions', 'jurisdiction_id', 'cmp_lfl_jurisdictions'],
        ] as [$table, $column, $parent]) {
            if ($this->db->tableExists($table)) {
                continue;
            }
            $this->forge->addField([
                'id'         => $this->id(),
                'profile_id' => $this->fk(),
                $column      => $this->fk(),
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['profile_id', $column]);
            $this->forge->addForeignKey('profile_id', 'cmp_lfl_profiles', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey($column, $parent, 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable($table);
        }

        // El servicio de la firma puede colgar de un área del catálogo común: así el
        // lead que entra por el directorio nace con el servicio bueno.
        if ($this->db->tableExists('cmp_lfl_services') && ! $this->db->fieldExists('area_id', 'cmp_lfl_services')) {
            $this->forge->addColumn('cmp_lfl_services', [
                'area_id' => $this->fk(true),
            ]);
            $this->db->resetDataCache();
        }

        // El lead sabe por qué perfil entró: es lo que permite decirle a cada abogado
        // cuántos prospectos le trajo el directorio.
        if ($this->db->tableExists('cmp_lfl_leads') && ! $this->db->fieldExists('profile_id', 'cmp_lfl_leads')) {
            $this->forge->addColumn('cmp_lfl_leads', [
                'profile_id' => $this->fk(true),
            ]);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        foreach (['profile_id' => 'cmp_lfl_leads', 'area_id' => 'cmp_lfl_services'] as $col => $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists($col, $table)) {
                $this->forge->dropColumn($table, $col);
                $this->db->resetDataCache();
            }
        }

        foreach (['cmp_lfl_profile_jurisdictions', 'cmp_lfl_profile_areas', 'cmp_lfl_profiles',
            'cmp_lfl_jurisdictions', 'cmp_lfl_areas'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }
}
