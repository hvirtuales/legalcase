<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\AreaModel;
use Components\LeadFlow\Models\JurisdictionModel;
use Components\LeadFlow\Models\ProfileModel;

/**
 * Las reglas del perfil del directorio: alta, edición, publicación y baja.
 *
 * Todo lo que decide vive aquí y no en el controlador, porque un POST directo se
 * salta la vista: la pertenencia del usuario a la firma, que el área exista en el
 * catálogo común, que el cupo del plan alcance para publicar y que el slug sea único
 * en toda la plataforma.
 *
 * **Lo que llega del navegador no se limpia, se reconstruye**: las áreas, las
 * jurisdicciones, los idiomas y las modalidades se rearman contra las listas válidas,
 * así que un id inventado o de otra firma no llega a guardarse. Es el mismo criterio
 * del saneador de automatizaciones y del de formularios.
 */
class ProfileService
{
    private ProfileModel $profiles;

    public function __construct()
    {
        $this->profiles = new ProfileModel();
        Taxonomy::ensure();
    }

    private static function ok(string $message, int $id = 0): array
    {
        return ['ok' => true, 'message' => $message, 'id' => $id];
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0];
    }

    private static function str($v, int $max): ?string
    {
        $v = trim(strip_tags((string) ($v ?? '')));

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /**
     * Toma lo que viene del formulario y devuelve solo lo que puede guardarse.
     *
     * El `company_id` NO sale de aquí: lo pone el llamador desde la sesión.
     */
    private function clean(int $companyId, array $in): array
    {
        $kind = in_array($in['kind'] ?? '', ProfileModel::KINDS, true) ? $in['kind'] : 'lawyer';

        // Un perfil de abogado se ata a un usuario de ESTA firma; el de la firma, a nadie.
        $userId = (int) ($in['user_id'] ?? 0);
        $userId = ($kind === 'lawyer' && $userId > 0 && Team::isMember($companyId, $userId)) ? $userId : null;

        $modes = array_values(array_intersect(
            (array) ($in['consult_modes'] ?? []),
            ProfileModel::MODES
        ));

        // Los idiomas son texto libre, pero acotado: una lista de etiquetas cortas.
        $languages = [];
        foreach (array_slice(ProfileModel::listOf((string) ($in['languages'] ?? '')), 0, 8) as $l) {
            $languages[] = mb_substr(strip_tags($l), 0, 20);
        }

        return [
            'kind'             => $kind,
            'user_id'          => $userId,
            'display_name'     => self::str($in['display_name'] ?? '', 150),
            'headline'         => self::str($in['headline'] ?? '', 180),
            'bio'              => self::str($in['bio'] ?? '', 4000),
            'email'            => self::str($in['email'] ?? '', 150),
            'phone'            => self::str($in['phone'] ?? '', 30),
            'whatsapp'         => self::str($in['whatsapp'] ?? '', 30),
            'website'          => self::str($in['website'] ?? '', 255),
            'city'             => self::str($in['city'] ?? '', 80),
            'languages'        => $languages ? implode(', ', $languages) : null,
            'consult_modes'    => $modes ? implode(',', $modes) : null,
            'experience_years' => ($y = (int) ($in['experience_years'] ?? 0)) > 0 ? min($y, 70) : null,
            'bar_number'       => self::str($in['bar_number'] ?? '', 60),
            'bar_state'        => self::str($in['bar_state'] ?? '', 20),
            'bar_url'          => self::str($in['bar_url'] ?? '', 255),
            'seo_description'  => self::str($in['seo_description'] ?? '', 200),
        ];
    }

    /** Los ids que existen de verdad en el catálogo común. */
    private function validIds(string $model, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $m = $model === 'area' ? new AreaModel() : new JurisdictionModel();

        return array_map('intval', array_column(
            $m->select('id')->whereIn('id', $ids)->where('is_active', 1)->findAll(),
            'id'
        ));
    }

    /** Reescribe los pivotes de un perfil: se borra lo que sobra y se añade lo que falta. */
    private function syncPivot(int $profileId, string $table, string $column, array $ids): void
    {
        $db      = db_connect();
        $current = array_map('intval', array_column(
            $db->table($table)->select($column)->where('profile_id', $profileId)->get()->getResultArray(),
            $column
        ));

        $add = array_diff($ids, $current);
        $del = array_diff($current, $ids);

        if ($del) {
            $db->table($table)->where('profile_id', $profileId)->whereIn($column, $del)->delete();
        }
        foreach ($add as $id) {
            $db->table($table)->insert([
                'profile_id' => $profileId,
                $column      => $id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function create(int $companyId, array $in): array
    {
        $data = $this->clean($companyId, $in);
        if ($data['display_name'] === null) {
            return self::fail(lang('Lfl.vNameRequired'));
        }
        if ($data['kind'] === 'lawyer' && $data['user_id'] === null) {
            return self::fail(lang('Lfl.vProfileUser'));
        }
        // Un usuario, un perfil por firma: dos fichas de la misma persona compiten
        // entre sí en el buscador y ninguna de las dos queda al día.
        if ($data['user_id'] !== null && $this->profiles->ofUser($companyId, $data['user_id'])) {
            return self::fail(lang('Lfl.vProfileDuplicate'));
        }

        $data['company_id'] = $companyId;
        $data['slug']       = $this->profiles->freeSlug((string) $data['display_name']);
        $data['status']     = 'draft';

        if (! $this->profiles->insert($data)) {
            return self::fail(implode(' ', $this->profiles->errors()));
        }
        $id = (int) $this->profiles->getInsertID();

        $this->syncPivot($id, 'cmp_lfl_profile_areas', 'area_id', $this->validIds('area', (array) ($in['areas'] ?? [])));
        $this->syncPivot($id, 'cmp_lfl_profile_jurisdictions', 'jurisdiction_id',
            $this->validIds('jurisdiction', (array) ($in['jurisdictions'] ?? [])));

        return self::ok(lang('Lfl.okProfileCreated'), $id);
    }

    public function update(array $profile, array $in): array
    {
        $cid  = (int) $profile['company_id'];
        $data = $this->clean($cid, $in);
        if ($data['display_name'] === null) {
            return self::fail(lang('Lfl.vNameRequired'));
        }
        if ($data['kind'] === 'lawyer' && $data['user_id'] === null) {
            return self::fail(lang('Lfl.vProfileUser'));
        }
        $other = $data['user_id'] !== null ? $this->profiles->ofUser($cid, $data['user_id']) : null;
        if ($other && (int) $other['id'] !== (int) $profile['id']) {
            return self::fail(lang('Lfl.vProfileDuplicate'));
        }

        // El slug solo cambia si lo pide explícitamente: una URL publicada que se
        // mueve sola rompe los enlaces que ya circulan.
        if (! empty($in['renew_slug'])) {
            $data['slug'] = $this->profiles->freeSlug((string) $data['display_name'], (int) $profile['id']);
        }

        if (! $this->profiles->update((int) $profile['id'], $data)) {
            return self::fail(implode(' ', $this->profiles->errors()));
        }

        $this->syncPivot((int) $profile['id'], 'cmp_lfl_profile_areas', 'area_id',
            $this->validIds('area', (array) ($in['areas'] ?? [])));
        $this->syncPivot((int) $profile['id'], 'cmp_lfl_profile_jurisdictions', 'jurisdiction_id',
            $this->validIds('jurisdiction', (array) ($in['jurisdictions'] ?? [])));

        return self::ok(lang('Lfl.okUpdated'), (int) $profile['id']);
    }

    /**
     * Publica un perfil.
     *
     * Aquí se hace valer el plan **en el servicio**: la bandera del directorio y el
     * cupo de perfiles. Y se exige un mínimo de contenido, porque un perfil vacío en
     * el directorio le hace daño a quien lo publica: sin área de práctica no aparece
     * en ningún filtro, y sin forma de contacto no sirve de nada.
     */
    public function publish(array $profile): array
    {
        $cid = (int) $profile['company_id'];

        if ($block = (new PlanGuard())->publishBlockReason($cid)) {
            return self::fail($block);
        }

        $areas = db_connect()->table('cmp_lfl_profile_areas')
            ->where('profile_id', (int) $profile['id'])->countAllResults();
        if ($areas === 0) {
            return self::fail(lang('Lfl.vProfileNeedArea'));
        }
        if (trim((string) $profile['email']) === '' && trim((string) $profile['phone']) === ''
            && trim((string) $profile['whatsapp']) === '') {
            return self::fail(lang('Lfl.vProfileNeedContact'));
        }

        $this->profiles->update((int) $profile['id'], [
            'status'       => 'published',
            'published_at' => $profile['published_at'] ?: date('Y-m-d H:i:s'),
        ]);

        return self::ok(lang('Lfl.okProfilePublished'), (int) $profile['id']);
    }

    /**
     * Marca el perfil como verificado, o le retira la verificación.
     *
     * **Esto lo hace la PLATAFORMA y no la firma** (lo comprueba el controlador): una
     * insignia que cada uno se pone a sí mismo no dice nada, y el §8 del insumo la pide
     * justamente para que el usuario pueda confiar en lo que lee. Queda registrado quién
     * verificó y cuándo, porque una validación sin fecha no se puede caducar.
     */
    public function verify(array $profile, ?int $userId, bool $verified = true): array
    {
        $this->profiles->update((int) $profile['id'], [
            'verified_at' => $verified ? date('Y-m-d H:i:s') : null,
            'verified_by' => $verified ? $userId : null,
        ]);

        return self::ok(lang($verified ? 'Lfl.okProfileVerified' : 'Lfl.okProfileUnverified'), (int) $profile['id']);
    }

    /** Lo saca del directorio sin borrarlo: vuelve a borrador y deja de ser visible. */
    public function unpublish(array $profile): array
    {
        $this->profiles->update((int) $profile['id'], ['status' => 'draft']);

        return self::ok(lang('Lfl.okProfileUnpublished'), (int) $profile['id']);
    }

    public function delete(array $profile): array
    {
        $this->profiles->delete((int) $profile['id']);

        return self::ok(lang('Lfl.okDeleted'), (int) $profile['id']);
    }
}
