<?php

namespace Components\Copropiedad\Libraries;

use Components\Copropiedad\Models\AuthorizationModel;
use Components\Copropiedad\Models\BlockModel;
use Components\Copropiedad\Models\DocumentModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\PetModel;
use Components\Copropiedad\Models\RelationModel;
use Components\Copropiedad\Models\UnitModel;
use Components\Copropiedad\Models\VehicleModel;

/**
 * Cómo se **presenta** cada valor de lista: su etiqueta traducida y, cuando lleva
 * distintivo, su color.
 *
 * Está centralizado porque los mismos valores se pintan en cinco sitios —el listado,
 * la ficha, el panel, el formulario y el filtro— y con el mapa copiado en cada vista
 * bastaba con añadir un tipo de inmueble para que apareciera con su nombre en dos
 * pantallas y en blanco en las otras tres.
 *
 * Las **claves** de los mapas son las mismas constantes de los modelos, así que
 * `options()` sirve igual para pintar un `<select>` que para traducir un valor
 * guardado, y las dos cosas no pueden desincronizarse.
 */
class Meta
{
    /** valor guardado => clave de idioma */
    public const UNIT_KINDS = [
        'apartamento' => 'kApartamento',
        'casa'        => 'kCasa',
        'local'       => 'kLocal',
        'oficina'     => 'kOficina',
        'parqueadero' => 'kParqueadero',
        'deposito'    => 'kDeposito',
        'otro'        => 'kOtro',
    ];

    public const OCCUPANCIES = [
        'desocupado'   => 'oDesocupado',
        'propietario'  => 'oPropietario',
        'arrendado'    => 'oArrendado',
        'ocupado'      => 'oOcupado',
        'en_venta'     => 'oEnVenta',
        'remodelacion' => 'oRemodelacion',
    ];

    /** El semáforo del estado de ocupación. */
    public const OCCUPANCY_COLORS = [
        'desocupado'   => 'badge-secondary',
        'propietario'  => 'badge-success',
        'arrendado'    => 'badge-info',
        'ocupado'      => 'badge-success',
        'en_venta'     => 'badge-warning',
        'remodelacion' => 'badge-warning',
    ];

    public const BLOCK_KINDS = [
        'torre'    => 'bTorre',
        'bloque'   => 'bBloque',
        'etapa'    => 'bEtapa',
        'manzana'  => 'bManzana',
        'interior' => 'bInterior',
    ];

    public const ROLES = [
        'propietario'  => 'roPropietario',
        'residente'    => 'roResidente',
        'arrendatario' => 'roArrendatario',
        'ocupante'     => 'roOcupante',
    ];

    public const ROLE_COLORS = [
        'propietario'  => 'badge-primary',
        'residente'    => 'badge-success',
        'arrendatario' => 'badge-info',
        'ocupante'     => 'badge-secondary',
    ];

    public const FAMILY_ROLES = [
        'jefe'     => 'frJefe',
        'conyuge'  => 'frConyuge',
        'hijo'     => 'frHijo',
        'padre'    => 'frPadre',
        'hermano'  => 'frHermano',
        'otro'     => 'frOtro',
    ];

    public const VEHICLE_KINDS = [
        'carro'     => 'vCarro',
        'camioneta' => 'vCamioneta',
        'moto'      => 'vMoto',
        'bicicleta' => 'vBicicleta',
        'otro'      => 'vOtro',
    ];

    public const SPECIES = [
        'perro'  => 'sPerro',
        'gato'   => 'sGato',
        'ave'    => 'sAve',
        'pez'    => 'sPez',
        'roedor' => 'sRoedor',
        'reptil' => 'sReptil',
        'otro'   => 'sOtro',
    ];

    public const SIZES = [
        'pequeno' => 'zPequeno',
        'mediano' => 'zMediano',
        'grande'  => 'zGrande',
    ];

    public const AUTH_KINDS = [
        'ingreso'             => 'aIngreso',
        'recogida_menor'      => 'aRecogidaMenor',
        'visitante_frecuente' => 'aVisitanteFrec',
        'mudanza'             => 'aMudanza',
        'remodelacion'        => 'aRemodelacion',
        'mascota'             => 'aMascota',
        'parqueadero'         => 'aParqueadero',
        'domicilio'           => 'aDomicilio',
        'otro'                => 'aOtro',
    ];

    public const AUTH_STATUSES = [
        'vigente'  => 'stVigente',
        'vencida'  => 'stVencida',
        'revocada' => 'stRevocada',
    ];

    public const AUTH_STATUS_COLORS = [
        'vigente'  => 'badge-success',
        'vencida'  => 'badge-secondary',
        'revocada' => 'badge-danger',
    ];

    public const DOC_KINDS = [
        'escritura'            => 'dEscritura',
        'certificado_tradicion' => 'dCertTradicion',
        'contrato_arriendo'    => 'dContratoArr',
        'documento_identidad'  => 'dIdentidad',
        'rut'                  => 'dRut',
        'paz_y_salvo'          => 'dPazYSalvo',
        'poder'                => 'dPoder',
        'reglamento'           => 'dReglamento',
        'acta'                 => 'dActa',
        'vacunas'              => 'dVacunas',
        'soat'                 => 'dSoat',
        'tecnicomecanica'      => 'dTecnico',
        'poliza'               => 'dPoliza',
        'otro'                 => 'dOtro',
    ];

    /**
     * Etiqueta traducida de un valor.
     *
     * Si el valor no está en el mapa devuelve el valor crudo en vez de una cadena
     * vacía: un dato viejo o de una versión posterior se sigue leyendo, aunque sea
     * sin traducir. Una celda en blanco no dice nada; «arrendado» sí.
     */
    public static function label(array $map, ?string $value): string
    {
        if ($value === null || $value === '') {
            return lang('Cop.none');
        }

        return isset($map[$value]) ? lang('Cop.' . $map[$value]) : $value;
    }

    /** Clase de distintivo de un valor, con un color neutro por defecto. */
    public static function color(array $map, ?string $value, string $default = 'badge-secondary'): string
    {
        return $map[$value] ?? $default;
    }

    /**
     * `[valor => etiqueta]` listo para un `<select>`.
     *
     * Sale del **mismo** mapa que usa `label()`, así que una opción que se pueda
     * elegir siempre se puede volver a leer.
     */
    public static function options(array $map): array
    {
        $out = [];

        foreach ($map as $value => $key) {
            $out[$value] = lang('Cop.' . $key);
        }

        return $out;
    }

    /**
     * Comprobación de coherencia entre los mapas y las constantes de los modelos.
     *
     * La usa el autotest: si alguien añade un tipo de inmueble en `UnitModel::KINDS`
     * y se olvida del mapa, ese tipo se guardaría bien y se pintaría en crudo en toda
     * la aplicación. Aquí salta.
     *
     * @return array<string,string[]> mapa => valores sin presentación
     */
    public static function missing(): array
    {
        $pares = [
            'UNIT_KINDS'    => [UnitModel::KINDS, self::UNIT_KINDS],
            'OCCUPANCIES'   => [UnitModel::OCCUPANCIES, self::OCCUPANCIES],
            'BLOCK_KINDS'   => [BlockModel::KINDS, self::BLOCK_KINDS],
            'ROLES'         => [RelationModel::ROLES, self::ROLES],
            'FAMILY_ROLES'  => [PersonModel::FAMILY_ROLES, self::FAMILY_ROLES],
            'VEHICLE_KINDS' => [VehicleModel::KINDS, self::VEHICLE_KINDS],
            'SPECIES'       => [PetModel::SPECIES, self::SPECIES],
            'SIZES'         => [PetModel::SIZES, self::SIZES],
            'AUTH_KINDS'    => [AuthorizationModel::KINDS, self::AUTH_KINDS],
            'AUTH_STATUSES' => [AuthorizationModel::STATUSES, self::AUTH_STATUSES],
            'DOC_KINDS'     => [DocumentModel::KINDS, self::DOC_KINDS],
        ];

        $faltan = [];

        foreach ($pares as $nombre => [$constantes, $mapa]) {
            $diff = array_diff($constantes, array_keys($mapa));
            if ($diff) {
                $faltan[$nombre] = array_values($diff);
            }
        }

        return $faltan;
    }
}
