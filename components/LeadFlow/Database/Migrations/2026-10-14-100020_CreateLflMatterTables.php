<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El expediente: la gestión del caso DENTRO de la plataforma.
 *
 * ── Por qué existe ───────────────────────────────────────────────────────────
 *
 * Hasta aquí el embudo terminaba en «convertido a Docketwise», y esa salida exigía
 * tener Docketwise: `ConversionService::problems()` pide `isConnected()`, y la etapa
 * `kind='converted'` solo la puede escribir la conversión. Una firma SIN Docketwise
 * dejaba sus leads en «Aprobado» para siempre, sin ninguna otra salida.
 *
 * El expediente es esa salida. Docketwise pasa a ser un destino opcional que se
 * sincroniza si la firma lo tiene, no el único final posible.
 *
 * ── Un expediente por lead ───────────────────────────────────────────────────
 *
 * `UNIQUE(lead_id)`, el mismo criterio que ya usan `cmp_lfl_conversions.lead_id` y
 * `cmp_lfl_reviews.lead_id`. Un segundo trámite del mismo cliente entra como un
 * segundo lead, y eso no es una limitación: así la atribución (fuente, campaña, UTM)
 * y el scoring siguen contando una venta por intención.
 *
 * La consecuencia buscada es que los hijos del caso —actividades, documentos, notas,
 * consultas, línea de tiempo— NO necesitan `matter_id`: ya cuelgan de `lead_id` y la
 * relación es 1:1. Una columna `matter_id` sería funcionalmente dependiente
 * (se deduce del lead), podría divergir, y obligaría a cinco escritores distintos a
 * acordarse de rellenarla. `Access::scope()` está escrito contra `cmp_lfl_leads` de
 * todos modos, así que derivar del lead no cuesta un salto extra.
 *
 * Sí llevan `matter_id` las filas que solo existen por el expediente: requisitos,
 * honorarios y pagos (migraciones siguientes).
 *
 * ── El snapshot ──────────────────────────────────────────────────────────────
 *
 * Se congela lo que se IMPRIME y vive en tablas que la firma edita a diario: el
 * nombre del servicio se renombra, un abogado se va de la firma, la moneda cambia.
 * Es el criterio de `QuoteService::clientSnapshot()`: se guardan la FK y la copia
 * — la FK para navegar, la copia para que un documento emitido no cambie solo.
 *
 * NO se congelan: la etapa (se mueve, es el punto del expediente), el dinero (tiene
 * sus propias tablas), los documentos (son del lead y deben seguir vivos) ni el
 * recibo de USCIS (vive en el lead; duplicarlo daría dos verdades a `leadflow:tick`).
 */
class CreateLflMatterTables extends Migration
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
        /*
         * Etapas procesales, configurables por firma.
         *
         * Son otra cosa que las del pipeline (`cmp_lfl_stages`): esas miden una venta y
         * solo avanzan; estas describen un trámite y RETROCEDEN — una solicitud de
         * evidencia adicional te devuelve a recolección documental. Por eso tabla
         * propia y `kind` propio en vez de reutilizar las comerciales.
         *
         * Va primero porque `cmp_lfl_matters.stage_id` la referencia.
         */
        if (! $this->db->tableExists('cmp_lfl_matter_stages')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'code'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                // open|filed|waiting|closed|cancelled — validado en el modelo, no ENUM
                // (portabilidad a PostgreSQL y SQL Server).
                'kind'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'open'],
                'sort'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'color'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'primary'],
                // Días esperados en la etapa. Alimenta la vista de estancados; null =
                // sin plazo esperado, que es lo normal en las etapas que dependen de
                // una autoridad (biométricos, entrevista).
                'sla_days'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'is_active', 'sort']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_matter_stages');
        }

        if (! $this->db->tableExists('cmp_lfl_matters')) {
            $this->forge->addField([
                'id'                => $this->id(),
                'company_id'        => $this->fk(),
                'lead_id'           => $this->fk(),
                // EXP-000001. Consecutivo por firma, transaccional.
                'number'            => ['type' => 'VARCHAR', 'constraint' => 30],
                'title'             => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'stage_id'          => $this->fk(true),
                'stage_entered_at'  => ['type' => 'DATETIME', 'null' => true],
                // active|on_hold|closed|cancelled — se DERIVA del kind de la etapa,
                // igual que `leads.status` se deriva del kind de la etapa comercial.
                'status'            => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'active'],
                'opened_at'         => ['type' => 'DATETIME', 'null' => true],
                'opened_by'         => $this->fk(true),
                'closed_at'         => ['type' => 'DATETIME', 'null' => true],
                // won|denied|withdrawn|transferred|other. Obligatoria al cerrar, como
                // `leads.lost_reason` al perder: un cierre sin motivo no se puede leer
                // después, y el cierre de un caso sí hay que poder explicarlo.
                'close_reason'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'close_note'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'service_id'        => $this->fk(true),
                // El responsable del caso. Separado del asistente a propósito: quien
                // responde ante el cliente no es siempre quien hace el trámite.
                'lawyer_user_id'    => $this->fk(true),
                'assigned_user_id'  => $this->fk(true),
                'summary'           => ['type' => 'TEXT', 'null' => true],

                // ── Snapshot: lo que se imprime ──────────────────────────────
                'client_first_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'client_last_name'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'client_email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'client_phone'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'client_whatsapp'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'client_country'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'client_city'       => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'service_name'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'matter_type_name'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'lawyer_name'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'currency'          => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],

                // ── Rollups: los recalcula el servicio, no el llamador ───────
                'fee_total'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'paid_total'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'docs_required'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'docs_received'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'next_deadline_at'  => ['type' => 'DATETIME', 'null' => true],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            // La llave de «un expediente por lead». Es además la que hace idempotente
            // la apertura: un segundo intento choca aquí en vez de duplicar.
            $this->forge->addUniqueKey('lead_id');
            // Segunda red del consecutivo: si dos peticiones pasaran el contador, el
            // insert falla en vez de dejar dos expedientes con el mismo número.
            $this->forge->addUniqueKey(['company_id', 'number']);
            $this->forge->addKey(['company_id', 'status', 'stage_id']);
            $this->forge->addKey(['company_id', 'next_deadline_at']);
            $this->forge->addKey(['company_id', 'lawyer_user_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('lead_id', 'cmp_lfl_leads', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('stage_id', 'cmp_lfl_matter_stages', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('service_id', 'cmp_lfl_services', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('lawyer_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('assigned_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('opened_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_matters');
        }

        /*
         * El contador del consecutivo y el prefijo, en los ajustes de la firma.
         *
         * Va en `cmp_lfl_settings` —que tiene UNIQUE(company_id)— porque hace de tabla
         * maestra igual que `cmp_cont_voucher_types` en Contabilidad: el incremento es
         * un `UPDATE ... SET col = col + 1` evaluado en el servidor, así que el motor
         * bloquea LA FILA de esa firma y dos aperturas simultáneas se serializan ahí.
         * Un `MAX(number)+1` sí daría duplicados.
         */
        if ($this->db->tableExists('cmp_lfl_settings')) {
            $nuevas = [];
            if (! $this->db->fieldExists('matter_prefix', 'cmp_lfl_settings')) {
                $nuevas['matter_prefix'] = ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'EXP'];
            }
            if (! $this->db->fieldExists('matter_sequence', 'cmp_lfl_settings')) {
                $nuevas['matter_sequence'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0];
            }
            if (! $this->db->fieldExists('fee_terms', 'cmp_lfl_settings')) {
                // Texto por defecto del acuerdo de honorarios, para no reescribirlo
                // en cada expediente.
                $nuevas['fee_terms'] = ['type' => 'TEXT', 'null' => true];
            }
            if ($nuevas !== []) {
                $this->forge->addColumn('cmp_lfl_settings', $nuevas);
                $this->db->resetDataCache();
            }
        }
    }

    public function down()
    {
        // La hija primero: `matters` referencia a `matter_stages`.
        $this->forge->dropTable('cmp_lfl_matters', true);
        $this->forge->dropTable('cmp_lfl_matter_stages', true);

        if ($this->db->tableExists('cmp_lfl_settings')) {
            foreach (['matter_prefix', 'matter_sequence', 'fee_terms'] as $col) {
                if ($this->db->fieldExists($col, 'cmp_lfl_settings')) {
                    $this->forge->dropColumn('cmp_lfl_settings', $col);
                }
            }
            $this->db->resetDataCache();
        }
    }
}
