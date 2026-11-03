<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Contabilidad\Libraries\AttachmentService;
use Components\Contabilidad\Libraries\BeneficiaryImporter;
use Components\Contabilidad\Models\BeneficiaryModel;

class Beneficiaries extends BaseController
{
    protected BeneficiaryModel $model;

    public function __construct()
    {
        $this->model = new BeneficiaryModel();
    }

    /** Empresa activa (contexto multi-tenant). null = Global. */
    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Contabilidad\Views\beneficiaries\index', ['title' => 'Beneficiarios', 'dt' => null]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'id_number', 'email', 'phone', 'city'])
            ->sortable(['id', 'name', 'id_type', 'id_number', 'city'])
            ->defaultSort('name', 'asc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        return view('Components\Contabilidad\Views\beneficiaries\index', ['title' => 'Beneficiarios', 'dt' => $dt]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('contabilidad/beneficiarios')->with('error', 'Selecciona una empresa activa para crear beneficiarios.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store($companyId);
        }

        return view('Components\Contabilidad\Views\beneficiaries\create', ['title' => 'Nuevo beneficiario']);
    }

    private function store(int $companyId)
    {
        $data = $this->collect($companyId);
        if ($this->duplicate($companyId, $data['id_type'], $data['id_number'])) {
            return redirect()->back()->withInput()->with('error', 'Ya existe un tercero con ese tipo y número de documento en esta empresa.');
        }
        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $id = (int) $this->model->getInsertID();

        // Documentos adjuntos cargados en el alta (imagen/PDF)
        $svc = new AttachmentService();
        $up  = 0;
        foreach ($this->request->getFileMultiple('documents') ?? [] as $f) {
            if (!$f || $f->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($svc->store($f, 'beneficiary', $id, session()->get('user_id'))['ok']) {
                $up++;
            }
        }
        $msg = 'Beneficiario creado correctamente.' . ($up ? ' ' . $up . ' documento(s) adjuntado(s).' : '');

        return redirect()->to('contabilidad/beneficiarios/editar/' . $id)->with('success', $msg);
    }

    public function edit($id)
    {
        $companyId = $this->companyId();
        $b         = $this->model->find($id);
        if (!$b || (int) $b['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/beneficiarios')->with('error', 'Beneficiario no encontrado.');
        }

        if ($this->request->getMethod() === 'POST') {
            $data = $this->collect($companyId);
            if ($this->duplicate($companyId, $data['id_type'], $data['id_number'], (int) $id)) {
                return redirect()->back()->withInput()->with('error', 'Ya existe un tercero con ese documento en esta empresa.');
            }
            if (!$this->model->update($id, $data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }

            return redirect()->to('contabilidad/beneficiarios')->with('success', 'Beneficiario actualizado.');
        }

        return view('Components\Contabilidad\Views\beneficiaries\edit', [
            'title'       => 'Editar beneficiario',
            'beneficiary' => $b,
            'attachments' => (new AttachmentService())->listFor('beneficiary', (int) $id),
        ]);
    }

    public function delete($id)
    {
        $companyId = $this->companyId();
        $b         = $this->model->find($id);
        if (!$b || (int) $b['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/beneficiarios')->with('error', 'Beneficiario no encontrado.');
        }
        $this->model->delete($id);

        return redirect()->to('contabilidad/beneficiarios')->with('success', 'Beneficiario eliminado.');
    }

    /** Importación CSV (§5.8): formulario (GET) y procesamiento (POST). */
    public function import()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('contabilidad/beneficiarios')->with('error', 'Selecciona una empresa activa para importar beneficiarios.');
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('Components\Contabilidad\Views\beneficiaries\import', ['title' => 'Importar beneficiarios']);
        }

        $file = $this->request->getFile('csv');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'Selecciona un archivo CSV válido.');
        }
        $ext = strtolower($file->getClientExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', 'El archivo debe ser CSV (.csv).');
        }

        $mode   = $this->request->getPost('mode') === 'upsert' ? 'upsert' : 'add';
        $parsed = (new BeneficiaryImporter())->fromCsv($file->getTempName());

        $inserted = 0;
        $updated  = 0;
        $skipped  = (int) $parsed['skipped'];
        foreach ($parsed['rows'] as $row) {
            $existing = $this->model
                ->where('company_id', $companyId)
                ->where('id_type', $row['id_type'])
                ->where('id_number', $row['id_number'])
                ->first();

            if ($existing && $row['id_number'] !== '') {
                if ($mode === 'upsert') {
                    $this->model->skipValidation(true)->update($existing['id'], $row);
                    $this->model->skipValidation(false);
                    $updated++;
                } else {
                    $skipped++; // add: no se tocan los existentes
                }
                continue;
            }

            $row['company_id'] = $companyId;
            if ($this->model->insert($row)) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        $msg = "Importación completada: {$inserted} nuevo(s), {$updated} actualizado(s), {$skipped} omitido(s).";

        return redirect()->to('contabilidad/beneficiarios')->with('success', $msg);
    }

    /** Descarga la plantilla CSV (§5.8, delimitador `;`). */
    public function template()
    {
        $headers = implode(';', BeneficiaryImporter::TEMPLATE_HEADERS);
        $example = implode(';', [
            'Proveedora S.A.S.', 'NIT', '900123456', 'contacto@proveedora.com', '3001234567',
            'Bogotá', 'Calle 1 # 2-3', 'Juan Pérez', 'Cliente frecuente', 'Bancolombia', 'Ahorros', '1234567890',
        ]);
        $csv = "\xEF\xBB\xBF" . $headers . "\r\n" . $example . "\r\n"; // BOM UTF-8 para Excel

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="plantilla_beneficiarios.csv"')
            ->setBody($csv);
    }

    /** ID único por empresa (tipo + número), excluyendo el propio en edición. */
    private function duplicate(int $companyId, string $idType, string $idNumber, ?int $exceptId = null): bool
    {
        if ($idNumber === '') {
            return false;
        }
        $q = $this->model->where('company_id', $companyId)->where('id_type', $idType)->where('id_number', $idNumber);
        if ($exceptId) {
            $q->where('id !=', $exceptId);
        }

        return $q->countAllResults() > 0;
    }

    private function collect(int $companyId): array
    {
        return [
            'company_id'          => $companyId,
            'name'                => trim((string) $this->request->getPost('name')),
            'id_type'             => $this->request->getPost('id_type') ?: 'NIT',
            'id_number'           => trim((string) $this->request->getPost('id_number')),
            'email'               => trim((string) $this->request->getPost('email')),
            'phone'               => trim((string) $this->request->getPost('phone')),
            'address'             => trim((string) $this->request->getPost('address')),
            'city'                => trim((string) $this->request->getPost('city')),
            'contact_name'        => trim((string) $this->request->getPost('contact_name')),
            'notes'               => $this->request->getPost('notes'),
            'bank_name'           => trim((string) $this->request->getPost('bank_name')),
            'bank_account_type'   => $this->request->getPost('bank_account_type') ?: 'Ahorros',
            'bank_account_number' => trim((string) $this->request->getPost('bank_account_number')),
        ];
    }
}
