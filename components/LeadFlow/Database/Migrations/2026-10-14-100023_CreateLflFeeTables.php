<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honorarios y pagos del expediente.
 *
 * ── La frontera con Contabilidad, por escrito ────────────────────────────────
 *
 * Esto registra **lo pactado y lo recibido de un caso**, que es lo que el abogado
 * necesita mirar en la ficha. NO emite factura ni comprobante fiscal, no genera asiento
 * de partida doble, no toca el PUC, no calcula impuestos ni retenciones, no numera
 * consecutivos fiscales y no concilia tesorería: eso es el componente Contabilidad.
 *
 * Mecánicamente, el aislamiento es literal: **ni una FK ni una lectura de `cmp_cont_*`**
 * (contrato §0.4), y tampoco un `voucher_id` reservado «por si acaso» — una FK a otro
 * componente rompe la instalación donde ese componente no está instalado. El puente, si
 * alguna vez se pide, será una exportación, no un acoplamiento.
 *
 * ── Por qué `kind` separa honorario de tasa oficial ──────────────────────────
 *
 * Las tasas de USCIS no son ingreso de la firma: son dinero que el cliente paga al
 * gobierno y que pasa por la firma. Sin esa distinción, un expediente de 3.500 USD de
 * los que 1.200 son tasas se leería como 3.500 de honorarios, y el estado de cuenta que
 * se le enseña al cliente estaría mintiendo sobre quién cobra qué.
 *
 * ── Por qué los pagos son tabla y no una columna ─────────────────────────────
 *
 * Un caso se paga a plazos. Guardar solo «cobrado: 1.200» pierde cuándo, cómo y con qué
 * referencia, que es exactamente lo que hay que poder enseñar cuando un cliente dice que
 * ya pagó. `fee_id` es opcional: hay abonos a cuenta que no corresponden a una línea.
 */
class CreateLflFeeTables extends Migration
{
    private function ts(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];
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
        if (! $this->db->tableExists('cmp_lfl_matter_fees')) {
            $this->forge->addField([
                'id'         => $this->id(),
                'company_id' => $this->fk(),
                'matter_id'  => $this->fk(),
                // fee|filing|third_party|expense — solo `fee` es ingreso de la firma.
                'kind'       => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'fee'],
                'concept'    => ['type' => 'VARCHAR', 'constraint' => 160],
                'amount'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                // Se copia del expediente al crear la línea: un cambio de moneda de la
                // firma no puede reinterpretar importes ya pactados.
                'currency'   => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],
                'due_at'     => ['type' => 'DATETIME', 'null' => true],
                'sort'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'note'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['matter_id', 'kind']);
            $this->forge->addKey(['company_id', 'due_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('matter_id', 'cmp_lfl_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_lfl_matter_fees');
        }

        if (! $this->db->tableExists('cmp_lfl_matter_payments')) {
            $this->forge->addField([
                'id'          => $this->id(),
                'company_id'  => $this->fk(),
                'matter_id'   => $this->fk(),
                // Opcional: un abono a cuenta no corresponde a ninguna línea concreta.
                'fee_id'      => $this->fk(true),
                'amount'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency'    => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],
                'paid_at'     => ['type' => 'DATETIME', 'null' => true],
                'method'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'transfer'],
                'reference'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'note'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'recorded_by' => $this->fk(true),
            ] + $this->ts());
            $this->forge->addKey('id', true);
            $this->forge->addKey('matter_id');
            $this->forge->addKey(['company_id', 'paid_at']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('matter_id', 'cmp_lfl_matters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('fee_id', 'cmp_lfl_matter_fees', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('recorded_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cmp_lfl_matter_payments');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_matter_payments', true);
        $this->forge->dropTable('cmp_lfl_matter_fees', true);
    }
}
