<?php

namespace Components\Copropiedad\Libraries;

use Components\Copropiedad\Models\AuthorizationModel;
use Components\Copropiedad\Models\ContactModel;
use Components\Copropiedad\Models\DocumentModel;
use Components\Copropiedad\Models\FamilyModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\PetModel;
use Components\Copropiedad\Models\RelationModel;
use Components\Copropiedad\Models\UnitModel;
use Components\Copropiedad\Models\VehicleModel;

/**
 * Las reglas del registro, en un solo sitio.
 *
 * Los controladores son delgados a propósito: aquí viven las tres cosas que **no**
 * pueden decidirse dos veces sin acabar discrepando —abrir y cerrar una relación,
 * derivar el estado de ocupación y limpiar lo que cuelga de algo que se borra— y las
 * comprobaciones de pertenencia, que el permiso no cubre (contrato §9).
 *
 * Todos los métodos reciben `$companyId` y **ninguno confía en el id que venga del
 * formulario**: se comprueba que la fila sea de esa empresa antes de tocarla.
 */
class RegistryService
{
    private UnitModel $units;
    private PersonModel $people;
    private RelationModel $relations;

    public function __construct()
    {
        $this->units     = new UnitModel();
        $this->people    = new PersonModel();
        $this->relations = new RelationModel();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Relación persona ↔ inmueble
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Abre una relación (alguien pasa a ser propietario / residente / arrendatario /
     * ocupante de un inmueble).
     *
     * @return array{ok:bool,message:string,id:int}
     */
    public function openRelation(array $in, int $companyId): array
    {
        $unitId   = (int) ($in['unit_id'] ?? 0);
        $personId = (int) ($in['person_id'] ?? 0);
        $role     = (string) ($in['role'] ?? '');

        // Pertenencia: el permiso `cop.create` no autoriza a tocar el inmueble de
        // otra empresa. Se comprueban las DOS puntas de la relación.
        if (! $this->units->owned($unitId, $companyId)) {
            return $this->fail(lang('Cop.errUnitNotFound'));
        }
        if (! $this->people->owned($personId, $companyId)) {
            return $this->fail(lang('Cop.errPersonNotFound'));
        }
        if (! in_array($role, RelationModel::ROLES, true)) {
            return $this->fail(lang('Cop.vRelationRole'));
        }

        // Pulsar dos veces «añadir» dejaba a la misma persona dos veces con el mismo
        // papel, y entonces los porcentajes de propiedad dejaban de sumar 100 sin que
        // nada lo explicara.
        if ($this->relations->existsCurrent($unitId, $personId, $role, $companyId)) {
            return $this->fail(lang('Cop.errRelationDuplicate'));
        }

        // Los campos opcionales se leen con `??`: este método es la API del servicio
        // y lo llaman el controlador (que manda el formulario entero), los datos
        // demo y las pruebas, que solo mandan lo que les interesa.
        $share = trim((string) ($in['share'] ?? ''));

        $data = [
            'company_id' => $companyId,
            'unit_id'    => $unitId,
            'person_id'  => $personId,
            'role'       => $role,
            // El porcentaje solo significa algo para un propietario.
            'share'      => $role === 'propietario' && $share !== '' ? (float) $share : null,
            'is_main'    => ! empty($in['is_main']) ? 1 : 0,
            'start_date' => ($in['start_date'] ?? '') ?: date('Y-m-d'),
            'is_current' => 1,
        ];

        if (! empty($in['notes'])) {
            $data['notes'] = $in['notes'];
        }

        if (! $this->relations->insert($data)) {
            return $this->fail(implode(' ', $this->relations->errors()));
        }

        $id = (int) $this->relations->getInsertID();

        // Un solo titular por papel: marcar a alguien desmarca al anterior.
        if ($data['is_main']) {
            $this->demoteOtherMains($unitId, $role, $id, $companyId);
        }

        $this->syncOccupancy($unitId, $companyId);

        return ['ok' => true, 'message' => lang('Cop.okRelationOpened'), 'id' => $id];
    }

    /**
     * Cierra una relación: le pone fecha de fin y la saca de las vigentes.
     *
     * **No la borra.** La fila cerrada ES el historial (ver la migración): borrarla
     * dejaría el inmueble sin memoria de quién vivió allí.
     */
    public function closeRelation(int $id, int $companyId, ?string $endDate = null): array
    {
        $row = $this->relations->owned($id, $companyId);
        if (! $row) {
            return $this->fail(lang('Cop.errRelationNotFound'));
        }
        if ((int) $row['is_current'] === 0) {
            return $this->fail(lang('Cop.errRelationAlreadyClosed'));
        }

        $this->relations->update($id, [
            'is_current' => 0,
            'end_date'   => $endDate ?: date('Y-m-d'),
            'is_main'    => 0,
        ]);

        $this->syncOccupancy((int) $row['unit_id'], $companyId);

        return ['ok' => true, 'message' => lang('Cop.okRelationClosed'), 'id' => $id];
    }

    /** Reabre una relación cerrada por error. */
    public function reopenRelation(int $id, int $companyId): array
    {
        $row = $this->relations->owned($id, $companyId);
        if (! $row) {
            return $this->fail(lang('Cop.errRelationNotFound'));
        }
        if ((int) $row['is_current'] === 1) {
            return $this->fail(lang('Cop.errRelationAlreadyOpen'));
        }
        // Reabrir no puede crear el duplicado que `openRelation` evita.
        if ($this->relations->existsCurrent((int) $row['unit_id'], (int) $row['person_id'], $row['role'], $companyId)) {
            return $this->fail(lang('Cop.errRelationDuplicate'));
        }

        $this->relations->update($id, ['is_current' => 1, 'end_date' => null]);
        $this->syncOccupancy((int) $row['unit_id'], $companyId);

        return ['ok' => true, 'message' => lang('Cop.okRelationReopened'), 'id' => $id];
    }

    /** Elimina una relación **registrada por error** (no es lo mismo que cerrarla). */
    public function deleteRelation(int $id, int $companyId): array
    {
        $row = $this->relations->owned($id, $companyId);
        if (! $row) {
            return $this->fail(lang('Cop.errRelationNotFound'));
        }
        $this->relations->delete($id);
        $this->syncOccupancy((int) $row['unit_id'], $companyId);

        return ['ok' => true, 'message' => lang('Cop.okDeleted'), 'id' => $id];
    }

    /** Un solo titular por papel e inmueble. */
    private function demoteOtherMains(int $unitId, string $role, int $keepId, int $companyId): void
    {
        $this->relations->builder()
            ->where('company_id', $companyId)
            ->where('unit_id', $unitId)
            ->where('role', $role)
            ->where('is_current', 1)
            ->where('id !=', $keepId)
            ->update(['is_main' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Estado de ocupación
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recalcula el estado de ocupación de un inmueble desde sus relaciones vigentes.
     *
     * El orden importa y es el del mundo real:
     *   1. hay arrendatario ................. **arrendado**
     *   2. el propietario vive allí ......... **propietario**
     *   3. vive alguien más ................. **ocupado**
     *   4. no vive nadie .................... **desocupado**
     *
     * **Lo que NO toca:** «en venta» y «en remodelación». Ninguna relación puede
     * deducirlos —son una decisión del administrador— así que si están puestos, se
     * respetan. Sin esta salvedad, marcar un apartamento «en venta» habría durado
     * hasta el siguiente cambio de residente.
     *
     * @return string el estado que quedó
     */
    public function syncOccupancy(int $unitId, int $companyId): string
    {
        $unit = $this->units->owned($unitId, $companyId);
        if (! $unit) {
            return '';
        }

        if (in_array($unit['occupancy'], UnitModel::MANUAL_OCCUPANCIES, true)) {
            return $unit['occupancy'];
        }

        $estado = $this->deriveOccupancy($unitId, $companyId);

        if ($estado !== $unit['occupancy']) {
            $this->units->update($unitId, ['occupancy' => $estado]);
        }

        return $estado;
    }

    /** El estado que corresponde a las relaciones vigentes, sin escribir nada. */
    public function deriveOccupancy(int $unitId, int $companyId): string
    {
        $vigentes = $this->relations->currentFor($unitId, $companyId);
        if (! $vigentes) {
            return 'desocupado';
        }

        $porPapel = [];

        foreach ($vigentes as $r) {
            $porPapel[$r['role']][] = (int) $r['person_id'];
        }

        if (! empty($porPapel['arrendatario'])) {
            return 'arrendado';
        }

        // ¿Alguno de los que viven aquí es además propietario?
        $propietarios = $porPapel['propietario'] ?? [];
        $residentes   = $porPapel['residente'] ?? [];

        if ($propietarios && array_intersect($propietarios, $residentes)) {
            return 'propietario';
        }

        foreach (RelationModel::LIVING_ROLES as $papel) {
            if (! empty($porPapel[$papel])) {
                return 'ocupado';
            }
        }

        return 'desocupado';
    }

    /**
     * Fija el estado a mano.
     *
     * Si se elige uno de los derivables, se **recalcula** en vez de creerse lo que
     * llega: poner «desocupado» en un apartamento con tres residentes registrados
     * habría dejado el registro contradiciéndose a sí mismo.
     */
    public function setOccupancy(int $unitId, int $companyId, string $estado): array
    {
        if (! $this->units->owned($unitId, $companyId)) {
            return $this->fail(lang('Cop.errUnitNotFound'));
        }
        if (! in_array($estado, UnitModel::OCCUPANCIES, true)) {
            return $this->fail(lang('Cop.vUnitOccupancy'));
        }

        if (in_array($estado, UnitModel::MANUAL_OCCUPANCIES, true)) {
            $this->units->update($unitId, ['occupancy' => $estado]);

            return ['ok' => true, 'message' => lang('Cop.okOccupancy'), 'id' => $unitId];
        }

        // Derivable: manda lo que digan las relaciones.
        $this->units->update($unitId, ['occupancy' => $this->deriveOccupancy($unitId, $companyId)]);

        return ['ok' => true, 'message' => lang('Cop.okOccupancyDerived'), 'id' => $unitId];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Bajas (y la limpieza que el soft-delete no hace)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Da de baja un inmueble y todo lo que colgaba de él.
     *
     * **Un soft-delete no dispara `ON DELETE CASCADE`**: la fila sigue ahí, así que la
     * FK está satisfecha y el motor no limpia nada. Sin esta limpieza explícita, el
     * apartamento borrado dejaba sus vehículos y sus mascotas apareciendo en los
     * listados globales, apuntando a un inmueble que ya no se puede abrir. (Mismo
     * caso que `sectors` → `companies.sector_id` en el núcleo.)
     */
    public function deleteUnit(int $id, int $companyId): array
    {
        $unit = $this->units->owned($id, $companyId);
        if (! $unit) {
            return $this->fail(lang('Cop.errUnitNotFound'));
        }

        $ahora = date('Y-m-d H:i:s');

        foreach ([
            (new RelationModel())->builder(),
            (new PetModel())->builder(),
            (new VehicleModel())->builder(),
            (new ContactModel())->builder(),
            (new AuthorizationModel())->builder(),
        ] as $b) {
            $b->where('company_id', $companyId)->where('unit_id', $id)
                ->where('deleted_at', null)->update(['deleted_at' => $ahora]);
        }

        // Los documentos son polimórficos: no tienen `unit_id`.
        (new DocumentModel())->builder()
            ->where('company_id', $companyId)->where('owner_type', 'unit')->where('owner_id', $id)
            ->where('deleted_at', null)->update(['deleted_at' => $ahora]);

        // Los parqueaderos y depósitos adscritos NO se borran: son inmuebles por
        // derecho propio y pueden reasignarse. Solo se desadscriben.
        $this->units->builder()
            ->where('company_id', $companyId)->where('parent_unit_id', $id)
            ->update(['parent_unit_id' => null, 'updated_at' => $ahora]);

        // Ni los vehículos que tuvieran aquí su puesto: pierden el puesto, no el auto.
        (new VehicleModel())->builder()
            ->where('company_id', $companyId)->where('parking_unit_id', $id)
            ->update(['parking_unit_id' => null, 'updated_at' => $ahora]);

        $this->units->delete($id);

        return ['ok' => true, 'message' => lang('Cop.okUnitDeleted'), 'id' => $id];
    }

    /**
     * Da de baja a una persona.
     *
     * Sus relaciones se **cierran**, no se borran: el historial del inmueble tiene que
     * seguir diciendo que vivió allí. Lo que sí se suelta son las referencias sueltas
     * (responsable de una mascota, dueño de un vehículo), que apuntarían a alguien
     * que ya no se puede abrir.
     */
    public function deletePerson(int $id, int $companyId): array
    {
        $person = $this->people->owned($id, $companyId);
        if (! $person) {
            return $this->fail(lang('Cop.errPersonNotFound'));
        }

        $hoy      = date('Y-m-d');
        $ahora    = date('Y-m-d H:i:s');
        $afectados = $this->relations
            ->where('company_id', $companyId)->where('person_id', $id)->where('is_current', 1)
            ->findAll();

        $this->relations->builder()
            ->where('company_id', $companyId)->where('person_id', $id)->where('is_current', 1)
            ->update(['is_current' => 0, 'end_date' => $hoy, 'is_main' => 0, 'updated_at' => $ahora]);

        foreach ([(new PetModel())->builder(), (new VehicleModel())->builder(), (new ContactModel())->builder()] as $b) {
            $b->where('company_id', $companyId)->where('person_id', $id)->update(['person_id' => null, 'updated_at' => $ahora]);
        }

        $this->people->delete($id);

        // Los inmuebles donde vivía cambian de estado.
        foreach (array_unique(array_column($afectados, 'unit_id')) as $unitId) {
            $this->syncOccupancy((int) $unitId, $companyId);
        }

        return ['ok' => true, 'message' => lang('Cop.okPersonDeleted'), 'id' => $id];
    }

    /**
     * Da de baja un grupo familiar **sin borrar a sus miembros**.
     *
     * Igual que borrar una categoría no borra sus productos: las personas quedan sin
     * agrupar. La FK es `SET NULL`, pero el soft-delete no la dispara, así que se
     * sueltan a mano.
     */
    public function deleteFamily(int $id, int $companyId): array
    {
        $families = new FamilyModel();
        $family   = $families->owned($id, $companyId);
        if (! $family) {
            return $this->fail(lang('Cop.errFamilyNotFound'));
        }

        $this->people->builder()
            ->where('company_id', $companyId)->where('family_id', $id)
            ->update(['family_id' => null, 'family_role' => null, 'updated_at' => date('Y-m-d H:i:s')]);

        $families->delete($id);

        return ['ok' => true, 'message' => lang('Cop.okFamilyDeleted'), 'id' => $id];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Lecturas compuestas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Todo lo que necesita la ficha del inmueble, resuelto de una vez.
     *
     * Está aquí y no en el controlador porque la ficha y el panel preguntan lo mismo,
     * y dos consultas distintas para la misma pregunta acaban dando dos respuestas.
     */
    public function unitCard(int $id, int $companyId): ?array
    {
        $unit = $this->units->owned($id, $companyId);
        if (! $unit) {
            return null;
        }

        $shareSum = $this->relations->shareSum($id, $companyId);

        return [
            'unit'           => $unit,
            'block'          => $unit['block_id'] ? (new \Components\Copropiedad\Models\BlockModel())->find((int) $unit['block_id']) : null,
            'parent'         => $unit['parent_unit_id'] ? $this->units->owned((int) $unit['parent_unit_id'], $companyId) : null,
            'attached'       => $this->units->attachedTo($id, $companyId),
            'current'        => $this->relations->currentFor($id, $companyId),
            'history'        => $this->relations->historyFor($id, $companyId),
            'pets'           => (new PetModel())->forUnit($id, $companyId),
            'vehicles'       => (new VehicleModel())->forUnit($id, $companyId),
            'contacts'       => (new ContactModel())->forUnit($id, $companyId),
            'authorizations' => (new AuthorizationModel())->forUnit($id, $companyId),
            'documents'      => (new DocumentModel())->forOwner('unit', $id, $companyId),
            'shareSum'       => $shareSum,
            // Aviso, no bloqueo: durante la carga inicial es normal ir a medias.
            'shareWarning'   => $shareSum > 0 && abs($shareSum - 100) > 0.01,
            'derived'        => $this->deriveOccupancy($id, $companyId),
        ];
    }

    /** @return array{ok:bool,message:string,id:int} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0];
    }
}
