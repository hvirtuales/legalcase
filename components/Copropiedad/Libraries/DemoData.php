<?php

namespace Components\Copropiedad\Libraries;

use Components\Copropiedad\Models\AuthorizationModel;
use Components\Copropiedad\Models\BlockModel;
use Components\Copropiedad\Models\ContactModel;
use Components\Copropiedad\Models\FamilyModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\PetModel;
use Components\Copropiedad\Models\UnitModel;
use Components\Copropiedad\Models\VehicleModel;

/**
 * Datos de ejemplo: un conjunto pequeño pero **completo**, para poder ver el
 * componente funcionando sin teclear cien fichas.
 *
 * Se cuida que la muestra tenga de todo lo que las pantallas saben enseñar —los
 * cuatro papeles, los seis estados de ocupación, historial cerrado, coeficientes que
 * suman 100, una vacuna vencida, una autorización caducada— porque unos datos demo
 * planos hacen creer que la mitad de la aplicación no funciona.
 *
 * **Solo se siembra si la empresa no tiene nada** (`isEmpty()`): nunca pisa datos
 * reales. Y respeta el plan, como cualquier otra vía de alta.
 */
class DemoData
{
    private int $companyId;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    /** Sin inmuebles no hay registro: es la señal de que la empresa está vacía. */
    public function isEmpty(): bool
    {
        return (new UnitModel())->countFor($this->companyId) === 0;
    }

    /**
     * Siembra el ejemplo.
     *
     * @return array{ok:bool,message:string}
     */
    public function seed(): array
    {
        if (! $this->isEmpty()) {
            return ['ok' => false, 'message' => lang('Cop.demoNotEmpty')];
        }

        $plan = new PlanGuard();
        if ($motivo = $plan->blockReason($this->companyId)) {
            return ['ok' => false, 'message' => $motivo];
        }

        // Con cupo justo, mejor no empezar: sembrar a medias deja un conjunto
        // incoherente (apartamentos sin su parqueadero, coeficientes que no suman).
        $quedan = $plan->unitsLeft($this->companyId);
        if ($quedan !== null && $quedan < 12) {
            return ['ok' => false, 'message' => lang('Cop.demoNoQuota', [12])];
        }

        $svc      = new RegistryService();
        $units    = new UnitModel();
        $people   = new PersonModel();
        $families = new FamilyModel();

        // ── Estructura ───────────────────────────────────────────────────────
        $blocks = new BlockModel();
        $t1     = $this->id($blocks, ['company_id' => $this->companyId, 'name' => 'Torre 1', 'code' => 'T1', 'kind' => 'torre', 'floors' => 8, 'sort_order' => 1]);
        $t2     = $this->id($blocks, ['company_id' => $this->companyId, 'name' => 'Torre 2', 'code' => 'T2', 'kind' => 'torre', 'floors' => 8, 'sort_order' => 2]);

        // ── Inmuebles: coeficientes elegidos para que sumen 100 ───────────────
        $aptos = [];

        $definicion = [
            [$t1, '101', 'apartamento', '1', 72.50, 11.5, 3, 2],
            [$t1, '201', 'apartamento', '2', 72.50, 11.5, 3, 2],
            [$t1, '301', 'apartamento', '3', 88.00, 14.0, 3, 3],
            [$t1, '401', 'apartamento', '4', 88.00, 14.0, 3, 3],
            [$t2, '102', 'apartamento', '1', 65.00, 10.0, 2, 2],
            [$t2, '202', 'apartamento', '2', 65.00, 10.0, 2, 2],
            [$t2, '302', 'apartamento', '3', 95.00, 15.0, 4, 3],
        ];

        foreach ($definicion as [$torre, $code, $kind, $piso, $area, $coef, $hab, $ban]) {
            $aptos[$code] = $this->id($units, [
                'company_id' => $this->companyId, 'block_id' => $torre, 'code' => $code,
                'kind' => $kind, 'floor' => $piso, 'area' => $area, 'coefficient' => $coef,
                'bedrooms' => $hab, 'bathrooms' => $ban, 'occupancy' => 'desocupado',
                'matricula' => '050N-' . random_int(1000000, 9999999),
            ]);
        }

        /*
         * Un local, y parqueaderos y depósitos adscritos: son inmuebles propios.
         *
         * Los coeficientes están elegidos para que el total dé **exactamente 100**:
         * 86 de los apartamentos + 7 del local + 6 de los tres parqueaderos + 1 del
         * depósito. Si se toca uno hay que tocar otro — el panel avisa en cuanto la
         * suma se sale, que es justo lo que se quiere demostrar.
         */
        $local = $this->id($units, [
            'company_id' => $this->companyId, 'block_id' => $t1, 'code' => 'L-1', 'kind' => 'local',
            'floor' => '1', 'area' => 45.00, 'coefficient' => 7.0, 'occupancy' => 'en_venta',
        ]);

        $parqs = [];

        foreach ([['P-01', '101', 2.0], ['P-02', '301', 2.0], ['P-03', '302', 2.0]] as [$code, $de, $coef]) {
            $parqs[$code] = $this->id($units, [
                'company_id' => $this->companyId, 'code' => $code, 'kind' => 'parqueadero',
                'parent_unit_id' => $aptos[$de], 'coefficient' => $coef, 'occupancy' => 'desocupado',
            ]);
        }

        $this->id($units, [
            'company_id' => $this->companyId, 'code' => 'D-01', 'kind' => 'deposito',
            'parent_unit_id' => $aptos['301'], 'coefficient' => 1.0, 'occupancy' => 'desocupado',
        ]);

        // ── Personas ─────────────────────────────────────────────────────────
        $p = [];

        $censo = [
            ['CC', '71234567', 'Carlos', 'Restrepo Gómez', '3105551001', 'carlos.restrepo@example.com', 'Ingeniero'],
            ['CC', '43567890', 'Marta', 'Gómez Ríos', '3105551002', 'marta.gomez@example.com', 'Odontóloga'],
            ['TI', '1012345678', 'Sofía', 'Restrepo Gómez', '3105551003', null, 'Estudiante'],
            ['CC', '98123456', 'Andrés', 'Villa Mesa', '3105551004', 'andres.villa@example.com', 'Comerciante'],
            ['CC', '52345678', 'Luisa', 'Cardona Pérez', '3105551005', 'luisa.cardona@example.com', 'Abogada'],
            ['CE', '556677', 'Diego', 'Ferreira Souza', '3105551006', 'diego.f@example.com', 'Chef'],
            ['CC', '15987654', 'Jorge', 'Muñoz Arango', '3105551007', null, 'Pensionado'],
            ['CC', '39876543', 'Claudia', 'Osorio Vélez', '3105551008', 'claudia.osorio@example.com', 'Arquitecta'],
            ['CC', '80123987', 'Felipe', 'Zapata Ruiz', '3105551009', null, 'Contador'],
        ];

        foreach ($censo as $i => [$tipo, $doc, $nombre, $apellido, $tel, $mail, $oficio]) {
            $p[$i] = $this->id($people, [
                'company_id' => $this->companyId, 'doc_type' => $tipo, 'doc_number' => $doc,
                'first_name' => $nombre, 'last_name' => $apellido, 'phone' => $tel,
                'whatsapp' => $tel, 'email' => $mail, 'occupation' => $oficio,
            ]);
        }

        // ── Grupos familiares ────────────────────────────────────────────────
        $f1 = $this->id($families, ['company_id' => $this->companyId, 'unit_id' => $aptos['101'], 'name' => 'Familia Restrepo Gómez', 'head_person_id' => $p[0]]);
        $f2 = $this->id($families, ['company_id' => $this->companyId, 'unit_id' => $aptos['302'], 'name' => 'Familia Osorio Vélez', 'head_person_id' => $p[7]]);

        $people->update($p[0], ['family_id' => $f1, 'family_role' => 'jefe']);
        $people->update($p[1], ['family_id' => $f1, 'family_role' => 'conyuge']);
        $people->update($p[2], ['family_id' => $f1, 'family_role' => 'hijo']);
        $people->update($p[7], ['family_id' => $f2, 'family_role' => 'jefe']);

        // ── Relaciones: los cuatro papeles y algo de historia ────────────────
        // 101: propietario que vive allí, con su familia → «propietario»
        $svc->openRelation(['unit_id' => $aptos['101'], 'person_id' => $p[0], 'role' => 'propietario', 'share' => '50', 'is_main' => 1, 'start_date' => '2018-03-15'], $this->companyId);
        $svc->openRelation(['unit_id' => $aptos['101'], 'person_id' => $p[1], 'role' => 'propietario', 'share' => '50', 'start_date' => '2018-03-15'], $this->companyId);
        $svc->openRelation(['unit_id' => $aptos['101'], 'person_id' => $p[0], 'role' => 'residente', 'start_date' => '2018-04-01'], $this->companyId);
        $svc->openRelation(['unit_id' => $aptos['101'], 'person_id' => $p[1], 'role' => 'residente', 'start_date' => '2018-04-01'], $this->companyId);
        $svc->openRelation(['unit_id' => $aptos['101'], 'person_id' => $p[2], 'role' => 'ocupante', 'start_date' => '2018-04-01'], $this->companyId);

        // 201: del dueño, arrendado → «arrendado»
        $svc->openRelation(['unit_id' => $aptos['201'], 'person_id' => $p[3], 'role' => 'propietario', 'share' => '100', 'is_main' => 1, 'start_date' => '2015-01-10'], $this->companyId);
        $svc->openRelation(['unit_id' => $aptos['201'], 'person_id' => $p[4], 'role' => 'arrendatario', 'is_main' => 1, 'start_date' => '2023-02-01'], $this->companyId);

        // …y un arrendatario ANTERIOR, ya cerrado: eso es el historial.
        $viejo = $svc->openRelation(['unit_id' => $aptos['201'], 'person_id' => $p[5], 'role' => 'arrendatario', 'start_date' => '2020-06-01'], $this->companyId);
        if ($viejo['ok']) {
            $svc->closeRelation($viejo['id'], $this->companyId, '2023-01-15');
        }

        // 301: propietario que no reside → sigue «desocupado»
        $svc->openRelation(['unit_id' => $aptos['301'], 'person_id' => $p[6], 'role' => 'propietario', 'share' => '100', 'is_main' => 1, 'start_date' => '2012-08-20'], $this->companyId);

        // 302: propietaria residente
        $svc->openRelation(['unit_id' => $aptos['302'], 'person_id' => $p[7], 'role' => 'propietario', 'share' => '100', 'is_main' => 1, 'start_date' => '2021-11-05'], $this->companyId);
        $svc->openRelation(['unit_id' => $aptos['302'], 'person_id' => $p[7], 'role' => 'residente', 'start_date' => '2021-11-05'], $this->companyId);

        // 102: solo un ocupante → «ocupado»
        $svc->openRelation(['unit_id' => $aptos['102'], 'person_id' => $p[8], 'role' => 'ocupante', 'start_date' => '2024-09-01'], $this->companyId);

        // 401 se queda sin nadie («desocupado») y 202 en remodelación: así están los
        // seis estados representados.
        $units->update($aptos['202'], ['occupancy' => 'remodelacion']);

        // El local ya nació «en venta».
        $svc->openRelation(['unit_id' => $local, 'person_id' => $p[3], 'role' => 'propietario', 'share' => '100', 'start_date' => '2019-05-01'], $this->companyId);

        // ── Vehículos ────────────────────────────────────────────────────────
        $vehicles = new VehicleModel();

        foreach ([
            [$aptos['101'], $p[0], $parqs['P-01'], 'carro', 'MJK412', 'Mazda', 'CX-30', 'Gris', 2021, 'TAG-001'],
            [$aptos['101'], $p[1], null, 'moto', 'ABC12D', 'Yamaha', 'NMAX', 'Azul', 2022, null],
            [$aptos['301'], $p[6], $parqs['P-02'], 'camioneta', 'HTR889', 'Toyota', 'Prado', 'Negro', 2019, 'TAG-014'],
            [$aptos['302'], $p[7], $parqs['P-03'], 'carro', 'PQW335', 'Renault', 'Logan', 'Blanco', 2018, 'TAG-022'],
            [$aptos['201'], $p[4], null, 'bicicleta', null, 'Trek', 'Marlin 5', 'Rojo', 2023, 'BIC-07'],
        ] as [$u, $per, $park, $kind, $plate, $marca, $linea, $color, $anio, $tag]) {
            $vehicles->insert([
                'company_id' => $this->companyId, 'unit_id' => $u, 'person_id' => $per,
                'parking_unit_id' => $park, 'kind' => $kind,
                'plate' => VehicleModel::normalizePlate($plate), 'brand' => $marca,
                'model' => $linea, 'color' => $color, 'year' => $anio, 'sticker' => $tag,
            ]);
        }

        // ── Mascotas (una vencida y una de manejo especial) ───────────────────
        $pets = new PetModel();

        foreach ([
            [$aptos['101'], $p[0], 'Nube', 'perro', 'Golden Retriever', 'Dorado', 'grande', '+180 days', 0],
            [$aptos['302'], $p[7], 'Michi', 'gato', 'Criollo', 'Atigrado', 'pequeno', '-20 days', 0],
            [$aptos['201'], $p[4], 'Rocco', 'perro', 'Pitbull', 'Café', 'grande', '+45 days', 1],
        ] as [$u, $per, $nombre, $especie, $raza, $color, $tam, $vence, $peligroso]) {
            $pets->insert([
                'company_id' => $this->companyId, 'unit_id' => $u, 'person_id' => $per,
                'name' => $nombre, 'species' => $especie, 'breed' => $raza, 'color' => $color,
                'size' => $tam, 'is_dangerous' => $peligroso,
                'vaccine_date' => date('Y-m-d', strtotime('-6 months')),
                'vaccine_expires' => date('Y-m-d', strtotime($vence)),
            ]);
        }

        // ── Contactos de emergencia ──────────────────────────────────────────
        $contacts = new ContactModel();

        foreach ([
            [$aptos['101'], $p[0], 'Elena Gómez Ríos', 'Hermana', '3115552001', 1],
            [$aptos['201'], $p[4], 'Mauricio Cardona', 'Padre', '3115552002', 1],
            [$aptos['302'], $p[7], 'Clínica del Norte', 'Servicio médico', '6045553000', 0],
        ] as [$u, $per, $nombre, $rel, $tel, $primero]) {
            $contacts->insert([
                'company_id' => $this->companyId, 'unit_id' => $u, 'person_id' => $per,
                'name' => $nombre, 'relationship' => $rel, 'phone' => $tel, 'is_primary' => $primero,
            ]);
        }

        // ── Autorizaciones: una vigente, una que ya caducó, una revocada ──────
        $auths = new AuthorizationModel();

        foreach ([
            [$aptos['101'], 'recogida_menor', null, 'Beatriz Álvarez', '43111222', '3115553001', $p[0], '+90 days', 'vigente', 'Puede recoger a Sofía los martes y jueves.'],
            [$aptos['302'], 'visitante_frecuente', null, 'Andrea Osorio', '1020304050', '3115553002', $p[7], '+180 days', 'vigente', 'Hermana de la propietaria.'],
            [$aptos['201'], 'mudanza', null, 'Trasteos La 80', '900123456', '6045554000', $p[4], '-15 days', 'vigente', 'Mudanza de entrada, sábado de 8 a 12.'],
            [$aptos['301'], 'remodelacion', null, 'Obras JM', '900987654', '6045554100', $p[6], '+30 days', 'revocada', 'Suspendida por ruido fuera de horario.'],
        ] as [$u, $tipo, $per, $nombre, $doc, $tel, $quien, $hasta, $estado, $detalle]) {
            $auths->insert([
                'company_id' => $this->companyId, 'unit_id' => $u, 'kind' => $tipo,
                'person_id' => $per, 'authorized_name' => $nombre, 'authorized_doc' => $doc,
                'authorized_phone' => $tel, 'granted_by_id' => $quien,
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d', strtotime($hasta)),
                'status' => $estado, 'details' => $detalle,
            ]);
        }

        // La que ya pasó de fecha se marca sola al entrar al listado: se deja
        // «vigente» a propósito para que se vea funcionar ese cierre por calendario.

        return ['ok' => true, 'message' => lang('Cop.demoDone')];
    }

    /** Inserta y devuelve el id, o revienta claro si el modelo rechaza la fila. */
    private function id($model, array $data): int
    {
        if (! $model->insert($data)) {
            throw new \RuntimeException('Datos demo: ' . implode(' ', $model->errors()));
        }

        return (int) $model->getInsertID();
    }
}
