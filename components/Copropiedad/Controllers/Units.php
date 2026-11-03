<?php

namespace Components\Copropiedad\Controllers;

use App\Libraries\DataTable;
use Components\Copropiedad\Libraries\PlanGuard;
use Components\Copropiedad\Libraries\RegistryService;
use Components\Copropiedad\Models\BlockModel;
use Components\Copropiedad\Models\UnitModel;

/**
 * Inmuebles: el maestro central del componente.
 *
 * `show()` —la **ficha**— es la pantalla que justifica el módulo: en un sitio,
 * quién es el dueño, quién vive, desde cuándo, con qué carro, qué perro, a quién
 * llamar en una emergencia, qué se autorizó y qué papeles hay.
 */
class Units extends BaseCrud
{
    protected string $route   = 'copropiedad/inmuebles';
    protected string $viewDir = 'units';
    protected string $langKey = 'units';

    private PlanGuard $plan;
    private RegistryService $registry;

    public function __construct()
    {
        $this->model    = new UnitModel();
        $this->plan     = new PlanGuard();
        $this->registry = new RegistryService();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\units\index', ['dt' => null]);
        }

        // Filtros de la pantalla. Van por query string y se propagan al DataTable
        // con `extraParams` para que la paginación no los pierda.
        $block     = (int) ($this->request->getGet('torre') ?? 0);
        $kind      = (string) ($this->request->getGet('tipo') ?? '');
        $occupancy = (string) ($this->request->getGet('estado') ?? '');

        // Las columnas van CALIFICADAS: `cmp_cop_blocks` tiene también `code` y
        // `name`, así que un `ORDER BY code` a secas es ambiguo y el motor lo
        // rechaza. Las cabeceras de la tabla lo resuelven con `sortKey`.
        $dt = (new DataTable(new UnitModel(), $this->request))
            ->searchable(['cmp_cop_units.code', 'cmp_cop_units.matricula', 'cmp_cop_units.notes'])
            ->sortable([
                'cmp_cop_units.code', 'cmp_cop_units.kind', 'cmp_cop_units.floor',
                'cmp_cop_units.area', 'cmp_cop_units.coefficient', 'cmp_cop_units.occupancy',
            ])
            ->defaultSort('cmp_cop_units.code', 'asc')
            ->query(function ($b) use ($companyId, $block, $kind, $occupancy) {
                $b->select('cmp_cop_units.*, cmp_cop_blocks.name AS block_name')
                    ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
                    ->where('cmp_cop_units.company_id', $companyId);

                if ($block > 0) {
                    $b->where('cmp_cop_units.block_id', $block);
                }
                if (in_array($kind, UnitModel::KINDS, true)) {
                    $b->where('cmp_cop_units.kind', $kind);
                }
                if (in_array($occupancy, UnitModel::OCCUPANCIES, true)) {
                    $b->where('cmp_cop_units.occupancy', $occupancy);
                }
            })
            ->paginate(20);

        return view('Components\Copropiedad\Views\units\index', [
            'dt'         => $dt,
            'blocks'     => (new BlockModel())->optionsFor($companyId),
            'block'      => $block,
            'kind'       => $kind,
            'occupancy'  => $occupancy,
            'planBlock'  => $this->plan->blockReason($companyId),
            'unitsLeft'  => $this->plan->unitsLeft($companyId),
            'coeffSum'   => (new UnitModel())->coefficientSum($companyId),
            // Se pregunta al total, no a `$dt['rows']`: con un filtro activo la
            // página puede venir vacía y el registro estar lleno.
            'isEmpty'    => (new UnitModel())->countFor($companyId) === 0,
        ]);
    }

    /** La ficha del inmueble: todo lo que cuelga de él. */
    public function show($id = null)
    {
        $companyId = $this->companyId();
        $card      = $companyId ? $this->registry->unitCard((int) $id, $companyId) : null;

        if (! $card) {
            return redirect()->to($this->route)->with('error', lang('Cop.errUnitNotFound'));
        }

        return view('Components\Copropiedad\Views\units\show', $card + [
            'people' => (new \Components\Copropiedad\Models\PersonModel())->optionsFor($companyId),
        ]);
    }

    /** El historial completo, cuando la ficha se queda corta. */
    public function history($id = null)
    {
        $companyId = $this->companyId();
        $unit      = $companyId ? $this->model->owned((int) $id, $companyId) : null;

        if (! $unit) {
            return redirect()->to($this->route)->with('error', lang('Cop.errUnitNotFound'));
        }

        return view('Components\Copropiedad\Views\units\history', [
            'unit'    => $unit,
            'history' => (new \Components\Copropiedad\Models\RelationModel())->historyFor((int) $id, $companyId),
        ]);
    }

    /**
     * El plan se hace valer **al crear**, en el servidor.
     *
     * Ocultar el botón en el listado no impide un POST directo a esta ruta, que es
     * exactamente lo que el contrato §11.3 pide comprobar.
     */
    public function create()
    {
        $companyId = $this->companyId();
        if ($companyId && ($motivo = $this->plan->blockReason($companyId))) {
            return redirect()->to($this->route)->with('error', $motivo);
        }

        return parent::create();
    }

    protected function fields(int $companyId): array
    {
        return [
            'company_id'     => $companyId,
            'block_id'       => $this->int('block_id'),
            'parent_unit_id' => $this->int('parent_unit_id'),
            'code'           => (string) $this->str('code', 30),
            'kind'           => $this->pick('kind', UnitModel::KINDS, 'apartamento'),
            'floor'          => $this->str('floor', 10),
            'area'           => $this->dec('area'),
            'coefficient'    => $this->dec('coefficient'),
            'bedrooms'       => $this->int('bedrooms'),
            'bathrooms'      => $this->int('bathrooms'),
            'matricula'      => $this->str('matricula', 40),
            'occupancy'      => $this->pick('occupancy', UnitModel::OCCUPANCIES, 'desocupado'),
            'notes'          => $this->str('notes'),
            'is_active'      => $this->flag('is_active'),
        ];
    }

    protected function extraCheck(array $data, ?array $existing): ?string
    {
        $companyId = (int) $data['company_id'];

        if ($this->model->codeTaken($companyId, $data['code'], $existing['id'] ?? null)) {
            return lang('Cop.errUnitCodeTaken', [$data['code']]);
        }

        // Adscribirse a uno mismo dejaría la ficha en un bucle.
        if ($existing && $data['parent_unit_id'] === (int) $existing['id']) {
            return lang('Cop.errUnitSelfParent');
        }

        // El padre tiene que ser de la misma empresa: si no, se podría colgar un
        // parqueadero del apartamento de otro conjunto.
        if ($data['parent_unit_id'] && ! $this->model->owned($data['parent_unit_id'], $companyId)) {
            return lang('Cop.errUnitParentNotFound');
        }

        // Solo un bloque de esta empresa.
        if ($data['block_id'] && ! (new BlockModel())->owned($data['block_id'], $companyId)) {
            return lang('Cop.errBlockNotFound');
        }

        return null;
    }

    protected function formData(int $companyId, ?array $row = null): array
    {
        // Como padre solo se ofrecen los inmuebles «grandes»: adscribir un
        // apartamento a un parqueadero no significa nada.
        $padres = $this->model->optionsFor($companyId, ['apartamento', 'casa', 'local', 'oficina']);
        if ($row) {
            unset($padres[(int) $row['id']]);
        }

        return [
            'blocks'  => (new BlockModel())->optionsFor($companyId),
            'parents' => $padres,
        ];
    }

    /** Tras guardar, el estado de ocupación vuelve a cuadrar con las relaciones. */
    protected function afterSave(int $id, int $companyId): void
    {
        $this->registry->syncOccupancy($id, $companyId);
    }

    /** Cambiar el estado a mano desde la ficha. */
    public function occupancy($id = null)
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNoCompany'));
        }

        $r = $this->registry->setOccupancy((int) $id, $companyId, (string) $this->request->getPost('occupancy'));

        return redirect()->to($this->route . '/ficha/' . (int) $id)
            ->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    /**
     * Siembra un conjunto de ejemplo.
     *
     * Solo si el registro está vacío —lo comprueba `DemoData::isEmpty()`, no la
     * vista— para que no haya forma de pisar datos reales con un POST directo.
     */
    public function demo()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNoCompany'));
        }

        $r = (new \Components\Copropiedad\Libraries\DemoData($companyId))->seed();

        return redirect()->to($this->route)->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    /** La baja pasa por el servicio, que limpia lo que el soft-delete no limpia. */
    public function delete($id = null)
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNoCompany'));
        }

        $r = $this->registry->deleteUnit((int) $id, $companyId);

        return redirect()->to($this->route)->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
