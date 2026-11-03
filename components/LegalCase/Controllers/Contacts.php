<?php

namespace Components\LegalCase\Controllers;

use App\Libraries\DataTable;
use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Models\ContactModel;
use Components\LegalCase\Models\ParticipantModel;

/** Contactos: personas naturales o jurídicas. El papel lo da cada asunto. */
class Contacts extends BaseLgc
{
    private const FIELDS = ['kind', 'name', 'doc_type', 'doc_number', 'email', 'phone', 'address', 'city', 'country', 'locale', 'notes'];

    public function index()
    {
        $companyId = $this->companyId();
        $dt        = (new DataTable(new ContactModel(), $this->request))
            ->searchable(['name', 'doc_number', 'email', 'phone'])
            ->sortable(['name', 'kind', 'created_at'])
            ->defaultSort('name', 'asc')
            ->query(static fn ($m) => $m->where('cmp_lgc_contacts.company_id', $companyId))
            ->paginate(20);

        return view('Components\LegalCase\Views\contacts\index', ['title' => lang('Lgc.contacts'), 'dt' => $dt]);
    }

    public function create()
    {
        if ($this->isPost()) {
            return $this->save(null);
        }

        return view('Components\LegalCase\Views\contacts\form', [
            'title' => lang('Lgc.contactNew'), 'row' => ['kind' => 'person', 'country' => 'CO', 'locale' => 'es'],
            'action' => base_url('legalcase/contactos/nuevo'),
        ]);
    }

    public function edit($id = null)
    {
        $row = (new ContactModel())->own($this->companyId(), (int) $id);
        if (!$row) {
            return $this->notFound('legalcase/contactos');
        }
        if ($this->isPost()) {
            return $this->save($row);
        }

        return view('Components\LegalCase\Views\contacts\form', [
            'title' => lang('Lgc.contactEdit'), 'row' => $row,
            'action' => base_url('legalcase/contactos/editar/' . (int) $row['id']),
        ]);
    }

    private function save(?array $row)
    {
        $data = [];
        foreach (self::FIELDS as $f) {
            $data[$f] = trim((string) $this->request->getPost($f));
        }
        $data['email']   = strtolower($data['email']);
        $data['country'] = strtoupper($data['country'] ?: 'CO');
        $data['locale']  = in_array($data['locale'], ['es', 'en'], true) ? $data['locale'] : 'es';
        $data['kind']    = in_array($data['kind'], ContactModel::KINDS, true) ? $data['kind'] : 'person';

        $model = new ContactModel();
        if ($row) {
            $ok = $model->update((int) $row['id'], $data);
            $id = (int) $row['id'];
        } else {
            $data['company_id'] = $this->companyId();
            $data['created_by'] = $this->acc()->userId ?: null;
            $ok = $model->insert($data);
            $id = (int) $model->getInsertID();
        }
        if (!$ok) {
            return redirect()->back()->withInput()->with('error', esc(implode(' ', $model->errors())));
        }
        Audit::log($this->companyId(), null, $row ? 'contact.updated' : 'contact.created', 'contact', $id);

        // Volver a donde se pidió (p. ej. el alta de un asunto) si es una ruta del componente.
        $back = (string) $this->request->getPost('return_to');

        return redirect()->to(preg_match('#^legalcase/[a-z0-9/_?=&-]*$#i', $back) ? $back : 'legalcase/contactos')
            ->with('success', esc(lang('Lgc.okSaved')));
    }

    public function delete($id = null)
    {
        $row = (new ContactModel())->own($this->companyId(), (int) $id);
        if (!$row) {
            return $this->notFound('legalcase/contactos');
        }
        $used = (new ParticipantModel())->where('company_id', $this->companyId())->where('contact_id', (int) $row['id'])->countAllResults();
        if ($used > 0) {
            return redirect()->to('legalcase/contactos')->with('error', esc(lang('Lgc.errContactInUse', [$used])));
        }
        (new ContactModel())->delete((int) $row['id']);
        Audit::log($this->companyId(), null, 'contact.deleted', 'contact', (int) $row['id']);

        return redirect()->to('legalcase/contactos')->with('success', esc(lang('Lgc.okDeleted')));
    }
}
