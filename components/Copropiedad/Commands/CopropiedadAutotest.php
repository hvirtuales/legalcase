<?php

namespace Components\Copropiedad\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Copropiedad\Libraries\DocumentService;
use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Libraries\PlanGuard;
use Components\Copropiedad\Libraries\RegistryService;
use Components\Copropiedad\Models\AuthorizationModel;
use Components\Copropiedad\Models\BlockModel;
use Components\Copropiedad\Models\ContactModel;
use Components\Copropiedad\Models\DocumentModel;
use Components\Copropiedad\Models\FamilyModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\PetModel;
use Components\Copropiedad\Models\RelationModel;
use Components\Copropiedad\Models\UnitModel;
use Components\Copropiedad\Models\VehicleModel;

/**
 * `php spark copropiedad:autotest`
 *
 * Comprueba las **reglas** del componente, que es lo que no se ve mirando una
 * pantalla: cómo se deriva el estado de ocupación, que cerrar una relación no borre
 * el historial, que una baja limpie lo que cuelga (el soft-delete **no** dispara
 * `ON DELETE CASCADE`) y que tener el permiso no autorice a tocar los datos de otra
 * empresa.
 *
 * **Crea sus propias filas y las borra al terminar**, siempre —también si algo falla,
 * vía `finally`—. Trabaja sobre una empresa real porque las tablas tienen FK a
 * `companies`, pero **nunca toca nada que no haya creado él**: todo lo suyo lleva el
 * prefijo `ZZTEST`, y la limpieza borra exactamente esos ids.
 */
class CopropiedadAutotest extends BaseCommand
{
    protected $group       = 'Copropiedad';
    protected $name        = 'copropiedad:autotest';
    protected $description = 'Comprueba las reglas del registro de la copropiedad (no deja datos).';

    private int $ok = 0;
    private int $ko = 0;

    /** Todo lo creado, para poder borrarlo pase lo que pase. */
    private array $basura = [];

    private int $companyId = 0;
    private int $otherCompanyId = 0;

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $empresas = $db->table('companies')->select('id')->where('deleted_at', null)
            ->orderBy('id', 'asc')->get()->getResultArray();

        if (! $empresas) {
            CLI::error('No hay ninguna empresa: el componente necesita al menos una para poder probar el aislamiento.');

            return;
        }

        $this->companyId      = (int) $empresas[0]['id'];
        // La segunda empresa, si existe, sirve para probar el aislamiento de verdad.
        $this->otherCompanyId = isset($empresas[1]) ? (int) $empresas[1]['id'] : 0;

        CLI::write('Empresa de pruebas: ' . $this->companyId
            . ($this->otherCompanyId ? ' · segunda empresa: ' . $this->otherCompanyId : ' · (sin segunda empresa)'), 'dark_gray');
        CLI::newLine();

        try {
            $this->seccion1Esquema();
            $this->seccion2Presentacion();
            $this->seccion3Aislamiento();
            $this->seccion4Relaciones();
            $this->seccion5Ocupacion();
            $this->seccion6Bajas();
            $this->seccion7Placas();
            $this->seccion8Autorizaciones();
            $this->seccion9Documentos();
            $this->seccion10Plan();
        } finally {
            $this->limpia();
        }

        CLI::newLine();
        CLI::write(str_repeat('─', 44), 'dark_gray');

        if ($this->ko === 0) {
            CLI::write("TODO BIEN: {$this->ok} comprobaciones, 0 fallidas", 'green');
        } else {
            CLI::write("HAY FALLOS: {$this->ok} correctas, {$this->ko} fallidas", 'red');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function check(string $que, bool $bien, string $detalle = ''): void
    {
        if ($bien) {
            $this->ok++;
            CLI::write('  OK    ' . $que, 'green');

            return;
        }
        $this->ko++;
        CLI::write('  FALLA ' . $que . ($detalle !== '' ? '  ->  ' . $detalle : ''), 'red');
    }

    private function titulo(string $t): void
    {
        CLI::newLine();
        CLI::write('=== ' . $t . ' ===', 'yellow');
    }

    /** Crea una fila de prueba y la apunta para borrarla luego. */
    private function crea(string $tabla, array $datos): int
    {
        $db = \Config\Database::connect();
        $datos += ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        $db->table($tabla)->insert($datos);
        $id = (int) $db->insertID();

        $this->basura[$tabla][] = $id;

        return $id;
    }

    /**
     * Borrado **físico** de todo lo creado, en orden inverso a las FK.
     *
     * No se usa el soft-delete de los modelos: esto es basura de prueba y tiene que
     * desaparecer de verdad, no quedarse marcada como borrada en la base del cliente.
     */
    private function limpia(): void
    {
        $db    = \Config\Database::connect();
        $orden = [
            'cmp_cop_documents', 'cmp_cop_authorizations', 'cmp_cop_contacts',
            'cmp_cop_vehicles', 'cmp_cop_pets', 'cmp_cop_unit_people',
            'cmp_cop_people', 'cmp_cop_units', 'cmp_cop_families', 'cmp_cop_blocks',
        ];

        foreach ($orden as $tabla) {
            $ids = $this->basura[$tabla] ?? [];
            if ($ids) {
                $db->table($tabla)->whereIn('id', array_unique($ids))->delete();
            }
        }

        CLI::newLine();
        CLI::write('Datos de prueba eliminados.', 'dark_gray');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seccion1Esquema(): void
    {
        $this->titulo('1. Esquema');
        $db = \Config\Database::connect();

        $tablas = [
            'cmp_cop_blocks', 'cmp_cop_families', 'cmp_cop_units', 'cmp_cop_people',
            'cmp_cop_unit_people', 'cmp_cop_pets', 'cmp_cop_vehicles',
            'cmp_cop_contacts', 'cmp_cop_authorizations', 'cmp_cop_documents',
        ];

        foreach ($tablas as $t) {
            $this->check('existe ' . $t, $db->tableExists($t));
        }

        $this->check('plans tiene copropiedad_enabled', $db->fieldExists('copropiedad_enabled', 'plans'));
        $this->check('plans tiene max_units', $db->fieldExists('max_units', 'plans'));

        // Todas las tablas llevan company_id: es lo que sostiene el aislamiento.
        foreach ($tablas as $t) {
            $this->check($t . ' lleva company_id', $db->fieldExists('company_id', $t));
        }
    }

    private function seccion2Presentacion(): void
    {
        $this->titulo('2. Presentación (todo valor guardado se puede pintar)');

        $faltan = Meta::missing();
        $this->check('ningún valor de lista se queda sin etiqueta', $faltan === [], json_encode($faltan));

        // Y al revés: que el mapa no ofrezca opciones que el modelo rechazaría.
        $sobran = array_diff(array_keys(Meta::UNIT_KINDS), UnitModel::KINDS);
        $this->check('el mapa de tipos no ofrece nada que el modelo rechace', $sobran === [], json_encode($sobran));

        $this->check('label() de un valor desconocido devuelve el valor, no vacío',
            Meta::label(Meta::UNIT_KINDS, 'inventado') === 'inventado');
        $this->check('label() de null devuelve el guion', Meta::label(Meta::UNIT_KINDS, null) === lang('Cop.none'));
    }

    private function seccion3Aislamiento(): void
    {
        $this->titulo('3. Aislamiento por empresa (el permiso no basta)');

        $units = new UnitModel();
        $id    = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-A1', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);

        $this->check('owned() encuentra la fila de su empresa', $units->owned($id, $this->companyId) !== null);
        $this->check('owned() con id 0 devuelve null', $units->owned(0, $this->companyId) === null);
        $this->check('owned() con empresa 0 devuelve null', $units->owned($id, 0) === null);

        if ($this->otherCompanyId) {
            $this->check('owned() NO devuelve la fila desde otra empresa',
                $units->owned($id, $this->otherCompanyId) === null);
        } else {
            // Sin segunda empresa real, se comprueba con un id de empresa que no es
            // la dueña: la condición que se prueba es la misma.
            $this->check('owned() NO devuelve la fila con otro company_id',
                $units->owned($id, $this->companyId + 99999) === null);
        }

        // Y la comprobación que de verdad importa: el servicio rechaza el id ajeno.
        $svc = new RegistryService();
        $r   = $svc->openRelation(['unit_id' => $id, 'person_id' => 1, 'role' => 'residente'], $this->companyId + 99999);
        $this->check('el servicio rechaza abrir una relación desde otra empresa', $r['ok'] === false);
    }

    private function seccion4Relaciones(): void
    {
        $this->titulo('4. La relación ES el historial');

        $svc  = new RegistryService();
        $rels = new RelationModel();

        $unit   = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-501', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);
        $juan   = $this->creaPersona('ZZTEST1', 'Juan', 'Probador');
        $maria  = $this->creaPersona('ZZTEST2', 'María', 'Probadora');

        $r = $svc->openRelation([
            'unit_id' => $unit, 'person_id' => $juan, 'role' => 'propietario',
            'share' => '100', 'is_main' => 1, 'start_date' => '2020-01-01',
        ], $this->companyId);
        $this->check('abre una relación', $r['ok'], $r['message']);
        $this->basura['cmp_cop_unit_people'][] = $r['id'];
        $relJuan = $r['id'];

        // El duplicado es el fallo que descuadraba los porcentajes sin explicación.
        $dup = $svc->openRelation(['unit_id' => $unit, 'person_id' => $juan, 'role' => 'propietario'], $this->companyId);
        $this->check('rechaza la MISMA relación dos veces', $dup['ok'] === false, $dup['message']);

        // El mismo papel para OTRA persona sí se puede: copropiedad al 50 %.
        $r2 = $svc->openRelation(['unit_id' => $unit, 'person_id' => $maria, 'role' => 'propietario', 'share' => '50'], $this->companyId);
        $this->check('admite un segundo propietario', $r2['ok'], $r2['message']);
        $this->basura['cmp_cop_unit_people'][] = $r2['id'];

        // Un solo titular por papel.
        $marcados = $rels->where('unit_id', $unit)->where('role', 'propietario')
            ->where('is_current', 1)->where('is_main', 1)->countAllResults();
        $this->check('solo hay un titular por papel', $marcados <= 1, 'titulares: ' . $marcados);

        $this->check('la suma de porcentajes se calcula', abs($rels->shareSum($unit, $this->companyId) - 150.0) < 0.001);

        // Cerrar NO borra: es la promesa del componente.
        $c = $svc->closeRelation($relJuan, $this->companyId, '2024-06-30');
        $this->check('cierra la relación', $c['ok'], $c['message']);

        $fila = $rels->find($relJuan);
        $this->check('la fila cerrada SIGUE existiendo', $fila !== null);
        $this->check('  con is_current = 0', $fila && (int) $fila['is_current'] === 0);
        $this->check('  y con fecha de fin', $fila && $fila['end_date'] === '2024-06-30');

        $vigentes = $rels->currentFor($unit, $this->companyId);
        $this->check('ya no aparece entre las vigentes',
            ! in_array($relJuan, array_map('intval', array_column($vigentes, 'id')), true));

        $historial = $rels->historyFor($unit, $this->companyId);
        $this->check('SÍ aparece en el historial',
            in_array($relJuan, array_map('intval', array_column($historial, 'id')), true));

        $this->check('cerrar dos veces se rechaza', $svc->closeRelation($relJuan, $this->companyId)['ok'] === false);

        $re = $svc->reopenRelation($relJuan, $this->companyId);
        $this->check('reabre una relación cerrada', $re['ok'], $re['message']);
        $this->check('  y le quita la fecha de fin', $rels->find($relJuan)['end_date'] === null);

        // El historial de la persona sale de las MISMAS filas.
        $suyas = $rels->forPerson($juan, $this->companyId);
        $this->check('el historial de la persona usa las mismas filas', count($suyas) === 1);
    }

    private function seccion5Ocupacion(): void
    {
        $this->titulo('5. El estado de ocupación se deriva');

        $svc   = new RegistryService();
        $units = new UnitModel();

        $unit = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-702', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);
        $dueno    = $this->creaPersona('ZZTEST3', 'Ana', 'Dueña');
        $inquilino = $this->creaPersona('ZZTEST4', 'Luis', 'Inquilino');

        $this->check('sin nadie: desocupado', $svc->deriveOccupancy($unit, $this->companyId) === 'desocupado');

        // Propietario que NO vive allí: sigue desocupado.
        $rProp = $svc->openRelation(['unit_id' => $unit, 'person_id' => $dueno, 'role' => 'propietario'], $this->companyId);
        $this->basura['cmp_cop_unit_people'][] = $rProp['id'];
        $this->check('propietario que no reside: sigue desocupado',
            $svc->deriveOccupancy($unit, $this->companyId) === 'desocupado',
            $svc->deriveOccupancy($unit, $this->companyId));

        // El propietario se muda: propietario.
        $rRes = $svc->openRelation(['unit_id' => $unit, 'person_id' => $dueno, 'role' => 'residente'], $this->companyId);
        $this->basura['cmp_cop_unit_people'][] = $rRes['id'];
        $this->check('el propietario vive allí: «propietario»',
            $svc->deriveOccupancy($unit, $this->companyId) === 'propietario');
        $this->check('  y se guardó solo en el inmueble',
            $units->find($unit)['occupancy'] === 'propietario');

        // Entra un arrendatario: manda «arrendado».
        $rArr = $svc->openRelation(['unit_id' => $unit, 'person_id' => $inquilino, 'role' => 'arrendatario'], $this->companyId);
        $this->basura['cmp_cop_unit_people'][] = $rArr['id'];
        $this->check('con arrendatario: «arrendado»',
            $svc->deriveOccupancy($unit, $this->companyId) === 'arrendado');

        // Un ocupante suelto: «ocupado».
        $unit2 = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-703', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);
        $rOcu = $svc->openRelation(['unit_id' => $unit2, 'person_id' => $inquilino, 'role' => 'ocupante'], $this->companyId);
        $this->basura['cmp_cop_unit_people'][] = $rOcu['id'];
        $this->check('solo un ocupante: «ocupado»', $svc->deriveOccupancy($unit2, $this->companyId) === 'ocupado');

        // LO IMPORTANTE: «en venta» y «en remodelación» no se pisan.
        $units->update($unit2, ['occupancy' => 'en_venta']);
        $svc->syncOccupancy($unit2, $this->companyId);
        $this->check('«en venta» NO lo pisa el recálculo',
            $units->find($unit2)['occupancy'] === 'en_venta', $units->find($unit2)['occupancy']);

        $units->update($unit2, ['occupancy' => 'remodelacion']);
        $svc->syncOccupancy($unit2, $this->companyId);
        $this->check('«en remodelación» tampoco', $units->find($unit2)['occupancy'] === 'remodelacion');

        // Y fijar a mano un estado derivable se recalcula en vez de creerse.
        $units->update($unit2, ['occupancy' => 'desocupado']);
        $r = $svc->setOccupancy($unit2, $this->companyId, 'desocupado');
        $this->check('fijar «desocupado» con un ocupante dentro se recalcula',
            $r['ok'] && $units->find($unit2)['occupancy'] === 'ocupado', $units->find($unit2)['occupancy']);

        // Al cerrar la última relación, vuelve a desocupado.
        $svc->closeRelation($rOcu['id'], $this->companyId);
        $this->check('al cerrar la última relación vuelve a desocupado',
            $units->find($unit2)['occupancy'] === 'desocupado', $units->find($unit2)['occupancy']);
    }

    private function seccion6Bajas(): void
    {
        $this->titulo('6. Las bajas limpian lo que el soft-delete no limpia');

        $svc = new RegistryService();

        $torre = $this->crea('cmp_cop_blocks', ['company_id' => $this->companyId, 'name' => 'ZZTEST Torre', 'kind' => 'torre']);
        $apto  = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'block_id' => $torre, 'code' => 'ZZTEST-101',
            'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);
        $parq = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'parent_unit_id' => $apto, 'code' => 'ZZTEST-P1',
            'kind' => 'parqueadero', 'occupancy' => 'desocupado',
        ]);
        $persona = $this->creaPersona('ZZTEST5', 'Pedro', 'Baja');

        $mascota = $this->crea('cmp_cop_pets', [
            'company_id' => $this->companyId, 'unit_id' => $apto, 'person_id' => $persona,
            'name' => 'ZZTEST Firulais', 'species' => 'perro',
        ]);
        $vehiculo = $this->crea('cmp_cop_vehicles', [
            'company_id' => $this->companyId, 'unit_id' => $apto, 'person_id' => $persona,
            'parking_unit_id' => $parq, 'kind' => 'carro', 'plate' => 'ZZT001',
        ]);
        $contacto = $this->crea('cmp_cop_contacts', [
            'company_id' => $this->companyId, 'unit_id' => $apto, 'person_id' => $persona,
            'name' => 'ZZTEST Contacto', 'phone' => '3000000000',
        ]);
        $rel = $svc->openRelation(['unit_id' => $apto, 'person_id' => $persona, 'role' => 'residente'], $this->companyId);
        $this->basura['cmp_cop_unit_people'][] = $rel['id'];

        // ── Baja de la PERSONA ───────────────────────────────────────────────
        $r = $svc->deletePerson($persona, $this->companyId);
        $this->check('da de baja a la persona', $r['ok'], $r['message']);

        $filaRel = (new RelationModel())->find($rel['id']);
        $this->check('su relación NO se borra…', $filaRel !== null);
        $this->check('  …se CIERRA', $filaRel && (int) $filaRel['is_current'] === 0);

        $this->check('la mascota suelta al responsable',
            (new PetModel())->find($mascota)['person_id'] === null);
        $this->check('el vehículo suelta al responsable',
            (new VehicleModel())->find($vehiculo)['person_id'] === null);
        $this->check('el contacto suelta a la persona',
            (new ContactModel())->find($contacto)['person_id'] === null);
        $this->check('el inmueble vuelve a desocupado',
            (new UnitModel())->find($apto)['occupancy'] === 'desocupado');

        // ── Baja del INMUEBLE ────────────────────────────────────────────────
        $r = $svc->deleteUnit($apto, $this->companyId);
        $this->check('da de baja el inmueble', $r['ok'], $r['message']);

        $db = \Config\Database::connect();
        foreach ([['cmp_cop_pets', $mascota], ['cmp_cop_vehicles', $vehiculo], ['cmp_cop_contacts', $contacto]] as [$tabla, $id]) {
            $fila = $db->table($tabla)->where('id', $id)->get()->getRowArray();
            $this->check($tabla . ' queda marcada como borrada', $fila && $fila['deleted_at'] !== null);
        }

        // El parqueadero es un inmueble propio: se desadscribe, NO se borra.
        $filaParq = $db->table('cmp_cop_units')->where('id', $parq)->get()->getRowArray();
        $this->check('el parqueadero NO se borra', $filaParq && $filaParq['deleted_at'] === null);
        $this->check('  solo se desadscribe', $filaParq && $filaParq['parent_unit_id'] === null);

        // Y al revés: al dar de baja el PARQUEADERO, el vehículo que lo tenía
        // asignado pierde el puesto pero sobrevive — está en otro inmueble.
        $otroApto = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-103', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);
        $parq2 = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-P2', 'kind' => 'parqueadero', 'occupancy' => 'desocupado',
        ]);
        $vehiculo2 = $this->crea('cmp_cop_vehicles', [
            'company_id' => $this->companyId, 'unit_id' => $otroApto, 'parking_unit_id' => $parq2,
            'kind' => 'carro', 'plate' => 'ZZT002',
        ]);

        $svc->deleteUnit($parq2, $this->companyId);
        $filaVeh = $db->table('cmp_cop_vehicles')->where('id', $vehiculo2)->get()->getRowArray();
        $this->check('al borrar el parqueadero, el vehículo sobrevive', $filaVeh && $filaVeh['deleted_at'] === null);
        $this->check('  pero pierde el puesto', $filaVeh && $filaVeh['parking_unit_id'] === null);

        // ── Baja de la TORRE: no borra sus inmuebles ─────────────────────────
        $otro = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'block_id' => $torre, 'code' => 'ZZTEST-102',
            'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);
        (new UnitModel())->builder()->where('block_id', $torre)->update(['block_id' => null]);
        (new BlockModel())->delete($torre);
        $filaOtro = $db->table('cmp_cop_units')->where('id', $otro)->get()->getRowArray();
        $this->check('borrar la torre NO borra sus inmuebles', $filaOtro && $filaOtro['deleted_at'] === null);
        $this->check('  los deja sin torre', $filaOtro && $filaOtro['block_id'] === null);

        // ── Baja de la FAMILIA: no borra a sus miembros ──────────────────────
        $familia = $this->crea('cmp_cop_families', ['company_id' => $this->companyId, 'name' => 'ZZTEST Familia']);
        $miembro = $this->creaPersona('ZZTEST6', 'Sara', 'Miembro', ['family_id' => $familia, 'family_role' => 'jefe']);

        $r = $svc->deleteFamily($familia, $this->companyId);
        $this->check('da de baja el grupo familiar', $r['ok'], $r['message']);
        $filaMiembro = $db->table('cmp_cop_people')->where('id', $miembro)->get()->getRowArray();
        $this->check('sus miembros NO se borran', $filaMiembro && $filaMiembro['deleted_at'] === null);
        $this->check('  quedan sin agrupar', $filaMiembro && $filaMiembro['family_id'] === null);
    }

    private function seccion7Placas(): void
    {
        $this->titulo('7. Placas');

        $this->check('«abc-123» se normaliza a ABC123', VehicleModel::normalizePlate('abc-123') === 'ABC123');
        $this->check('«ABC 123» también', VehicleModel::normalizePlate('ABC 123') === 'ABC123');
        $this->check('una placa vacía es null', VehicleModel::normalizePlate('   ') === null);
        $this->check('los símbolos se caen', VehicleModel::normalizePlate('A*B/C1') === 'ABC1');

        $unit = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-VEH', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);
        $this->crea('cmp_cop_vehicles', [
            'company_id' => $this->companyId, 'unit_id' => $unit, 'kind' => 'carro', 'plate' => 'ZZT999',
        ]);

        $veh = new VehicleModel();
        $this->check('detecta la placa repetida', $veh->plateTaken($this->companyId, 'zzt-999'));
        $this->check('una placa nueva no está repetida', ! $veh->plateTaken($this->companyId, 'ZZT998'));
        $this->check('sin placa nunca hay repetición', ! $veh->plateTaken($this->companyId, null));
    }

    private function seccion8Autorizaciones(): void
    {
        $this->titulo('8. Autorizaciones: vencer ≠ revocar');

        $auths = new AuthorizationModel();
        $unit  = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-AUT', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);

        $ayer = date('Y-m-d', strtotime('-1 day'));

        $vencida = $this->crea('cmp_cop_authorizations', [
            'company_id' => $this->companyId, 'unit_id' => $unit, 'kind' => 'ingreso',
            'authorized_name' => 'ZZTEST Vencida', 'status' => 'vigente', 'end_date' => $ayer,
        ]);
        $revocada = $this->crea('cmp_cop_authorizations', [
            'company_id' => $this->companyId, 'unit_id' => $unit, 'kind' => 'ingreso',
            'authorized_name' => 'ZZTEST Revocada', 'status' => 'revocada', 'end_date' => $ayer,
        ]);
        $viva = $this->crea('cmp_cop_authorizations', [
            'company_id' => $this->companyId, 'unit_id' => $unit, 'kind' => 'ingreso',
            'authorized_name' => 'ZZTEST Viva', 'status' => 'vigente',
            'end_date' => date('Y-m-d', strtotime('+30 days')),
        ]);

        $auths->expireOverdue($this->companyId);

        $this->check('la pasada de fecha queda vencida', $auths->find($vencida)['status'] === 'vencida');
        $this->check('la REVOCADA sigue revocada', $auths->find($revocada)['status'] === 'revocada');
        $this->check('la que aún vale sigue vigente', $auths->find($viva)['status'] === 'vigente');

        $this->check('hay que autorizar a alguien', ! AuthorizationModel::hasSubject(['person_id' => null, 'authorized_name' => '  ']));
        $this->check('vale con un nombre suelto', AuthorizationModel::hasSubject(['authorized_name' => 'Alguien']));
        $this->check('o con una persona del censo', AuthorizationModel::hasSubject(['person_id' => 5]));
    }

    private function seccion9Documentos(): void
    {
        $this->titulo('9. Documentos (privados y aislados)');

        $docs = new DocumentService();
        $unit = $this->crea('cmp_cop_units', [
            'company_id' => $this->companyId, 'code' => 'ZZTEST-DOC', 'kind' => 'apartamento', 'occupancy' => 'desocupado',
        ]);

        $this->check('resuelve la empresa del dueño', $docs->ownerCompanyId('unit', $unit) === $this->companyId);
        $this->check('un owner_type inventado no resuelve nada', $docs->ownerCompanyId('inventado', $unit) === null);
        $this->check('un id que no existe tampoco', $docs->ownerCompanyId('unit', 99999999) === null);

        // Sin archivo válido no se guarda nada, y el mensaje explica por qué.
        $r = $docs->store(null, ['owner_type' => 'unit', 'owner_id' => $unit], $this->companyId);
        $this->check('sin archivo no guarda', $r['ok'] === false, $r['message']);

        $r = $docs->store(null, ['owner_type' => 'unit', 'owner_id' => $unit], $this->companyId + 99999);
        $this->check('con un dueño de otra empresa, ni mira el archivo',
            $r['ok'] === false && $r['message'] === lang('Cop.errDocOwner'), $r['message']);

        // Un documento de otra empresa no se puede leer aunque se sepa su id.
        $doc = $this->crea('cmp_cop_documents', [
            'company_id' => $this->companyId, 'owner_type' => 'unit', 'owner_id' => $unit,
            'kind' => 'escritura', 'title' => 'ZZTEST doc', 'file_path' => 'noexiste.pdf',
            'original_name' => 'x.pdf', 'size' => 0,
        ]);
        $this->check('no se sirve un documento de otra empresa', $docs->readable($doc, $this->companyId + 99999) === null);
        // Y con la empresa correcta tampoco, porque el archivo no está en disco.
        $this->check('ni uno cuyo archivo no existe', $docs->readable($doc, $this->companyId) === null);

        $this->check('los tipos admitidos son lista blanca',
            in_array('application/pdf', DocumentService::ALLOWED_MIMES, true)
            && ! in_array('application/x-msdownload', DocumentService::ALLOWED_MIMES, true));
        $this->check('todo owner_type tiene su tabla',
            array_diff(DocumentModel::OWNER_TYPES, array_keys(DocumentModel::OWNER_TABLES)) === []);
    }

    private function seccion10Plan(): void
    {
        $this->titulo('10. Plan (contrato §11)');

        $guard = new PlanGuard();

        // Lo que de verdad se prueba: que resolver el plan NO exija un método que
        // este núcleo puede no tener (§15.2). Si `effectivePlan()` no existe, el
        // guardián cae al plan de la propia empresa en vez de reventar.
        $companies = new \App\Models\CompanyModel();
        CLI::write('    (el núcleo ' . (method_exists($companies, 'effectivePlan') ? 'SÍ' : 'NO')
            . ' tiene CompanyModel::effectivePlan)', 'dark_gray');

        $enabled = $guard->serviceEnabled($this->companyId);
        $this->check('resuelve el servicio sin lanzar excepción', is_bool($enabled));

        $left = $guard->unitsLeft($this->companyId);
        $this->check('resuelve el cupo (null = ilimitado)', $left === null || is_int($left));

        $motivo = $guard->blockReason($this->companyId);
        $this->check('blockReason devuelve null o un mensaje', $motivo === null || is_string($motivo));

        // Sin empresa: permitido e ilimitado, como manda el contrato.
        $sin = new PlanGuard();
        $this->check('sin empresa el servicio está permitido', $sin->serviceEnabled(0) === true);
        $this->check('sin empresa el cupo es ilimitado', (new PlanGuard())->unitsLeft(0) === null);

        // Coeficientes.
        $suma = (new UnitModel())->coefficientSum($this->companyId);
        $this->check('la suma de coeficientes se calcula', is_float($suma));
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function creaPersona(string $doc, string $nombre, string $apellido, array $extra = []): int
    {
        return $this->crea('cmp_cop_people', [
            'company_id' => $this->companyId, 'doc_type' => 'CC', 'doc_number' => $doc,
            'first_name' => $nombre, 'last_name' => $apellido,
        ] + $extra);
    }
}
