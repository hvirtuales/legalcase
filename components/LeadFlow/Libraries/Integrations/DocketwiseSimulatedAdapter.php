<?php

namespace Components\LeadFlow\Libraries\Integrations;

use Components\LeadFlow\Models\SimRecordModel;

/**
 * Docketwise SIMULADO: el mismo contrato que el adaptador real, con una cuenta
 * falsa por firma guardada en `cmp_lfl_sim_records`.
 *
 * Existe porque el acceso a la API real exige una solicitud a Docketwise (no hay
 * sandbox). Con él el flujo completo del §38 es demostrable hoy: autorizar la
 * cuenta, detectar un contacto ya existente, crear Contact + Matter, transferir
 * notas y documentos, y ver los ids externos.
 *
 * Reproduce los límites de la API v1 que importan para el diseño: el listado de
 * contactos no filtra (se trae entero), y los datos que devuelve son los mismos
 * campos que devuelve la real.
 *
 * `$failOn` permite forzar un fallo en un paso concreto (lo usa el autotest para
 * comprobar que un reintento continúa sin duplicar).
 */
class DocketwiseSimulatedAdapter implements IntegrationAdapter
{
    public static ?string $failOn = null;

    private SimRecordModel $store;

    public function __construct(private int $companyId)
    {
        $this->store = new SimRecordModel();
    }

    public function provider(): string
    {
        return 'docketwise';
    }

    public function mode(): string
    {
        return 'simulated';
    }

    private function maybeFail(string $action): void
    {
        if (self::$failOn === $action) {
            throw new IntegrationException(lang('Lfl.simForcedError', [$action]), 500);
        }
    }

    public function authorizeUrl(string $state): string
    {
        return base_url('leadflow/docketwise/simulado/autorizar') . '?state=' . rawurlencode($state);
    }

    public function exchangeCode(string $code): array
    {
        if (! preg_match('/^sim_[a-f0-9]{16}$/', $code)) {
            throw new IntegrationException(lang('Lfl.errOauthCode'), 400);
        }
        $this->seed();

        return ['access_token' => 'sim_at_' . bin2hex(random_bytes(12)), 'refresh_token' => 'sim_rt_' . bin2hex(random_bytes(12)), 'expires_in' => 180 * 86400];
    }

    public function refresh(string $refreshToken): array
    {
        return ['access_token' => 'sim_at_' . bin2hex(random_bytes(12)), 'refresh_token' => $refreshToken, 'expires_in' => 180 * 86400];
    }

    /**
     * La cuenta falsa arranca con dos abogados, los tipos de matter más comunes de
     * inmigración y tres contactos ya existentes (uno de ellos coincide con un
     * lead de los datos demo, para poder ver la detección de duplicados).
     */
    private function seed(): void
    {
        if ($this->store->forCompany($this->companyId)->where('entity', 'user')->countAllResults() > 0) {
            return;
        }
        foreach ([['Laura', 'Méndez', 'laura.mendez@firm.test'], ['Daniel', 'Ortega', 'daniel.ortega@firm.test']] as [$f, $l, $e]) {
            $this->store->add($this->companyId, 'user', ['email' => $e, 'attorney_profile' => ['first_name' => $f, 'last_name' => $l]]);
        }
        foreach (['Family-Based Petition (I-130)', 'Asylum (I-589)', 'Naturalization (N-400)', 'Employment-Based Visa',
            'Adjustment of Status (I-485)', 'Removal Defense', 'General Consultation'] as $name) {
            $this->store->add($this->companyId, 'matter_type', ['name' => $name]);
        }
        foreach ([['Carlos', 'Ramírez', 'carlos.ramirez@example.com', '13055550101'],
            ['Ana', 'Torres', 'ana.torres@example.com', '17865550122'],
            ['María', 'López', 'maria.lopez@example.com', '13055550199']] as [$f, $l, $e, $p]) {
            $this->store->add($this->companyId, 'contact', ['first_name' => $f, 'last_name' => $l, 'email' => $e,
                'lead' => false, 'lead_phone' => $p, 'type' => 'Person']);
        }
    }

    public function listContacts(int $page): array
    {
        $this->maybeFail('list_contacts');
        if ($page > 1) {
            return ['items' => [], 'next' => null];
        }
        $items = [];
        foreach ($this->store->all($this->companyId, 'contact') as $id => $c) {
            $items[] = ['id' => (string) $id, 'first_name' => $c['first_name'] ?? null, 'last_name' => $c['last_name'] ?? null,
                'email' => $c['email'] ?? null, 'phone' => $c['lead_phone'] ?? null];
        }

        return ['items' => $items, 'next' => null];
    }

    public function getContact(string $id): ?array
    {
        return ctype_digit($id) ? $this->store->one($this->companyId, 'contact', (int) $id) : null;
    }

    public function createContact(array $data): string
    {
        $this->maybeFail('create_contact');

        return $this->store->add($this->companyId, 'contact', [
            'first_name' => $data['first_name'], 'middle_name' => $data['middle_name'] ?? null, 'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null, 'type' => 'Person', 'lead' => false, 'lead_phone' => $data['phone'] ?? null,
            'physical_address' => ['city' => $data['city'] ?? null, 'country' => $data['country'] ?? null],
        ]);
    }

    /**
     * Parche sobre la cuenta falsa. El teléfono se guarda en `lead_phone`, que es de
     * donde lo lee `listContacts()` aquí: el simulado tiene su propia forma y lo que
     * importa es que la prueba vea el dato reflejado, igual que en el real.
     */
    public function updateContact(string $id, array $data): void
    {
        $this->maybeFail('update_contact');
        if (! ctype_digit($id)) {
            return;
        }

        $changes = [];
        foreach (['first_name', 'middle_name', 'last_name', 'email'] as $k) {
            if (array_key_exists($k, $data)) {
                $changes[$k] = $data[$k];
            }
        }
        if (! empty($data['phone'])) {
            $changes['lead_phone'] = trim((string) $data['phone']);
        }
        if ($changes === []) {
            return;
        }

        $this->store->patch($this->companyId, 'contact', (int) $id, $changes);
    }

    public function listUsers(): array
    {
        $out = [];
        foreach ($this->store->all($this->companyId, 'user') as $id => $u) {
            $out[(string) $id] = trim(($u['attorney_profile']['first_name'] ?? '') . ' ' . ($u['attorney_profile']['last_name'] ?? '')) ?: ($u['email'] ?? '#' . $id);
        }

        return $out;
    }

    public function listMatterTypes(): array
    {
        $out = [];
        foreach ($this->store->all($this->companyId, 'matter_type') as $id => $t) {
            $out[(string) $id] = (string) ($t['name'] ?? '#' . $id);
        }

        return $out;
    }

    public function createMatter(array $data): string
    {
        $this->maybeFail('create_matter');
        if (! $this->getContact((string) $data['client_id'])) {
            throw new IntegrationException(lang('Lfl.simNoClient'), 422);
        }

        return $this->store->add($this->companyId, 'matter', [
            'client_id' => (int) $data['client_id'], 'title' => $data['title'], 'description' => $data['description'] ?? null,
            'user_ids' => array_map('intval', $data['user_ids'] ?? []), 'status' => null,
        ]);
    }

    public function createNote(array $data): string
    {
        $this->maybeFail('create_note');

        return $this->store->add($this->companyId, 'note', [
            'matter_id' => $data['matter_id'] ?? null, 'client_id' => $data['client_id'] ?? null,
            'title' => $data['title'], 'content' => mb_substr($data['content'], 0, 5000),
        ]);
    }

    public function createDocument(array $data): string
    {
        $this->maybeFail('create_document');
        if (base64_decode($data['base64'], true) === false) {
            throw new IntegrationException(lang('Lfl.simBadBase64'), 422);
        }

        // No se guarda el contenido: basta con saber que llegó y cuánto pesaba.
        return $this->store->add($this->companyId, 'document', [
            'title' => $data['title'], 'filename' => $data['filename'], 'client_id' => $data['client_id'] ?? null,
            'matter_id' => $data['matter_id'] ?? null, 'size' => (int) (strlen($data['base64']) * 3 / 4),
        ]);
    }
}
