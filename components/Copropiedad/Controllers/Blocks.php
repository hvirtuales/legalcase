<?php

namespace Components\Copropiedad\Controllers;

use Components\Copropiedad\Models\BlockModel;
use Components\Copropiedad\Models\UnitModel;

/**
 * Torres, bloques, etapas y manzanas: la estructura del conjunto.
 *
 * No está en el menú lateral a propósito —se entra desde el listado de Inmuebles,
 * que es donde se echa de menos— y va con permiso `cop.configure`: cambiar la
 * estructura no es lo mismo que corregir un teléfono.
 */
class Blocks extends BaseCrud
{
    protected string $route   = 'copropiedad/torres';
    protected string $viewDir = 'blocks';
    protected string $langKey = 'blocks';

    public function __construct()
    {
        $this->model = new BlockModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\blocks\index', ['blocks' => null]);
        }

        return view('Components\Copropiedad\Views\blocks\index', [
            'blocks' => $this->model->listFor($companyId),
            'counts' => $this->unitCounts($companyId),
        ]);
    }

    /**
     * Cuántos inmuebles tiene cada torre, en **una** consulta.
     *
     * El listado las pinta todas: contando por torre serían tantas consultas como
     * torres.
     */
    private function unitCounts(int $companyId): array
    {
        $rows = (new UnitModel())->builder()
            ->select('block_id, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('block_id IS NOT NULL')
            ->where('deleted_at', null)
            ->groupBy('block_id')
            ->get()->getResultArray();

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r['block_id']] = (int) $r['total'];
        }

        return $out;
    }

    protected function fields(int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'name'       => (string) $this->str('name', 80),
            'code'       => $this->str('code', 20),
            'kind'       => $this->pick('kind', BlockModel::KINDS, 'torre'),
            'floors'     => $this->int('floors'),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'notes'      => $this->str('notes'),
            'is_active'  => $this->flag('is_active'),
        ];
    }

    /**
     * Borrar una torre **no borra sus inmuebles**: los deja sin torre.
     *
     * La FK es `SET NULL`, pero el soft-delete de la torre no la dispara —la fila
     * sigue ahí— así que se sueltan a mano. Es el mismo caso que `sectors` →
     * `companies.sector_id` en el núcleo. La confirmación de la vista avisa de
     * cuántos inmuebles se quedan sin torre.
     */
    public function delete($id = null)
    {
        $row = $this->own((int) $id);
        if (! $row) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNotFound'));
        }

        (new UnitModel())->builder()
            ->where('company_id', (int) $row['company_id'])
            ->where('block_id', (int) $row['id'])
            ->update(['block_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->model->delete((int) $row['id']);

        return redirect()->to($this->route)->with('success', lang('Cop.okBlockDeleted'));
    }
}
