<?php

namespace Components\Servicios\Libraries;

use Components\Servicios\Models\ClientModel;
use Components\Servicios\Models\QuoteItemModel;
use Components\Servicios\Models\QuoteModel;
use Components\Servicios\Models\SellerModel;
use Components\Servicios\Models\SettingsModel;

/**
 * Reglas de la cotización: numeración, guardado (con recálculo y snapshot),
 * estados y métricas del panel. Los controladores quedan delgados.
 */
class QuoteService
{
    private QuoteModel $quotes;
    private QuoteItemModel $items;
    private SettingsModel $settings;
    private QuoteCalculator $calc;
    private HtmlSanitizer $clean;

    public function __construct()
    {
        $this->quotes   = new QuoteModel();
        $this->items    = new QuoteItemModel();
        $this->settings = new SettingsModel();
        $this->calc     = new QuoteCalculator();
        $this->clean    = new HtmlSanitizer();
    }

    // ------------------------------------------------------------------ numeración

    /**
     * Reserva el siguiente número de la serie de la empresa: `PREFIJO-001`.
     *
     * A diferencia del original (que leía el último número sin reservarlo y podía
     * repetirlo con dos usuarios a la vez), el incremento va dentro de una
     * transacción. Si cambió el prefijo, la serie reinicia en `initial_number`.
     */
    public function nextNumber(int $companyId): string
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $cfg    = $this->settings->forCompany($companyId);
        $prefix = trim((string) $cfg['prefix']) ?: 'COT';
        $initial = max(1, (int) $cfg['initial_number']);

        $next = ((string) $cfg['last_prefix'] === $prefix && (int) $cfg['last_number'] > 0)
            ? max((int) $cfg['last_number'] + 1, $initial)
            : $initial;

        $db->table('cmp_quo_settings')
            ->where('company_id', $companyId)
            ->update(['last_number' => $next, 'last_prefix' => $prefix]);

        $db->transComplete();

        return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ guardado

    /**
     * Crea o actualiza una cotización con sus líneas.
     *
     * Los totales SIEMPRE se recalculan aquí: lo que manda el navegador solo sirve
     * para pintar el resumen en vivo. Los datos de cliente y vendedor se congelan
     * en la cotización (snapshot) para que editarlos después no altere documentos
     * ya emitidos.
     *
     * @return array{ok:bool,message:string,id:int|null}
     */
    public function save(int $companyId, array $data, array $rawItems, ?array $existing = null): array
    {
        $cfg   = $this->settings->forCompany($companyId);
        $items = $this->normalizeItems($rawItems);

        if (!$items) {
            return ['ok' => false, 'message' => lang('Quo.errNoItems'), 'id' => null];
        }

        $retention = (float) ($data['retention_percent'] ?? $cfg['default_retention'] ?? 0);
        $computed  = $this->calc->document($items, $retention);

        $row = [
            'company_id'     => $companyId,
            'doc_type'       => (string) ($data['doc_type'] ?? $cfg['doc_type']),
            'issue_date'     => $data['issue_date'] ?: date('Y-m-d'),
            'validity_days'  => (int) ($data['validity_days'] ?? $cfg['default_validity_days']),
            'payment_form'   => (string) ($data['payment_form'] ?? 'Contado'),
            'payment_method' => (string) ($data['payment_method'] ?? 'Transferencia bancaria'),
            'currency'       => in_array($data['currency'] ?? '', QuoteModel::CURRENCIES, true) ? $data['currency'] : $cfg['default_currency'],
            'intro_html'     => $this->clean->clean($data['intro_html'] ?? ''),
            'terms_html'     => $this->clean->clean($data['terms_html'] ?? ''),
            'show_detail'    => !empty($data['show_detail']) ? 1 : 0,
            'status'         => in_array($data['status'] ?? '', QuoteModel::STATUSES, true) ? $data['status'] : 'pendiente',
        ] + $computed['totals'];

        $row += $this->clientSnapshot($companyId, (int) ($data['client_id'] ?? 0));
        $row += $this->sellerSnapshot($companyId, (int) ($data['seller_id'] ?? 0));

        $db = \Config\Database::connect();
        $db->transStart();

        if ($existing) {
            $id = (int) $existing['id'];
            $this->quotes->update($id, $row);
            $this->items->where('quote_id', $id)->delete();
        } else {
            $row['number']     = $this->nextNumber($companyId);
            $row['created_by'] = (int) session()->get('user_id') ?: null;
            $this->quotes->insert($row);
            $id = (int) $this->quotes->getInsertID();
        }

        foreach ($computed['items'] as $item) {
            $this->items->insert([
                'quote_id'       => $id,
                'product_id'     => $item['product_id'] ?: null,
                'code'           => $item['code'],
                'name'           => $item['name'],
                'description'    => $item['description'],
                'details_url'    => $item['details_url'],
                'unit_price'     => $item['unit_price'],
                'quantity'       => $item['quantity'],
                'discount_type'  => $item['discount_type'],
                'discount_value' => $item['discount_value'],
                'tax_percent'    => $item['tax_percent'],
                'line_subtotal'  => $item['line_subtotal'],
                'line_discount'  => $item['line_discount'],
                'line_base'      => $item['line_base'],
                'line_tax'       => $item['line_tax'],
                'line_total'     => $item['line_total'],
                'sort_order'     => $item['sort_order'],
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'message' => lang('Quo.errSaveFailed'), 'id' => null];
        }

        return [
            'ok'      => true,
            'message' => $existing ? lang('Quo.okUpdated') : lang('Quo.okCreated'),
            'id'      => $id,
        ];
    }

    /** Deja las líneas del formulario en la forma que espera el calculador. */
    private function normalizeItems(array $raw): array
    {
        $out = [];

        foreach ($raw as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            $qty  = (float) ($r['quantity'] ?? 0);
            if ($name === '' || $qty <= 0) {
                continue; // filas vacías del formulario
            }
            $out[] = [
                'product_id'     => (int) ($r['product_id'] ?? 0) ?: null,
                'code'           => mb_substr(trim((string) ($r['code'] ?? '')), 0, 60),
                'name'           => mb_substr($name, 0, 200),
                'description'    => trim((string) ($r['description'] ?? '')),
                'details_url'    => mb_substr(trim((string) ($r['details_url'] ?? '')), 0, 255),
                'unit_price'     => (float) ($r['unit_price'] ?? 0),
                'quantity'       => $qty,
                'discount_type'  => ($r['discount_type'] ?? '%') === '$' ? '$' : '%',
                'discount_value' => (float) ($r['discount_value'] ?? 0),
                'tax_percent'    => (float) ($r['tax_percent'] ?? 0),
            ];
        }

        return $out;
    }

    /** Congela los datos del cliente en la cotización. */
    private function clientSnapshot(int $companyId, int $clientId): array
    {
        $client = $clientId
            ? (new ClientModel())->where('company_id', $companyId)->find($clientId)
            : null;

        return [
            'client_id'      => $client['id'] ?? null,
            'client_name'    => $client['name'] ?? '',
            'client_nit'     => $client['nit'] ?? '',
            'client_city'    => $client['city'] ?? '',
            'client_address' => $client['address'] ?? '',
            'client_contact' => $client['contact_name'] ?? '',
            'client_phone'   => $client['phone'] ?? '',
            'client_email'   => $client['email'] ?? '',
        ];
    }

    /** Congela los datos del vendedor (incluida su tarjeta digital). */
    private function sellerSnapshot(int $companyId, int $sellerId): array
    {
        $seller = $sellerId
            ? (new SellerModel())->where('company_id', $companyId)->find($sellerId)
            : null;

        // Si el vendedor no tiene una tarjeta externa, se congela la suya de la
        // plataforma: así el botón «Tarjeta digital» del documento y el QR del
        // PDF siempre llevan a algún sitio.
        $cardUrl = trim((string) ($seller['card_url'] ?? ''));
        if ($cardUrl === '' && $seller && ($seller['status'] ?? '') === 'activo') {
            $cardUrl = (new SellerCardService())->publicUrl($seller);
        }

        return [
            'seller_id'        => $seller['id'] ?? null,
            'seller_name'      => $seller['name'] ?? '',
            'seller_position'  => $seller['position'] ?? '',
            'seller_email'     => $seller['email'] ?? '',
            'seller_phone'     => $seller['phone'] ?? '',
            'seller_card_url'  => $cardUrl,
            'seller_photo'     => $seller['photo'] ?? null,
            'seller_signature' => $seller['signature'] ?? null,
        ];
    }

    // ------------------------------------------------------------------ consulta

    /** Cotización de la empresa (o null): el permiso no basta, hay que ser dueño. */
    public function find(int $companyId, int $id): ?array
    {
        $row = $this->quotes->where('company_id', $companyId)->find($id);
        if (!$row) {
            return null;
        }
        $row['items'] = $this->items->forQuote($id);

        return $row;
    }

    public function changeStatus(int $companyId, int $id, string $status): array
    {
        if (!in_array($status, QuoteModel::STATUSES, true)) {
            return ['ok' => false, 'message' => lang('Quo.errBadStatus')];
        }
        if (!$this->quotes->where('company_id', $companyId)->find($id)) {
            return ['ok' => false, 'message' => lang('Quo.errNotFound')];
        }
        $this->quotes->update($id, ['status' => $status]);

        return ['ok' => true, 'message' => lang('Quo.okStatusChanged')];
    }

    // ------------------------------------------------------------------ métricas

    /**
     * Rango de fechas de los filtros de período del original
     * (`script.js:1887`): mes actual, mes anterior, últimos 3/6, este año.
     *
     * @return array{0:?string,1:?string} [desde, hasta] en Y-m-d
     */
    public function periodRange(string $period): array
    {
        $today = date('Y-m-d');

        switch ($period) {
            case 'mes-actual':
                return [date('Y-m-01'), $today];
            case 'mes-anterior':
                return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))];
            case 'ultimos-3':
                return [date('Y-m-d', strtotime('-3 months')), $today];
            case 'ultimos-6':
                return [date('Y-m-d', strtotime('-6 months')), $today];
            case 'este-anio':
                return [date('Y-01-01'), $today];
            default:
                return [null, null];
        }
    }

    /** Aplica los filtros del panel sobre el modelo (lo usa DataTable). */
    public function applyFilters($model, int $companyId, array $f): void
    {
        $model->where('company_id', $companyId);

        [$from, $to] = $this->periodRange((string) ($f['period'] ?? ''));
        if ($from) {
            $model->where('issue_date >=', $from)->where('issue_date <=', $to);
        }
        if (!empty($f['seller']) && (int) $f['seller'] > 0) {
            $model->where('seller_id', (int) $f['seller']);
        }
        if (!empty($f['status']) && in_array($f['status'], QuoteModel::STATUSES, true)) {
            $model->where('status', $f['status']);
        }
    }

    /**
     * Totalizadores del panel: documentos, aprobadas, total cotizado y tasa de
     * conversión (aprobadas + facturadas sobre el total), con los mismos filtros.
     */
    public function metrics(int $companyId, array $f = []): array
    {
        $base = function () use ($companyId, $f) {
            $m = new QuoteModel();
            $this->applyFilters($m, $companyId, $f);

            return $m;
        };

        $total    = $base()->countAllResults();
        $approved = $base()->whereIn('status', ['aprobada', 'facturada'])->countAllResults();
        $sum      = (float) ($base()->selectSum('total')->first()['total'] ?? 0);

        return [
            'documents'  => $total,
            'approved'   => $approved,
            'amount'     => $sum,
            'conversion' => $total > 0 ? round($approved * 100 / $total, 1) : 0.0,
        ];
    }
}
