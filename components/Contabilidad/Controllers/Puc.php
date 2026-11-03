<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Contabilidad\Libraries\CatalogService;
use Components\Contabilidad\Libraries\PucImporter;
use Components\Contabilidad\Models\PucAccountModel;

/**
 * Plan Único de Cuentas — GLOBAL (compartido por todas las empresas). No se
 * filtra por empresa. Su edición afecta a toda la plataforma.
 */
class Puc extends BaseController
{
    private PucAccountModel $model;

    public function __construct()
    {
        $this->model = new PucAccountModel();
    }

    public function index()
    {
        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['code', 'name'])
            ->sortable(['code', 'name', 'type', 'level', 'is_active'])
            ->defaultSort('code', 'asc')
            ->paginate(25);

        return view('Components\Contabilidad\Views\puc\index', ['title' => 'Plan Único de Cuentas', 'dt' => $dt]);
    }

    public function seed()
    {
        $n = (new CatalogService())->seedPuc();

        return redirect()->to('contabilidad/puc')->with('success', $n > 0 ? "Se agregaron {$n} cuentas al PUC." : 'El PUC de arranque ya estaba cargado.');
    }

    /** Importación CSV del PUC (§5.10): formulario (GET) y procesamiento (POST). */
    public function import()
    {
        if ($this->request->getMethod() !== 'POST') {
            return view('Components\Contabilidad\Views\puc\import', ['title' => 'Importar PUC']);
        }

        $file = $this->request->getFile('csv');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'Selecciona un archivo CSV válido.');
        }
        if (!in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', 'El archivo debe ser CSV (.csv).');
        }

        $mode   = $this->request->getPost('mode') === 'upsert' ? 'upsert' : 'add';
        $parsed = (new PucImporter())->fromCsv($file->getTempName());

        $inserted = 0;
        $updated  = 0;
        $skipped  = (int) $parsed['skipped'];
        foreach ($parsed['rows'] as $row) {
            $existing = $this->model->where('code', $row['code'])->first();
            if ($existing) {
                if ($mode === 'upsert') {
                    $this->model->update($existing['id'], $row);
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }
            if ($this->model->insert($row)) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        $msg = "Importación del PUC completada: {$inserted} nueva(s), {$updated} actualizada(s), {$skipped} omitida(s).";

        return redirect()->to('contabilidad/puc')->with('success', $msg);
    }

    /** Descarga la plantilla CSV del PUC (delimitador `;`). */
    public function template()
    {
        $headers = implode(';', PucImporter::TEMPLATE_HEADERS);
        $rows    = [
            ['1105', 'Caja', 'activo', 'debito', '4', '11', 'Efectivo en caja'],
            ['2205', 'Proveedores nacionales', 'pasivo', 'credito', '4', '22', 'Cuentas por pagar a proveedores'],
        ];
        $csv = "\xEF\xBB\xBF" . $headers . "\r\n";
        foreach ($rows as $r) {
            $csv .= implode(';', $r) . "\r\n";
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="plantilla_puc.csv"')
            ->setBody($csv);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->save(null);
        }

        return view('Components\Contabilidad\Views\puc\form', ['title' => 'Nueva cuenta PUC', 'account' => []]);
    }

    public function edit($id)
    {
        $account = $this->model->find($id);
        if (!$account) {
            return redirect()->to('contabilidad/puc')->with('error', 'Cuenta no encontrada.');
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save((int) $id);
        }

        return view('Components\Contabilidad\Views\puc\form', ['title' => 'Editar cuenta PUC', 'account' => $account]);
    }

    private function save(?int $id)
    {
        $code = trim((string) $this->request->getPost('code'));
        $dup  = $this->model->where('code', $code);
        if ($id) {
            $dup->where('id !=', $id);
        }
        if ($code !== '' && $dup->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'Ya existe una cuenta con ese código.');
        }

        $data = [
            'code'        => $code,
            'name'        => trim((string) $this->request->getPost('name')),
            'type'        => $this->request->getPost('type') ?: 'activo',
            'nature'      => $this->request->getPost('nature') ?: 'debito',
            'level'       => (int) ($this->request->getPost('level') ?: 1),
            'parent_code' => trim((string) $this->request->getPost('parent_code')) ?: null,
            'description' => $this->request->getPost('description') ?: null,
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $ok = $id ? $this->model->update($id, $data) : $this->model->insert($data);
        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('contabilidad/puc')->with('success', 'Cuenta guardada.');
    }

    public function delete($id)
    {
        if (!$this->model->find($id)) {
            return redirect()->to('contabilidad/puc')->with('error', 'Cuenta no encontrada.');
        }
        $this->model->delete($id);

        return redirect()->to('contabilidad/puc')->with('success', 'Cuenta eliminada.');
    }
}
