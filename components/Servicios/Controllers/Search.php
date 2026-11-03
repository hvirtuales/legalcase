<?php

namespace Components\Servicios\Controllers;

use App\Controllers\BaseController;
use Components\Servicios\Models\ClientModel;
use Components\Servicios\Models\ProductModel;
use Components\Servicios\Models\ProductPriceModel;

/**
 * Autocompletado del cotizador (JSON). Siempre acotado a la empresa activa: nunca
 * puede devolver clientes ni productos de otra empresa.
 */
class Search extends BaseController
{
    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    public function clients()
    {
        $companyId = $this->companyId();
        $q         = trim((string) $this->request->getGet('q'));
        if (!$companyId || mb_strlen($q) < 2) {
            return $this->response->setJSON([]);
        }

        $rows = (new ClientModel())
            ->where('company_id', $companyId)
            ->groupStart()->like('name', $q)->orLike('nit', $q)->orLike('contact_name', $q)->groupEnd()
            ->orderBy('name', 'asc')
            ->findAll(10);

        return $this->response->setJSON(array_map(static fn ($c) => [
            'id'            => (int) $c['id'],
            'name'          => $c['name'],
            'nit'           => $c['nit'],
            'city'          => $c['city'],
            'price_tier'    => (int) ($c['price_tier'] ?? 0),
        ], $rows));
    }

    public function products()
    {
        $companyId = $this->companyId();
        $q         = trim((string) $this->request->getGet('q'));
        if (!$companyId || mb_strlen($q) < 2) {
            return $this->response->setJSON([]);
        }

        $rows = (new ProductModel())
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->groupStart()->like('code', $q)->orLike('name', $q)->groupEnd()
            ->orderBy('name', 'asc')
            ->findAll(10);

        // Los 3 valores de cada producto, para que el cotizador aplique el del cliente.
        $tiers = (new ProductPriceModel())->forProducts(array_column($rows, 'id'));

        return $this->response->setJSON(array_map(static fn ($p) => [
            'id'             => (int) $p['id'],
            'code'           => $p['code'],
            'name'           => $p['name'],
            'description'    => (string) $p['description'],
            'details_url'    => $p['details_url'],
            'unit_price'     => (float) $p['unit_price'],
            'tiers'          => (object) ($tiers[(int) $p['id']] ?? []),
            'quantity'       => (float) $p['default_quantity'],
            'discount_type'  => $p['discount_type'],
            'discount_value' => (float) $p['discount_value'],
            'tax_percent'    => (float) $p['tax_percent'],
        ], $rows));
    }
}
