<?php

namespace Components\Copropiedad\Controllers;

use App\Controllers\BaseController;
use Components\Copropiedad\Libraries\PlanGuard;
use Components\Copropiedad\Models\AuthorizationModel;
use Components\Copropiedad\Models\DocumentModel;
use Components\Copropiedad\Models\FamilyModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\PetModel;
use Components\Copropiedad\Models\RelationModel;
use Components\Copropiedad\Models\UnitModel;
use Components\Copropiedad\Models\VehicleModel;

/**
 * Panel del registro: el estado del censo de un vistazo.
 *
 * Dos avisos que son la razón de que el panel exista, más allá de los totales:
 *
 *  · **La suma de coeficientes.** Debe dar 100 %. Si no da, la copropiedad tiene un
 *    problema real —el reparto de la cuota y el peso del voto en asamblea salen de
 *    ahí— y es un error que no se ve en ninguna pantalla de detalle, porque cada
 *    inmueble por separado parece correcto.
 *  · **Lo que vence.** Media carpeta de una copropiedad caduca (SOAT, pólizas,
 *    contratos, carnés de vacunas). Guardar la fecha de vencimiento solo sirve si
 *    alguien la ve a tiempo.
 */
class Dashboard extends BaseController
{
    public function index()
    {
        $companyId = (int) (session()->get('active_company_id') ?? 0);

        if (! $companyId) {
            return view('Components\Copropiedad\Views\dashboard', ['companyId' => 0]);
        }

        $units = new UnitModel();
        $auths = new AuthorizationModel();

        // Al entrar al panel se cierran por calendario las autorizaciones pasadas de
        // fecha, igual que en su listado: si no, las tarjetas dirían «12 vigentes»
        // contando cuatro que caducaron.
        $auths->expireOverdue($companyId);

        $coeff = $units->coefficientSum($companyId);

        return view('Components\Copropiedad\Views\dashboard', [
            'companyId'    => $companyId,
            'totalUnits'   => $units->countFor($companyId),
            'byKind'       => $units->kindCounts($companyId),
            'byOccupancy'  => $units->occupancyCounts($companyId),
            'coeffSum'     => $coeff,
            // Con el registro vacío no hay nada que avisar; con datos a medias, sí.
            'coeffWarning' => $coeff > 0 && abs($coeff - 100) > 0.01,
            'totalPeople'  => (new PersonModel())->countFor($companyId),
            'byRole'       => (new RelationModel())->currentCountsByRole($companyId),
            'families'     => (new FamilyModel())->countFor($companyId),
            'pets'         => (new PetModel())->countFor($companyId),
            'dangerous'    => (new PetModel())->dangerousCount($companyId),
            'vehicles'     => (new VehicleModel())->countFor($companyId),
            'authCounts'   => $auths->statusCounts($companyId),
            'expiring'     => (new DocumentModel())->expiring($companyId),
            'vaccines'     => (new PetModel())->vaccinesDue($companyId),
            'planBlock'    => (new PlanGuard())->blockReason($companyId),
            'unitsLeft'    => (new PlanGuard())->unitsLeft($companyId),
        ]);
    }
}
