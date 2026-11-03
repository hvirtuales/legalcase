<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\DocumentTypeModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\ServiceRequirementModel;

/** Configuración → Servicios jurídicos (§14). */
class Services extends BaseCrud
{
    public function __construct()
    {
        $this->model   = new ServiceModel();
        $this->route   = 'leadflow/configuracion/servicios';
        $this->viewDir = 'settings/services';
        $this->langKey = 'service';
    }

    public function index()
    {
        $cid  = $this->companyId();
        $rows = $cid ? (new ServiceModel())->forCompany($cid)->orderBy('sort', 'asc')->orderBy('name', 'asc')->findAll() : null;

        return $this->render('settings/services/index', ['rows' => $rows, 'team' => $cid ? Team::users($cid) : [],
            'forms' => $cid ? (new FormModel())->options($cid) : []]);
    }

    /**
     * Añade un requisito a la plantilla documental del servicio.
     *
     * Ruta POST propia en vez de meterlo en el guardado del formulario, igual que las
     * etapas del pipeline: `BaseCrud` guarda un registro plano con `fields()`, y una
     * lista de hijas repetible no cabe ahí sin reescribir el flujo de las tres
     * subclases que lo comparten.
     */
    public function addRequirement($serviceId = null)
    {
        $cid     = $this->companyId();
        $service = $cid ? (new ServiceModel())->owned((int) $serviceId, $cid) : null;
        if (! $service) {
            return $this->notFound('leadflow/configuracion/servicios');
        }

        $etiqueta = $this->str('label', 120);
        if ($etiqueta === '') {
            $this->flash('error', lang('Lfl.vRequirementLabel'));

            return $this->back('leadflow/configuracion/servicios/editar/' . (int) $service['id']);
        }

        $aplica = (string) $this->request->getPost('applies_to');
        $tipo   = $this->postInt('document_type_id');

        (new ServiceRequirementModel())->insert([
            'company_id'       => $cid,
            'service_id'       => (int) $service['id'],
            'label'            => $etiqueta,
            // Contra la lista blanca y contra el catálogo de la empresa: un tipo de
            // otra firma llegado por el POST se descarta en vez de guardarse.
            'document_type_id' => $tipo && (new DocumentTypeModel())->owned($tipo, $cid) ? $tipo : null,
            'is_required'      => $this->request->getPost('is_required') ? 1 : 0,
            'applies_to'       => in_array($aplica, ServiceRequirementModel::APPLIES, true) ? $aplica : 'client',
            'notes'            => $this->str('notes', 255) ?: null,
            'sort'             => (int) $this->request->getPost('sort'),
            'is_active'        => 1,
        ]);

        $this->flash('success', lang('Lfl.okCreated'));

        return $this->back('leadflow/configuracion/servicios/editar/' . (int) $service['id']);
    }

    /**
     * Quita un requisito de la plantilla.
     *
     * **No toca los expedientes ya abiertos**: su checklist es una copia con estado
     * propio, y la FK `requirement_id` es `SET NULL` justamente para que borrar la
     * plantilla no se lleve por delante el checklist de un caso vivo.
     */
    public function deleteRequirement($id = null)
    {
        $cid = $this->companyId();
        $req = $cid ? (new ServiceRequirementModel())->owned((int) $id, $cid) : null;
        if (! $req) {
            return $this->notFound('leadflow/configuracion/servicios');
        }

        (new ServiceRequirementModel())->delete((int) $req['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return $this->back('leadflow/configuracion/servicios/editar/' . (int) $req['service_id']);
    }

    protected function formData(int $companyId, ?array $row): array
    {
        return [
            'team'        => Team::users($companyId),
            'forms'       => (new FormModel())->options($companyId),
            'matterTypes' => (new IntegrationService())->lists($companyId)['matter_types'],
            // La plantilla documental del servicio: solo tiene sentido en la edición,
            // porque hasta que el servicio no existe no hay a qué colgarla.
            'requirements' => $row ? (new ServiceRequirementModel())->forService($companyId, (int) $row['id']) : [],
            'docTypes'     => (new DocumentTypeModel())->options($companyId),
            // El área del catálogo común: es lo que conecta este servicio con el
            // directorio público, para que un lead que entra por «inmigración» nazca
            // con el servicio de inmigración de esta firma.
            'areas'       => (new \Components\LeadFlow\Models\AreaModel())->options(),
        ];
    }

    protected function fields(int $companyId): array
    {
        $owner = $this->postInt('owner_user_id');
        $form  = $this->postInt('form_id');
        $mt    = $this->str('matter_type_id', 40);

        // El nombre del tipo de Matter se toma del catálogo de Docketwise si viene de
        // ahí; si no, del campo libre (sin conexión todavía, o un tipo que falta).
        $types  = $mt !== '' ? (new IntegrationService())->lists($companyId)['matter_types'] : [];
        $mtName = $types[$mt] ?? $this->str('matter_type_name', 120);

        $area  = $this->postInt('area_id');
        $areas = (new \Components\LeadFlow\Models\AreaModel())->options();

        return [
            'area_id'          => isset($areas[$area]) ? $area : null,
            'name'             => $this->str('name', 120),
            'description'      => $this->str('description', 2000) ?: null,
            'is_priority'      => $this->request->getPost('is_priority') ? 1 : 0,
            'owner_user_id'    => $owner && Team::isMember($companyId, $owner) ? $owner : null,
            'form_id'          => $form && (new FormModel())->owned($form, $companyId) ? $form : null,
            'matter_type_id'   => $mt !== '' ? $mt : null,
            'matter_type_name' => $mtName !== '' ? $mtName : null,
            'sort'             => (int) $this->request->getPost('sort'),
            'is_active'        => $this->request->getPost('is_active') ? 1 : 0,
            // Publicar en la ficha de la firma es una decisión aparte de estar
            // activo: un servicio puede usarse para clasificar leads sin que la
            // firma quiera anunciarlo.
            'is_public'        => $this->request->getPost('is_public') ? 1 : 0,
        ];
    }
}
