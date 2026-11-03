<?php

namespace Components\LeadFlow\Libraries\Uscis;

use Components\LeadFlow\Libraries\Crypto;
use Components\LeadFlow\Models\SettingsModel;

/**
 * La conexión de UNA firma con USCIS.
 *
 * Aquí vive todo lo que depende de saber de qué firma hablamos: sus credenciales, su
 * modo y —cuando llegue la fase 2— su cupo y su caché. El adaptador solo habla HTTP.
 *
 * **Por qué por firma y no por plataforma.** USCIS da 1.000 consultas al día por
 * credencial. Con una credencial de plataforma, una firma con tráfico se las agota a
 * todas las demás antes del mediodía y no hay forma de repartir el daño. Con
 * credenciales propias, quien consume es quien gasta su límite, y una firma sin
 * configurar no le quita servicio a nadie.
 *
 * El precio es de puesta en marcha: cada firma tiene que registrar su propia app en el
 * portal de USCIS. Por eso la pantalla de Configuración lo explica y trae un botón de
 * prueba: sin él, una credencial mal pegada se descubre el día que un cliente consulta
 * su caso y no funciona.
 */
class UscisService
{
    /** Formato de un número de recibo: tres letras de centro y diez dígitos. */
    public const RECEIPT_RE = '/^[A-Z]{3}[0-9]{10}$/';

    /**
     * El proveedor de esta firma, según cómo esté configurada.
     *
     * Devuelve el simulado si la firma está en modo simulado o si no ha pegado
     * credenciales: así la pantalla se puede recorrer entera antes de tener nada, y
     * nunca se sale a la red sin querer.
     */
    public function provider(int $companyId): CaseStatusProvider
    {
        $cfg = $this->config($companyId);

        if ($cfg['mode'] !== 'live' || $cfg['client_id'] === '' || $cfg['client_secret'] === '') {
            return new UscisSimulatedAdapter();
        }

        return new UscisHttpAdapter($cfg['client_id'], $cfg['client_secret']);
    }

    /**
     * Las credenciales de la firma, descifradas.
     *
     * @return array{client_id:string,client_secret:string,mode:string,configured:bool}
     */
    public function config(int $companyId): array
    {
        $row = (new SettingsModel())->forCompany($companyId);

        $id     = trim((string) ($row['uscis_client_id'] ?? ''));
        $secret = (string) (Crypto::dec($row['uscis_client_secret'] ?? null) ?? '');
        $mode   = ($row['uscis_mode'] ?? 'simulated') === 'live' ? 'live' : 'simulated';

        return [
            'client_id'     => $id,
            'client_secret' => $secret,
            'mode'          => $mode,
            'configured'    => $id !== '' && $secret !== '',
        ];
    }

    /**
     * Guarda las credenciales de la firma.
     *
     * **Un secret vacío no borra el guardado.** El formulario nunca muestra el secret
     * —es un dato que no se puede volver a leer en el portal de USCIS—, así que
     * enviarlo en blanco significa «no lo cambio», no «bórralo». Para quitarlo se
     * desconecta, que es explícito.
     *
     * @return array{ok:bool,message:string}
     */
    public function save(int $companyId, string $clientId, string $clientSecret, string $mode): array
    {
        $clientId = trim($clientId);
        $mode     = $mode === 'live' ? 'live' : 'simulated';

        $data = ['uscis_client_id' => $clientId ?: null, 'uscis_mode' => $mode];

        if (trim($clientSecret) !== '') {
            $data['uscis_client_secret'] = Crypto::enc(trim($clientSecret));
        }

        // Pasar a «real» sin credenciales dejaría a la firma consultando contra nada.
        if ($mode === 'live') {
            $actual = $this->config($companyId);
            $tieneSecret = trim($clientSecret) !== '' || $actual['client_secret'] !== '';
            if ($clientId === '' || ! $tieneSecret) {
                return ['ok' => false, 'message' => lang('Lfl.uscNeedCredentials')];
            }
        }

        (new SettingsModel())->saveFor($companyId, $data);

        return ['ok' => true, 'message' => lang('Lfl.uscSaved')];
    }

    /**
     * Envuelve las comprobaciones con el veredicto y el tiempo que costó.
     *
     * `ok` es falso si alguna falló. Un `warn` NO lo tumba: avisa de algo que hay que
     * mirar —un recibo de sandbox contra producción, por ejemplo— pero no significa
     * que la conexión esté rota.
     *
     * @param array<int,array{label:string,status:string,detail:string}> $checks
     *
     * @return array{ok:bool,ms:int,checks:array}
     */
    private function wrap(array $checks, float $inicio): array
    {
        $fallos = array_filter($checks, static fn (array $c): bool => $c['status'] === 'error');

        return [
            'ok'     => $fallos === [],
            'ms'     => (int) round((microtime(true) - $inicio) * 1000),
            'checks' => $checks,
        ];
    }

    /** Borra las credenciales y devuelve la firma al modo simulado. */
    public function disconnect(int $companyId): array
    {
        (new SettingsModel())->saveFor($companyId, [
            'uscis_client_id'     => null,
            'uscis_client_secret' => null,
            'uscis_mode'          => 'simulated',
        ]);

        return ['ok' => true, 'message' => lang('Lfl.uscDisconnected')];
    }

    /**
     * Consulta el estado de un caso: caché primero, red solo si hace falta.
     *
     * El orden no es una optimización, es lo que hace viable abrir esto al público con
     * 1.000 consultas al día:
     *
     *  1. **Formato.** Un recibo mal escrito es el error más común; comprobarlo cuesta
     *     cero y salir a la red para descubrirlo cuesta una de las 1.000.
     *  2. **Caché fresca.** Doce horas. Cien personas mirando su caso dos veces al día
     *     son 200 llamadas sin caché y 100 con ella.
     *  3. **Cupo.** Si no queda, se sirve lo último que se supo CON SU FECHA en vez de
     *     un error: «estado a día 5 de octubre» es útil; «no se pudo consultar» no.
     *  4. **Red**, y se guarda.
     *
     * Si la red falla y hay algo cacheado, gana lo cacheado. Un servicio ajeno caído no
     * puede dejar en blanco una pantalla que tenía algo que enseñar.
     *
     * @return array{ok:bool,message:string,case:?array,cached:bool,checked_at:?string,stale:bool}
     */
    public function status(int $companyId, string $receipt): array
    {
        $receipt = strtoupper(preg_replace('/\s+/', '', trim($receipt)) ?? '');

        if (! preg_match(self::RECEIPT_RE, $receipt)) {
            return $this->out(false, lang('Lfl.uscBadReceipt'), null, false, null, false);
        }

        $cache = new \Components\LeadFlow\Models\CaseStatusModel();
        $fila  = $cache->lookup($companyId, $receipt);

        if (\Components\LeadFlow\Models\CaseStatusModel::isFresh($fila)) {
            return $this->out(true, '', \Components\LeadFlow\Models\CaseStatusModel::toCase($fila),
                true, $fila['checked_at'], false);
        }

        $usage = new \Components\LeadFlow\Models\UscisUsageModel();

        // El cupo solo manda cuando de verdad vamos a salir a la red. En modo simulado
        // no se gasta nada, así que tampoco se cuenta ni se corta.
        $real = $this->config($companyId)['mode'] === 'live';

        if ($real && $usage->left($companyId) <= 0) {
            return $fila
                ? $this->out(true, lang('Lfl.uscServedStale'), \Components\LeadFlow\Models\CaseStatusModel::toCase($fila),
                    true, $fila['checked_at'], true)
                : $this->out(false, lang('Lfl.uscQuotaSpent'), null, false, null, false);
        }

        // 5 consultas por segundo es el techo de USCIS. El freno es por firma porque
        // el cupo también lo es.
        if ($real && ! service('throttler')->check('lfl_uscis_' . $companyId, 5, 1)) {
            return $fila
                ? $this->out(true, lang('Lfl.uscServedStale'), \Components\LeadFlow\Models\CaseStatusModel::toCase($fila),
                    true, $fila['checked_at'], true)
                : $this->out(false, lang('Lfl.uscTooFast'), null, false, null, false);
        }

        try {
            $caso = $this->provider($companyId)->caseStatus($receipt);
            if ($real) {
                $usage->record($companyId);
            }
            $guardada = $cache->store($companyId, $caso);

            return $this->out(true, '', $caso, false, $guardada['checked_at'] ?? date('Y-m-d H:i:s'), false);
        } catch (UscisException $e) {
            if ($real) {
                // La llamada salió, aunque la respuesta fuera un «no existe»: USCIS la
                // cuenta igual y nosotros también, o el cupo propio mentiría.
                $usage->record($companyId);
            }

            // Un fallo de red no borra lo que ya sabíamos.
            if ($fila && ! $this->isNotFound($e)) {
                return $this->out(true, lang('Lfl.uscServedStale'),
                    \Components\LeadFlow\Models\CaseStatusModel::toCase($fila), true, $fila['checked_at'], true);
            }

            return $this->out(false, $e->getMessage(), null, false, null, false);
        }
    }

    /**
     * ¿Es un «ese caso no existe» y no un fallo de conexión?
     *
     * Importa porque los dos se tratan al revés: ante un fallo de red se sirve lo
     * cacheado, pero ante un «no existe» no — si USCIS dice que ese recibo ya no
     * devuelve nada, enseñar el estado viejo sería afirmar algo que USCIS ya no afirma.
     */
    private function isNotFound(UscisException $e): bool
    {
        return $e->getMessage() === lang('Lfl.uscNotFound');
    }

    /** @return array{ok:bool,message:string,case:?array,cached:bool,checked_at:?string,stale:bool} */
    private function out(bool $ok, string $message, ?array $case, bool $cached, ?string $checkedAt, bool $stale): array
    {
        return [
            'ok'         => $ok,
            'message'    => $message,
            'case'       => $case,
            'cached'     => $cached,
            'checked_at' => $checkedAt,
            // `stale` es lo que la vista usa para decir «esto es de hace un rato».
            // Sin ese aviso, un dato viejo se lee como si fuera de ahora mismo.
            'stale'      => $stale,
        ];
    }

    /**
     * Cuánto cupo le queda hoy a la firma, para enseñarlo en Configuración.
     *
     * @return array{used:int,left:int,limit:int}
     */
    public function quota(int $companyId): array
    {
        $usage = new \Components\LeadFlow\Models\UscisUsageModel();

        return [
            'used'  => $usage->used($companyId),
            'left'  => $usage->left($companyId),
            'limit' => \Components\LeadFlow\Models\UscisUsageModel::DAILY_LIMIT,
        ];
    }
    /** Cuántos casos se refrescan como mucho por firma y pasada. */
    public const TICK_BATCH = 25;

    /**
     * Refresca los casos de los leads activos y avisa de los que cambiaron.
     *
     * Lo llama `leadflow:tick`. Es el único sitio donde un cambio de estado se
     * convierte en un acontecimiento: nadie pulsa nada, lo detecta el reloj.
     *
     * Tres frenos, por el cupo:
     *  - solo firmas conectadas en modo real;
     *  - solo leads abiertos o ganados con recibo —un lead perdido ya no interesa—;
     *  - **25 por firma y pasada**, los más antiguos sin mirar primero. Una firma con
     *    400 leads abiertos no puede gastarse media cuota diaria en un solo tick.
     *
     * @return array{checked:int,changed:int}
     */
    public function refreshLeads(?int $onlyCompany = null): array
    {
        $db    = db_connect();
        $leads = new \Components\LeadFlow\Models\LeadModel();
        $out   = ['checked' => 0, 'changed' => 0];

        // Las firmas que de verdad pueden consultar. Sin esto se recorrerían leads de
        // firmas en simulado, que no gastan cupo pero tampoco informan de nada real.
        $q = $db->table('cmp_lfl_settings')->select('company_id')->where('uscis_mode', 'live')
            ->where('uscis_client_id IS NOT NULL', null, false);
        if ($onlyCompany) {
            $q->where('company_id', $onlyCompany);
        }

        foreach ($q->get()->getResultArray() as $fila) {
            $cid = (int) $fila['company_id'];
            if (! $this->config($cid)['configured']) {
                continue;
            }

            $pendientes = $leads->where('company_id', $cid)
                ->whereIn('status', ['open', 'won'])
                ->where('uscis_receipt IS NOT NULL', null, false)
                ->where('deleted_at', null)
                // Los que llevan más tiempo sin mirar, primero. NULL es «nunca», y en
                // MySQL ordena antes que cualquier fecha, que es justo lo que se quiere.
                ->orderBy('uscis_checked_at', 'asc')
                ->findAll(self::TICK_BATCH);

            foreach ($pendientes as $lead) {
                $antes = (string) ($lead['uscis_status'] ?? '');
                $res   = $this->status($cid, (string) $lead['uscis_receipt']);

                // Sin cupo o sin red no se marca como revisado: así le vuelve a tocar
                // en la siguiente pasada en vez de quedarse al final de la cola.
                if (! $res['ok'] || $res['case'] === null || ! empty($res['stale'])) {
                    continue;
                }

                $out['checked']++;
                $ahora = (string) ($res['case']['status'] ?? '');

                $leads->skipValidation(true)->protect(false)->update((int) $lead['id'], [
                    'uscis_status'     => $ahora ?: null,
                    'uscis_checked_at' => date('Y-m-d H:i:s'),
                ]);
                $leads->skipValidation(false);

                if ($ahora === '' || $ahora === $antes) {
                    continue;
                }

                $out['changed']++;

                /*
                 * El `ref` lleva el estado: así `once_per_lead` no bloquea el siguiente
                 * cambio del mismo caso, pero sí impide avisar dos veces del mismo.
                 */
                \Components\LeadFlow\Libraries\AutomationEngine::fire('case_status_changed',
                    $leads->find((int) $lead['id']),
                    ['ref' => 'case:' . (int) $lead['id'] . ':' . md5($ahora), 'uscis_status' => $ahora]);
            }
        }

        return $out;
    }

    /**
     * Diagnóstico de la conexión, con el mismo formato que el de Docketwise.
     *
     * Hace lo mínimo que demuestra que la cosa funciona: pide un token y consulta UN
     * caso de prueba. Dos llamadas, no veinte: comprobar la conexión no puede costarle
     * a la firma una parte apreciable de su cupo diario.
     *
     * Devuelve la MISMA forma que el diagnóstico de Docketwise para poder reutilizar
     * `partials/_diagnostic` sin tocarlo: dos pantallas que enseñan lo mismo no pueden
     * tener dos maneras de pintarlo.
     *
     * @return array{ok:bool,ms:int,checks:array<int,array{label:string,status:string,detail:string}>}
     */
    public function diagnose(int $companyId): array
    {
        $cfg    = $this->config($companyId);
        $inicio = microtime(true);
        $out    = [];
        $push   = static function (string $label, string $status, string $detail = '') use (&$out): void {
            $out[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
        };

        $push(lang('Lfl.uscDiagMode'), 'ok', lang('Lfl.uscMode_' . $cfg['mode']));

        if ($cfg['mode'] !== 'live') {
            $push(lang('Lfl.uscDiagCreds'), 'skip', lang('Lfl.uscDiagSimulated'));
            $caso = (new UscisSimulatedAdapter())->caseStatus(array_key_first(UscisSimulatedAdapter::CASES));
            $push(lang('Lfl.uscDiagQuery'), 'ok', $caso['receipt'] . ' · ' . (string) $caso['status']);

            return $this->wrap($out, $inicio);
        }

        if (! $cfg['configured']) {
            $push(lang('Lfl.uscDiagCreds'), 'error', lang('Lfl.uscNoCredentials'));

            return $this->wrap($out, $inicio);
        }

        $adapter = new UscisHttpAdapter($cfg['client_id'], $cfg['client_secret']);

        try {
            $adapter->token();
            $push(lang('Lfl.uscDiagCreds'), 'ok', lang('Lfl.uscDiagTokenOk'));
        } catch (UscisException $e) {
            $push(lang('Lfl.uscDiagCreds'), 'error', $e->getMessage());

            // Sin token no tiene sentido intentar la consulta: sería gastar cupo para
            // obtener el mismo error con otro nombre.
            return $this->wrap($out, $inicio);
        }

        $prueba = array_key_first(UscisSimulatedAdapter::CASES);

        try {
            $caso = $adapter->caseStatus($prueba);
            $push(lang('Lfl.uscDiagQuery'), 'ok', $caso['receipt'] . ' · ' . (string) $caso['status']);
        } catch (UscisException $e) {
            // Contra producción, un recibo de sandbox NO existe: eso no significa que
            // la conexión esté mal, y decir «error» aquí mandaría a la firma a revisar
            // unas credenciales que acaban de funcionar.
            $push(lang('Lfl.uscDiagQuery'), 'warn', $e->getMessage());
        }

        return $this->wrap($out, $inicio);
    }
}
