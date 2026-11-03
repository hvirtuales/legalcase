<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;
use Components\Formularios\Libraries\FieldRegistry;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Models\FormModel;

/**
 * El editor: pantalla completa, guardado por JSON.
 *
 * El servidor DEVUELVE EL ARBOL YA SANEADO en cada guardado, y el editor se
 * sincroniza con eso. Así lo que se ve en pantalla es lo que de verdad quedó
 * guardado, y no una versión optimista que el saneador ya recortó.
 */
class Editor extends BaseController
{
    private FormModel $forms;
    private FormService $svc;

    public function __construct()
    {
        $this->forms = new FormModel();
        $this->svc   = new FormService();
    }

    private function own(int $id): ?array
    {
        $companyId = (int) (session('active_company_id') ?? 0);

        return $companyId ? $this->forms->ofCompany($id, $companyId) : null;
    }

    public function index($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        $schema = $this->svc->sanitizeTree($form['schema_json'] ?? []);

        return view('Components\Formularios\Views\editor', [
            'title'    => $form['title'],
            'form'     => $form,
            'schema'   => $schema,
            'settings' => $this->svc->sanitizeSettings($form['settings'] ?? []),
            'registry' => FieldRegistry::forJs(),
            'groups'   => FieldRegistry::GROUPS,
            'publicUrl' => base_url('formulario/' . $form['public_token']),
        ]);
    }

    /**
     * Guarda el árbol. Responde JSON con lo que quedó guardado.
     *
     * `needsTitle` le dice al editor que recuerde ponerle nombre: el alta entra
     * directo aquí con un título provisional, y montar el contenido es lo
     * urgente, no bautizarlo.
     */
    public function save($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => lang('Frm.notFound')]);
        }

        $payload = $this->request->getJSON(true);
        if (!is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => lang('Frm.errBadPayload')]);
        }

        $schema = $this->svc->sanitizeTree($payload['schema'] ?? []);
        $this->forms->update((int) $form['id'], ['schema_json' => $schema]);

        return $this->response->setJSON([
            'ok'         => true,
            'schema'     => $schema,
            'needsTitle' => trim((string) $form['title']) === lang('Frm.untitled'),
            'dirty'      => (string) $form['status'] === 'published',
            'csrf'       => csrf_hash(),
        ]);
    }

    /**
     * Ajustes del formulario (pestaña «Formulario» del inspector), por JSON.
     *
     * Aquí vive el cambio de PRESENTACION: pasar de ficha a pantalla completa y
     * al revés, sin salir de lo que se está montando.
     */
    public function saveSettings($id = null)
    {
        $form = $this->own((int) $id);
        if (!$form) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => lang('Frm.notFound')]);
        }

        $payload = $this->request->getJSON(true);
        if (!is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => lang('Frm.errBadPayload')]);
        }

        $title = trim((string) ($payload['title'] ?? $form['title']));
        $title = $title !== '' ? mb_substr($title, 0, 180) : (string) $form['title'];

        $slug = trim((string) ($payload['slug'] ?? $form['slug']));
        $slug = $this->svc->uniqueSlug($slug !== '' ? $slug : $title, (int) $form['company_id'], (int) $form['id']);

        $data = [
            'title'          => $title,
            'slug'           => $slug,
            'settings'       => $this->svc->sanitizeSettings($payload['settings'] ?? [], $form['settings'] ?? []),
            'max_responses'  => max(0, (int) ($payload['maxResponses'] ?? $form['max_responses'])),
            'allow_multiple' => !empty($payload['allowMultiple']) ? 1 : 0,
            'opens_at'       => $this->dateTime($payload['opensAt'] ?? $form['opens_at']),
            'closes_at'      => $this->dateTime($payload['closesAt'] ?? $form['closes_at']),
        ];

        // La contraseña solo se toca si viene: un campo vacío CONSERVA la que
        // hubiera, igual que los secretos de la configuración del núcleo.
        $pass = (string) ($payload['password'] ?? '');
        if ($pass !== '') {
            $data['password_hash'] = $pass === '-' ? null : password_hash($pass, PASSWORD_DEFAULT);
        }

        // `is_unique` del modelo no aplica aquí (el slug se hace único solo),
        // pero el resto de reglas sí, y el gotcha de CI4 al actualizar obliga a
        // saltárselas con el id ya resuelto.
        $this->forms->skipValidation(true);
        $this->forms->update((int) $form['id'], $data);
        $this->forms->skipValidation(false);

        return $this->response->setJSON([
            'ok'        => true,
            'title'     => $title,
            'slug'      => $slug,
            'publicUrl' => base_url('formulario/' . $form['public_token']),
            'csrf'      => csrf_hash(),
        ]);
    }

    /** Fecha y hora del formulario del navegador, o null. */
    private function dateTime($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        $v = str_replace('T', ' ', $v);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v) !== 1) {
            return null;
        }

        return strlen($v) === 16 ? $v . ':00' : $v;
    }

    /**
     * Token de CSRF fresco.
     *
     * `Config\Security::$regenerate` está en true, así que basta con que otra
     * pestaña haga un POST para que el token cargado en el editor deje de
     * valer: el guardado respondería 403 con una página HTML y todos los
     * guardados siguientes fallarían hasta recargar. El editor pide uno nuevo
     * aquí y reintenta una vez. Gotcha documentado del proyecto.
     */
    public function token()
    {
        return $this->response->setJSON(['csrf' => csrf_hash()]);
    }
}
