<?php

namespace Components\Mailer\Controllers;

use App\Controllers\BaseController;
use Components\Mailer\Libraries\ContactImporter;
use Components\Mailer\Libraries\EmailValidator;
use Components\Mailer\Libraries\SuppressionService;
use Components\Mailer\Models\ContactModel;
use Components\Mailer\Models\ListModel;

/** Contactos de una lista: alta manual, importación CSV, baja y borrado. */
class Contacts extends BaseController
{
    private ContactModel $model;

    public function __construct()
    {
        $this->model = new ContactModel();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    private function ownedList($listId): ?array
    {
        $companyId = $this->companyId();
        $list      = (new ListModel())->find($listId);

        return ($list && (int) $list['company_id'] === $companyId) ? $list : null;
    }

    /** Alta manual de un contacto. */
    public function add($listId)
    {
        $list = $this->ownedList($listId);
        if (!$list) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }
        $companyId = (int) $list['company_id'];
        $email     = strtolower(trim((string) $this->request->getPost('email')));
        $name      = trim((string) $this->request->getPost('name'));
        $entity    = trim((string) $this->request->getPost('entity'));

        $check = (new EmailValidator())->check($email, true); // incluye verificación de dominio (MX)
        if (!$check['ok']) {
            return redirect()->back()->with('error', lang('Mailer.mBadEmailWhy', [$check['reason']]));
        }
        // Dedup dentro de la lista.
        if ($this->model->where('list_id', $listId)->where('email', $email)->countAllResults() > 0) {
            return redirect()->back()->with('error', lang('Mailer.mContactDup'));
        }
        $suppressed = (new SuppressionService())->isSuppressed($companyId, $email);
        $this->model->insert([
            'company_id'        => $companyId,
            'list_id'           => (int) $listId,
            'email'             => $email,
            'name'              => $name ?: null,
            'entity'            => $entity ?: null,
            'status'            => $suppressed ? 'unsubscribed' : 'subscribed',
            'unsubscribe_token' => bin2hex(random_bytes(16)),
            'subscribed_at'     => date('Y-m-d H:i:s'),
            // La prueba del consentimiento: la política de SES exige poder
            // demostrar que el contacto pidió recibir. Aquí lo metió alguien a
            // mano, así que consta eso y desde dónde.
        ] + $this->consent('manual', trim((string) $this->request->getPost('consent_note'))));

        $msg = lang('Mailer.mContactAdded') . ($suppressed ? lang('Mailer.mContactSupp') : '');

        return redirect()->to('mailer/listas/' . $listId)->with('success', $msg);
    }

    /** Importación CSV a la lista. */
    public function import($listId)
    {
        $list = $this->ownedList($listId);
        if (!$list) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }
        if ($this->request->getMethod() !== 'POST') {
            return view('Components\Mailer\Views\contacts\import', ['title' => lang('Mailer.importContacts'), 'list' => $list]);
        }

        $file = $this->request->getFile('csv');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', lang('Mailer.mNoCsv'));
        }
        if (!in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', lang('Mailer.mNotCsv'));
        }

        $parsed = (new ContactImporter())->fromCsv($file->getTempName());
        $origen = trim((string) $this->request->getPost('consent_note'));
        $res    = $this->insertValidRows($list, $parsed['rows'], $origen);

        $summary = lang('Mailer.mImportSummary', [$res['inserted'], $res['skipped'], $parsed['duplicates']]);

        // Las direcciones de área no se rechazan, se avisan: son las que más
        // quejas de spam generan, y quien importa decide si las quiere.
        if (!empty($parsed['roles'])) {
            $summary .= ' ' . lang('Mailer.roleWarn', [count($parsed['roles']), $parsed['roles'][0]]);
        }

        // Filas con correo inválido → pantalla de revisión/edición (no se descartan).
        if (!empty($parsed['invalid'])) {
            session()->set('mailer_import_review', [
                'list_id' => (int) $listId,
                'rows'    => $parsed['invalid'],
                // El origen se guarda con las filas: la pantalla de revisión no
                // vuelve a pedirlo y sin esto las corregidas entrarían sin él.
                'origen'  => $origen,
            ]);

            return redirect()->to('mailer/listas/' . $listId . '/contactos/revisar')
                ->with('success', $summary . ' ' . lang('Mailer.mImportReview', [count($parsed['invalid'])]));
        }

        return redirect()->to('mailer/listas/' . $listId)->with('success', $summary);
    }

    /**
     * Pantalla de revisión de las filas con correo inválido: permite editarlas
     * (GET) y reintentar la importación con los datos corregidos (POST).
     */
    public function review($listId)
    {
        $list = $this->ownedList($listId);
        if (!$list) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->reviewSave($list);
        }

        $data = session()->get('mailer_import_review');
        if (!$data || (int) $data['list_id'] !== (int) $listId || empty($data['rows'])) {
            return redirect()->to('mailer/listas/' . $listId)->with('error', lang('Mailer.mNoReview'));
        }

        return view('Components\Mailer\Views\contacts\review', [
            'title' => lang('Mailer.reviewShort'),
            'list'  => $list,
            'rows'  => $data['rows'],
        ]);
    }

    /** Procesa las filas corregidas: valida, importa las válidas y re-muestra las que sigan mal. */
    private function reviewSave(array $list)
    {
        $listId    = (int) $list['id'];
        $emails    = (array) $this->request->getPost('email');
        $names     = (array) $this->request->getPost('name');
        $entities  = (array) $this->request->getPost('entity');
        $validator = new EmailValidator();
        // El origen del consentimiento lo dejó la importación en sesión: aquí
        // ya no hay formulario que lo traiga, y sin él las filas corregidas
        // entrarían sin poder decir de dónde salieron.
        $origen = (string) (session()->get('mailer_import_review')['origen'] ?? '');

        $valid   = [];
        $invalid = [];
        foreach ($emails as $i => $rawEmail) {
            $email  = strtolower(trim((string) $rawEmail));
            $name   = trim((string) ($names[$i] ?? ''));
            $entity = trim((string) ($entities[$i] ?? ''));
            if ($email === '') {
                continue; // fila vaciada → se descarta a propósito
            }
            $check = $validator->check($email);
            if ($check['ok']) {
                $valid[] = ['email' => $email, 'name' => $name ?: null, 'entity' => $entity ?: null];
            } else {
                $invalid[] = ['email' => $email, 'name' => $name ?: null, 'entity' => $entity ?: null, 'reason' => $check['reason']];
            }
        }

        $res = $this->insertValidRows($list, $valid, $origen);

        // Si aún quedan inválidas, vuelve a mostrar la revisión con esas filas.
        if (!empty($invalid)) {
            session()->set('mailer_import_review', ['list_id' => $listId, 'rows' => $invalid, 'origen' => $origen]);

            return redirect()->to('mailer/listas/' . $listId . '/contactos/revisar')
                ->with('error', lang('Mailer.mFixedLeft', [$res['inserted'], count($invalid)]));
        }

        session()->remove('mailer_import_review');

        return redirect()->to('mailer/listas/' . $listId)
            ->with('success', lang('Mailer.mFixedDone', [$res['inserted'], $res['skipped']]));
    }

    /**
     * Inserta filas con correo ya validado, deduplicando contra la lista y
     * marcando como dados de baja los que estén en supresión.
     *
     * @param array<int,array{email:string,name:?string,entity:?string}> $rows
     *
     * @return array{inserted:int, skipped:int}
     */
    /**
     * De dónde salió un contacto. Es lo que se enseña si AWS pregunta por qué
     * le escribimos a alguien, así que se guarda siempre, no solo cuando hay
     * nota: una fila sin origen es una fila que no se puede defender.
     */
    private function consent(string $source, string $note = ''): array
    {
        return [
            'consent_source' => $source,
            'consent_at'     => date('Y-m-d H:i:s'),
            'consent_ip'     => $this->request->getIPAddress(),
            'consent_note'   => $note !== '' ? mb_substr($note, 0, 255) : null,
        ];
    }

    private function insertValidRows(array $list, array $rows, string $origen = ''): array
    {
        $listId     = (int) $list['id'];
        $companyId  = (int) $list['company_id'];
        $suppressed = (new SuppressionService())->emailSet($companyId);

        $existing = [];
        foreach ($this->model->select('email')->where('list_id', $listId)->findAll() as $e) {
            $existing[$e['email']] = true;
        }

        $now      = date('Y-m-d H:i:s');
        $inserted = 0;
        $skipped  = 0;
        $batch    = [];
        foreach ($rows as $row) {
            $email = $row['email'];
            if (isset($existing[$email])) {
                $skipped++;
                continue;
            }
            $existing[$email] = true;
            $batch[]          = [
                'company_id'        => $companyId,
                'list_id'           => $listId,
                'email'             => $email,
                'name'              => $row['name'] ?? null,
                'entity'            => $row['entity'] ?? null,
                'status'            => isset($suppressed[$email]) ? 'unsubscribed' : 'subscribed',
                'unsubscribe_token' => bin2hex(random_bytes(16)),
                'subscribed_at'     => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ] + $this->consent('import', $origen);
            $inserted++;
        }
        if ($batch) {
            $this->model->insertBatch($batch);
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Verifica en bloque el dominio (MX) + typos de los contactos suscritos de la
     * lista; marca como no enviables (bounced) los que no puedan recibir correo.
     */
    public function verifyDomains($listId)
    {
        $list = $this->ownedList($listId);
        if (!$list) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }
        $companyId = (int) $list['company_id'];
        $validator = new EmailValidator();

        $contacts = $this->model
            ->where('company_id', $companyId)
            ->where('list_id', $listId)
            ->where('status', 'subscribed')
            ->findAll();

        $checked = count($contacts);
        $invalid = 0;
        foreach ($contacts as $c) {
            if (!$validator->check($c['email'], true)['ok']) {
                $this->model->update($c['id'], ['status' => 'bounced']);
                $invalid++;
            }
        }

        $msg = lang('Mailer.mDomainsDone', [$checked])
            . ($invalid > 0 ? lang('Mailer.mDomainsBad', [$invalid]) : lang('Mailer.mDomainsOk'));

        return redirect()->to('mailer/listas/' . $listId)->with('success', $msg);
    }

    /**
     * Elimina contactos duplicados (mismo correo) dentro de la lista, dejando un
     * solo registro por correo. Conserva el que respeta la baja/rebote (para no
     * reactivar a quien optó por no recibir) o, en su defecto, el más antiguo, y
     * rellena nombre/entidad faltantes con los datos de los duplicados.
     */
    public function removeDuplicates($listId)
    {
        $list = $this->ownedList($listId);
        if (!$list) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mListNotFound'));
        }
        $companyId = (int) $list['company_id'];

        $contacts = $this->model
            ->where('company_id', $companyId)
            ->where('list_id', $listId)
            ->orderBy('id', 'asc')
            ->findAll();

        // Agrupa por correo normalizado (minúsculas/sin espacios).
        $groups = [];
        foreach ($contacts as $c) {
            $groups[strtolower(trim($c['email']))][] = $c;
        }

        // Prioridad de conservación: respeta primero los estados de "no enviar".
        $rank    = ['complained' => 4, 'unsubscribed' => 3, 'bounced' => 2, 'subscribed' => 1];
        $removed = 0;
        foreach ($groups as $rows) {
            if (count($rows) < 2) {
                continue;
            }
            usort($rows, static function ($a, $b) use ($rank) {
                $ra = $rank[$a['status']] ?? 0;
                $rb = $rank[$b['status']] ?? 0;

                return ($ra !== $rb) ? ($rb - $ra) : ($a['id'] - $b['id']);
            });
            $survivor = $rows[0];

            // Rellena datos faltantes del superviviente con los de los duplicados.
            $upd = [];
            foreach (['name', 'entity'] as $f) {
                if (empty($survivor[$f])) {
                    foreach ($rows as $r) {
                        if (!empty($r[$f])) {
                            $upd[$f] = $r[$f];
                            break;
                        }
                    }
                }
            }
            if ($upd) {
                $this->model->update($survivor['id'], $upd);
            }

            for ($i = 1, $n = count($rows); $i < $n; $i++) {
                $this->model->delete($rows[$i]['id']);
                $removed++;
            }
        }

        $msg = $removed > 0 ? lang('Mailer.mDupRemoved', [$removed]) : lang('Mailer.mDupNone');

        return redirect()->to('mailer/listas/' . $listId)->with('success', $msg);
    }

    /** Plantilla CSV descargable. */
    public function template()
    {
        $csv = "\xEF\xBB\xBF" . implode(';', ContactImporter::TEMPLATE_HEADERS) . "\r\n"
            . lang('Mailer.csvSample') . "\r\n";

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="plantilla_contactos.csv"')
            ->setBody($csv);
    }

    /** Baja manual desde el panel (agrega a supresión). */
    public function unsubscribe($id)
    {
        $companyId = $this->companyId();
        $contact   = $this->model->find($id);
        if (!$contact || (int) $contact['company_id'] !== $companyId) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mContactNotFound'));
        }
        (new SuppressionService())->suppress($companyId, $contact['email'], 'unsubscribe', 'Baja manual desde el panel');

        return redirect()->to('mailer/listas/' . $contact['list_id'])->with('success', lang('Mailer.mContactDown'));
    }

    public function delete($id)
    {
        $companyId = $this->companyId();
        $contact   = $this->model->find($id);
        if (!$contact || (int) $contact['company_id'] !== $companyId) {
            return redirect()->to('mailer/listas')->with('error', lang('Mailer.mContactNotFound'));
        }
        $listId = $contact['list_id'];
        $this->model->delete($id);

        return redirect()->to('mailer/listas/' . $listId)->with('success', lang('Mailer.mContactDeleted'));
    }
}
