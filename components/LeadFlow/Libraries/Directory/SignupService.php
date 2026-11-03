<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Models\MemberModel;
use Components\LeadFlow\Models\ProfileModel;

/**
 * Alta pública: un abogado independiente o una firma se registran solos.
 *
 * Es la otra mitad del marketplace. El buscador resuelve cómo encuentra el visitante a
 * un abogado; sin esta pantalla, aparecer ahí exige que un Super Admin cree la empresa a
 * mano, y eso sirve para los diez primeros despachos y no para un directorio.
 *
 * **Dos formas de alta y una sola mecánica.** Un abogado independiente *es* una empresa
 * de la plataforma —lo dice el Manifest desde el primer día—, así que las dos salen del
 * mismo sitio y lo único que cambia es de quién es el perfil que nace con ella:
 *
 *  - `lawyer` → empresa + usuario + **su perfil de abogado**, en borrador.
 *  - `firm`   → empresa + usuario administrador + **el perfil de la firma**, en borrador.
 *    A sus abogados los registra él después, que es como trabaja un despacho: no se sabe
 *    de antemano cuántos son.
 *
 * **Nace SIN publicar, y es deliberado.** Un perfil sin áreas de práctica ni forma de
 * contacto no le sirve a nadie que busque un abogado, y salir así en el directorio
 * perjudica a quien acaba de registrarse. Publicar es un botón de su panel, y hasta
 * entonces está dentro pero no en la vitrina.
 *
 * **Todo o nada.** Usuario, rol, empresa, membresía, suscripción, función comercial y
 * perfil van en una transacción: un alta a medias deja a alguien con cuenta y sin
 * empresa, que en la práctica es una cuenta que no puede hacer nada y que nadie sabe
 * arreglar.
 *
 * **Lo que este servicio NO hace: iniciar la sesión.** `AuthController::completeLogin()`
 * es privado y hace más cosas de las que parece —seguimiento de acceso, rol activo,
 * idioma, bitácora y el desvío a 2FA—. Reimplementarlo aquí sería duplicar lógica del
 * núcleo que va a cambiar sin avisar. Se termina mandando a `/login` con el usuario
 * puesto, que es un paso más y ninguna sorpresa.
 */
class SignupService
{
    public const KINDS = ['lawyer', 'firm'];

    /**
     * Intentos por hora y por IP.
     *
     * **Por hora y no por minuto**: crear cuentas es caro y así un robot no puede abrir
     * cien empresas en una tarde. Y **cinco y no dos**, porque un despacho entero sale a
     * internet por una sola IP: con el freno muy corto, el segundo abogado del mismo
     * bufete recibe un «demasiados intentos» que no entiende y no vuelve.
     */
    public const RATE = 5;

    /** @return array{ok:bool,message:string,username:string} */
    public function register(array $in, string $ip): array
    {
        $kind = in_array((string) ($in['kind'] ?? ''), self::KINDS, true) ? (string) $in['kind'] : 'lawyer';

        // El plan se resuelve ANTES de tocar nada: sin un plan que incluya el servicio,
        // el alta crearía una cuenta que no puede usar el producto. Es mejor decir que el
        // registro está cerrado que dejar a alguien dentro sin nada que hacer.
        $plan = $this->plan();
        if (! $plan) {
            return $this->fail(lang('Lfl.suClosed'));
        }

        $first = $this->text($in['first_name'] ?? '', 50);
        $last  = $this->text($in['last_name'] ?? '', 50);
        $email = mb_strtolower($this->text($in['email'] ?? '', 120));
        $phone = $this->text($in['phone'] ?? '', 30);
        $pass  = (string) ($in['password'] ?? '');
        $firm  = $this->text($in['company_name'] ?? '', 120);

        if ($first === '' || $last === '') {
            return $this->fail(lang('Lfl.suVName'));
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail(lang('Lfl.vEmail'));
        }
        if (mb_strlen($pass) < 8) {
            return $this->fail(lang('Lfl.suVPassword'));
        }
        if ($pass !== (string) ($in['password_confirm'] ?? '')) {
            return $this->fail(lang('Lfl.suVPasswordMatch'));
        }
        // A una firma hay que preguntarle su nombre; el de un abogado independiente es
        // el suyo.
        if ($kind === 'firm' && $firm === '') {
            return $this->fail(lang('Lfl.suVCompany'));
        }

        $users = new \App\Models\UserModel();
        if ($users->where('email', $email)->countAllResults() > 0) {
            return $this->fail(lang('Lfl.suTaken'));
        }

        $person   = trim($first . ' ' . $last);
        $legal    = $kind === 'firm' ? $firm : $person;
        $username = $this->username($email);
        $db       = \Config\Database::connect();

        $db->transBegin();

        try {
            $userId = (int) $users->insert([
                'username'           => $username,
                'email'              => $email,
                'password'           => $pass,
                'first_name'         => $first,
                'last_name'          => $last,
                'phone'              => $phone !== '' ? $phone : null,
                'is_active'          => 1,
                'auth_method'        => 'password',
                'preferred_language' => 'es',
            ]);
            if ($userId < 1) {
                throw new \RuntimeException(implode(' ', $users->errors() ?: [lang('Lfl.suFailed')]));
            }

            /*
             * El rol **Admin**, que es el que la plataforma pone al administrador de una
             * empresa. No da poder sobre las empresas de los demás: el núcleo acota a
             * quien no es Super Admin a las suyas.
             *
             * Si esta instalación no tiene ese rol, el alta **se cae**: dejar la cuenta
             * sin rol produce a alguien que entra y no ve nada, y eso es más difícil de
             * diagnosticar que un error aquí.
             */
            $role = $db->table('roles')->select('id')->where('name', 'Admin')->get()->getRowArray();
            if (! $role) {
                throw new \RuntimeException(lang('Lfl.suNoRole'));
            }
            $users->assignRole($userId, (int) $role['id']);

            $now = date('Y-m-d H:i:s');

            // `contact_email`, no `email`: así se llama la columna en `companies`. Con el
            // nombre equivocado el INSERT falla, `insertID()` devuelve 0 y el error sale
            // tres pasos después hablando de otra cosa.
            $db->table('companies')->insert([
                'name'                => $legal,
                'slug'                => $this->companySlug($legal),
                'contact_email'       => $email,
                'contact_phone'       => $phone !== '' ? $phone : null,
                'is_active'           => 1,
                'type'                => 'company',
                'can_create_children' => 0,
                'owner_id'            => $userId,
                'plan_id'             => (int) $plan['id'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            $companyId = (int) $db->insertID();
            if ($companyId < 1) {
                throw new \RuntimeException(lang('Lfl.suFailed'));
            }

            $db->table('company_users')->insert([
                'company_id' => $companyId, 'user_id' => $userId, 'is_admin' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            // Sin suscripción vigente, `switch-company` no deja seleccionar la empresa:
            // el alta quedaría hecha y sin servir para nada.
            $days = (int) ($plan['service_days'] ?? 30) ?: 30;
            $db->table('subscriptions')->insert([
                'company_id' => $companyId,
                'plan_id'    => (int) $plan['id'],
                'start_date' => $now,
                'end_date'   => date('Y-m-d H:i:s', strtotime('+' . $days . ' day')),
                'status'     => 'trial',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // La función comercial de LeadFlow: **supervisor**. Siendo dueño ya vería
            // todo, pero sin fila en `cmp_lfl_members` las pantallas le avisan de que no
            // tiene función comercial, y empezar con una advertencia que no se puede
            // quitar es un mal recibimiento.
            (new MemberModel())->setFor($companyId, $userId, 'supervisor', null);

            $profiles = new ProfileModel();
            $profile  = [
                'company_id'   => $companyId,
                'kind'         => $kind,
                'user_id'      => $kind === 'lawyer' ? $userId : null,
                'slug'         => $profiles->freeSlug($kind === 'lawyer' ? $person : $legal),
                'display_name' => $kind === 'lawyer' ? $person : $legal,
                'email'        => $email,
                'phone'        => $phone !== '' ? $phone : null,
                // En borrador: ver la cabecera de esta clase.
                'status'       => 'draft',
            ];
            if (! $profiles->insert($profile)) {
                throw new \RuntimeException(implode(' ', $profiles->errors() ?: [lang('Lfl.suFailed')]));
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'LeadFlow registro: ' . $e->getMessage());

            return $this->fail($e->getMessage() ?: lang('Lfl.suFailed'));
        }

        log_message('info', 'LeadFlow: alta pública ' . $kind . ' · empresa ' . $companyId . ' · ' . $ip);

        return ['ok' => true, 'message' => '', 'username' => $username];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * El plan con el que nace quien se registra.
     *
     * El activo **más barato** de ámbito empresa que incluya LeadFlow y el directorio: es
     * el de entrada, y quien quiera más ya subirá. Si el núcleo no gradúa el servicio
     * —la columna no existe— vale cualquier plan de empresa, que es la misma regla
     * defensiva de `PlanGuard`: una columna ausente significa «esta instalación no gradúa
     * esto», no «está prohibido».
     */
    private function plan(): ?array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('plans')) {
            return null;
        }

        $b = $db->table('plans')->where('is_active', 1)->where('deleted_at', null);

        if ($db->fieldExists('scope', 'plans')) {
            $b->where('scope', 'company');
        }
        foreach (['leadflow_enabled', 'lfl_directory_enabled'] as $flag) {
            if ($db->fieldExists($flag, 'plans')) {
                $b->where($flag, 1);
            }
        }

        return $b->orderBy('price_amount', 'asc')->orderBy('id', 'asc')->get()->getRowArray() ?: null;
    }

    /**
     * Un slug libre para `companies`.
     *
     * Propio y no del núcleo: el componente no puede depender de un ayudante que quizá
     * no exista en la instalación de destino (§15). Mismo criterio que
     * `ProfileModel::freeSlug()`: se busca el primer sufijo libre en vez de confiar en
     * el azar, porque la dirección la va a leer una persona.
     */
    private function companySlug(string $name): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(
            strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
                'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n'])
        ))), '-');
        $base = mb_substr($base, 0, 90) ?: 'firma';

        $db   = \Config\Database::connect();
        $slug = $base;
        $n    = 1;
        while ($db->table('companies')->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    /**
     * Un nombre de usuario a partir del correo, único.
     *
     * No se le pide a la persona: es un dato que no le importa —entra con su correo o con
     * esto— y elegirlo es un campo más y un error más en un formulario que ya tiene seis.
     */
    private function username(string $email): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '', mb_strtolower(strstr($email, '@', true) ?: $email));
        $base = $base !== '' ? mb_substr($base, 0, 40) : 'usuario';

        $db = \Config\Database::connect();
        $u  = $base;
        $n  = 1;
        while ($db->table('users')->where('username', $u)->countAllResults() > 0) {
            $u = $base . ++$n;
        }

        return $u;
    }

    private function text($v, int $max): string
    {
        return mb_substr(trim(strip_tags((string) $v)), 0, $max);
    }

    private function fail(string $msg): array
    {
        return ['ok' => false, 'message' => $msg, 'username' => ''];
    }
}
