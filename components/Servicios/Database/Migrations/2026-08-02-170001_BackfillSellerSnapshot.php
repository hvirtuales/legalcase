<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rellena el snapshot del vendedor en las cotizaciones que se emitieron ANTES de
 * que existieran cargo, foto y firma (`2026-08-02-160008`): quedaron con el
 * `seller_position` vacío y por eso el cargo no salía bajo el nombre.
 *
 * Solo toca los campos **vacíos** y solo cuando la cotización sigue apuntando a
 * un vendedor existente: un snapshot ya escrito no se pisa nunca (esa es la
 * promesa del documento emitido). Es idempotente — volver a correrla no cambia
 * nada.
 *
 * Recorre vendedor por vendedor con el Query Builder (nada de UPDATE ... JOIN,
 * que no es portable entre motores).
 */
class BackfillSellerSnapshot extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_quo_quotes') || !$this->db->tableExists('cmp_quo_sellers')) {
            return;
        }
        if (!$this->db->fieldExists('seller_position', 'cmp_quo_quotes')) {
            return;
        }

        $sellers = $this->db->table('cmp_quo_sellers')
            ->select('id, position, photo, signature, card_url')
            ->get()->getResultArray();

        foreach ($sellers as $seller) {
            $map = [
                'seller_position'  => (string) ($seller['position'] ?? ''),
                'seller_photo'     => $seller['photo'] ?? null,
                'seller_signature' => $seller['signature'] ?? null,
                'seller_card_url'  => (string) ($seller['card_url'] ?? ''),
            ];

            foreach ($map as $column => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }

                $this->db->table('cmp_quo_quotes')
                    ->where('seller_id', $seller['id'])
                    ->groupStart()
                        ->where($column, '')
                        ->orWhere($column, null)
                    ->groupEnd()
                    ->update([$column => $value]);
            }
        }
    }

    public function down()
    {
        // Nada que revertir: solo completó datos que faltaban.
    }
}
