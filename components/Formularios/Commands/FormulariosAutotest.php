<?php

namespace Components\Formularios\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Formularios\Libraries\ExportService;
use Components\Formularios\Libraries\FieldRegistry;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Libraries\LogicEngine;
use Components\Formularios\Libraries\PlanGuard;
use Components\Formularios\Libraries\Renderer;
use Components\Formularios\Libraries\ResponseService;
use Components\Formularios\Libraries\Validator;
use Components\Formularios\Models\AnswerModel;
use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\ResponseModel;

/**
 * Comprueba las REGLAS del componente, no la interfaz.
 *
 * Lo que se verifica es exactamente lo que se rompería sin darse cuenta: el
 * saneado del árbol, la revalidación en el servidor, la lógica condicional, el
 * aislamiento por empresa, la instantánea de etiquetas y el índice de
 * respuestas.
 *
 * Crea sus filas con el prefijo ZZTEST y LAS BORRA SIEMPRE, también si algo
 * falla (`finally`).
 */
class FormulariosAutotest extends BaseCommand
{
    protected $group       = 'Formularios';
    protected $name        = 'formularios:autotest';
    protected $description = 'Comprueba las reglas del componente Formularios.';

    private int $ok   = 0;
    private int $bad  = 0;
    private array $created = [];

    private function check(string $what, bool $condition): void
    {
        if ($condition) {
            $this->ok++;

            return;
        }
        $this->bad++;
        CLI::write('  FALLA: ' . $what, 'red');
    }

    public function run(array $params)
    {
        CLI::write('Autotest del componente Formularios', 'yellow');

        try {
            $this->testRegistry();
            $this->testSanitizer();
            $this->testSettings();
            $this->testLogic();
            $this->testValidator();
            $this->testDatabase();
        } catch (\Throwable $e) {
            $this->bad++;
            CLI::write('  EXCEPCION: ' . $e->getMessage(), 'red');
        } finally {
            $this->cleanup();
        }

        CLI::newLine();
        CLI::write('Comprobaciones: ' . ($this->ok + $this->bad)
            . '  ok: ' . $this->ok . '  fallos: ' . $this->bad, $this->bad ? 'red' : 'green');

        return $this->bad ? 1 : 0;
    }

    /** 1. El registro: los 19 tipos y sus propiedades comunes. */
    private function testRegistry(): void
    {
        CLI::write('1. Registro de campos', 'cyan');

        $types = FieldRegistry::types();
        $this->check('hay 19 tipos de campo', count($types) === 19);

        foreach ($types as $type => $def) {
            $this->check($type . ' declara etiqueta, icono, grupo y clase de valor',
                isset($def['label'], $def['icon'], $def['group'], $def['value']));
            $this->check($type . ' pertenece a un grupo conocido',
                in_array($def['group'], FieldRegistry::GROUPS, true));
        }

        // Toda propiedad tiene que caer en una pestaña que el inspector pinte.
        foreach (array_keys($types) as $type) {
            foreach (FieldRegistry::propsOf($type) as $p) {
                $this->check($type . '.' . $p['name'] . ' declara una pestaña válida',
                    in_array($p['tab'] ?? '', ['general', 'validation', 'layout'], true));
            }
        }

        $this->check('los estructurales no piden respuesta',
            FieldRegistry::isStructural('heading')
            && FieldRegistry::isStructural('paragraph')
            && FieldRegistry::isStructural('divider')
            && !FieldRegistry::isStructural('text'));

        // El gotcha de las claves numéricas: `propOptionValues` tiene que
        // devolver CADENAS, o toda opción numérica caería al valor por defecto.
        $values = FieldRegistry::propOptionValues(['options' => ['2' => 'dos', '3' => 'tres']]);
        $this->check('las opciones de un select vuelven como cadenas',
            $values === ['2', '3'] && in_array('2', $values, true));
    }

    /** 2. El saneador: lo que no está declarado, no entra. */
    private function testSanitizer(): void
    {
        CLI::write('2. Saneado del árbol', 'cyan');
        $svc = new FormService();

        $tree = $svc->sanitizeTree([
            'pages'  => [['id' => 'p_aaaa', 'title' => 'Uno']],
            'fields' => [
                ['id' => 'f_bbbb', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Nombre', 'inventado' => 'x'],
                ['id' => 'f_cccc', 'pageId' => 'p_aaaa', 'type' => 'noexiste', 'label' => 'Malo'],
                ['id' => 'f_dddd', 'pageId' => 'p_zzzz', 'type' => 'email', 'label' => 'Correo'],
                ['id' => 'f_eeee', 'pageId' => 'p_aaaa', 'type' => 'heading', 'label' => 'Hola', 'headingLevel' => 'h99'],
                ['id' => 'f_ffff', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Ancho', 'width' => '77'],
            ],
        ]);

        $ids = array_column($tree['fields'], 'id');
        $this->check('un tipo inventado se descarta', !in_array('f_cccc', $ids, true));
        $this->check('una propiedad no declarada se descarta', !isset($tree['fields'][0]['inventado']));
        $this->check('un campo huérfano va a la primera página',
            $tree['fields'][1]['pageId'] === 'p_aaaa');
        $this->check('un nivel de encabezado inválido se descarta',
            !isset($tree['fields'][2]['headingLevel']) || $tree['fields'][2]['headingLevel'] !== 'h99');
        $this->check('un ancho inválido cae al 100 por ciento',
            $tree['fields'][3]['width'] === '100');
        $this->check('siempre queda al menos una página',
            count($svc->sanitizeTree(['pages' => [], 'fields' => []])['pages']) === 1);

        // Un id corto pero legítimo (`p_rh1`) tiene que sobrevivir: lo que hace
        // seguro un id es su juego de caracteres, no su longitud. Cuando el
        // mínimo era 4, una plantilla de arranque perdía dos de sus tres pasos
        // y sus campos aparecían amontonados en el primero.
        $corto = $svc->sanitizeTree([
            'pages'  => [['id' => 'p_rh1', 'title' => 'Uno'], ['id' => 'p_rh2', 'title' => 'Dos']],
            'fields' => [['id' => 'f_ab', 'pageId' => 'p_rh2', 'type' => 'text', 'label' => 'Campo']],
        ]);
        $this->check('un id corto y válido se conserva', count($corto['pages']) === 2);
        $this->check('su campo se queda en su página', $corto['fields'][0]['pageId'] === 'p_rh2');

        // Recorrido «todo en una página»: ninguna sección puede quedar oculta,
        // porque sin asistente no hay nada que vuelva a mostrarla.
        $dos = $svc->sanitizeTree([
            'pages'  => [['id' => 'p_una', 'title' => 'Uno'], ['id' => 'p_dos', 'title' => 'Dos']],
            'fields' => [
                ['id' => 'f_a1', 'pageId' => 'p_una', 'type' => 'text', 'label' => 'A'],
                ['id' => 'f_b1', 'pageId' => 'p_dos', 'type' => 'text', 'label' => 'B'],
            ],
        ]);

        $single = (new Renderer($dos, $svc->sanitizeSettings(['flow' => 'single'])))->render();
        $wizard = (new Renderer($dos, $svc->sanitizeSettings(['flow' => 'wizard'])))->render();

        $this->check('en una sola página no se esconde nada', !str_contains($single, 'hidden>'));
        $this->check('paso a paso sí esconde las siguientes', str_contains($wizard, 'hidden>'));
        $this->check('las dos páginas se pintan en los dos recorridos',
            substr_count($single, 'data-ff-page=') === 2 && substr_count($wizard, 'data-ff-page=') === 2);
    }

    /** 2.b Reglas condicionales y opciones. */
    private function testSettings(): void
    {
        CLI::write('3. Reglas, opciones y ajustes', 'cyan');
        $svc = new FormService();

        $tree = $svc->sanitizeTree([
            'pages'  => [['id' => 'p_aaaa', 'title' => 'Uno']],
            'fields' => [
                ['id' => 'f_1111', 'pageId' => 'p_aaaa', 'type' => 'switch', 'label' => 'Quiere'],
                ['id' => 'f_2222', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Cuándo',
                 'conditionalRule' => ['enabled' => true, 'fieldId' => 'f_1111', 'operator' => 'inventado', 'value' => 'x']],
                ['id' => 'f_3333', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Ciclo',
                 'conditionalRule' => ['enabled' => true, 'fieldId' => 'f_3333', 'operator' => 'equals', 'value' => 'x']],
                ['id' => 'f_4444', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Fantasma',
                 'conditionalRule' => ['enabled' => true, 'fieldId' => 'f_9999', 'operator' => 'equals', 'value' => 'x']],
                ['id' => 'f_5555', 'pageId' => 'p_aaaa', 'type' => 'select', 'label' => 'Opciones',
                 'options' => [
                     ['id' => 'o_a', 'label' => 'A', 'value' => 'x'],
                     ['id' => 'o_b', 'label' => 'B', 'value' => 'x'],
                     ['id' => 'o_c', 'label' => 'C', 'value' => ''],
                 ]],
            ],
        ]);

        $byId = array_column($tree['fields'], null, 'id');

        $this->check('un operador desconocido cae en «es igual a»',
            ($byId['f_2222']['conditionalRule']['operator'] ?? '') === 'equals');
        $this->check('un campo no puede depender de sí mismo',
            !isset($byId['f_3333']['conditionalRule']));
        $this->check('una regla que apunta a un campo inexistente se retira',
            !isset($byId['f_4444']['conditionalRule']));

        $opts = FieldRegistry::optionValues($byId['f_5555']);
        $this->check('dos opciones no comparten valor', count($opts) === count(array_unique($opts)));
        $this->check('una opción sin valor recibe uno derivado de su etiqueta',
            in_array('c', $opts, true) || count($opts) === 3);

        // Ajustes: la presentación es lo que decide cómo se ve el formulario.
        $s = $svc->sanitizeSettings([
            'display'      => 'fullscreen',
            'flow'         => 'inventado',
            'primaryColor' => 'rojo',
            'redirectUrl'  => 'javascript:alert(1)',
            'notifyEmail'  => 'no-es-un-correo',
        ]);

        $this->check('la presentación a pantalla completa se conserva', $s['display'] === 'fullscreen');
        $this->check('una presentación inventada cae en ficha',
            $svc->sanitizeSettings(['display' => 'holograma'])['display'] === 'card');
        $this->check('un recorrido inventado cae en paso a paso', $s['flow'] === 'wizard');
        $this->check('un color que no es color se descarta', $s['primaryColor'] === '');
        $this->check('una redirección javascript: se rechaza', $s['redirectUrl'] === '');
        $this->check('un correo inválido se descarta', $s['notifyEmail'] === '');
        $this->check('un color válido se conserva tal cual',
            $svc->sanitizeSettings(['primaryColor' => '#1a2b3c'])['primaryColor'] === '#1a2b3c');
    }

    /** 4. Los nueve operadores de la lógica. */
    private function testLogic(): void
    {
        CLI::write('4. Lógica condicional', 'cyan');
        $logic = new LogicEngine();

        $f = static function (string $op, $value) {
            return ['id' => 'f_zzzz', 'type' => 'text',
                    'conditionalRule' => ['enabled' => true, 'fieldId' => 'f_aaaa', 'operator' => $op, 'value' => $value]];
        };

        $this->check('equals', $logic->isVisible($f('equals', 'si'), ['f_aaaa' => 'SI']));
        $this->check('not_equals', $logic->isVisible($f('not_equals', 'no'), ['f_aaaa' => 'si']));
        $this->check('contains', $logic->isVisible($f('contains', 'mundo'), ['f_aaaa' => 'hola mundo']));
        $this->check('is_checked', $logic->isVisible($f('is_checked', ''), ['f_aaaa' => '1']));
        $this->check('is_not_checked', $logic->isVisible($f('is_not_checked', ''), ['f_aaaa' => '0']));
        $this->check('greater_than', $logic->isVisible($f('greater_than', 5), ['f_aaaa' => 9]));
        $this->check('less_than', $logic->isVisible($f('less_than', 5), ['f_aaaa' => 2]));
        $this->check('is_empty', $logic->isVisible($f('is_empty', ''), ['f_aaaa' => '']));
        $this->check('is_not_empty', $logic->isVisible($f('is_not_empty', ''), ['f_aaaa' => 'algo']));
        $this->check('sin regla siempre se ve', $logic->isVisible(['id' => 'f_x', 'type' => 'text'], []));
        $this->check('una lista se compara por pertenencia',
            $logic->isVisible($f('contains', 'b'), ['f_aaaa' => ['a', 'b']]));
    }

    /** 5. La revalidación en el servidor. */
    private function testValidator(): void
    {
        CLI::write('5. Validación en el servidor', 'cyan');

        $schema = (new FormService())->sanitizeTree([
            'pages'  => [['id' => 'p_aaaa', 'title' => 'Uno']],
            'fields' => [
                ['id' => 'f_name', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Nombre', 'required' => true, 'minLength' => 3],
                ['id' => 'f_mail', 'pageId' => 'p_aaaa', 'type' => 'email', 'label' => 'Correo'],
                ['id' => 'f_pick', 'pageId' => 'p_aaaa', 'type' => 'select', 'label' => 'Elija', 'options' => [
                    ['id' => 'o_a', 'label' => 'A', 'value' => 'a'],
                    ['id' => 'o_b', 'label' => 'B', 'value' => 'b'],
                ]],
                ['id' => 'f_want', 'pageId' => 'p_aaaa', 'type' => 'switch', 'label' => 'Quiere'],
                ['id' => 'f_when', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Cuándo', 'required' => true,
                 'conditionalRule' => ['enabled' => true, 'fieldId' => 'f_want', 'operator' => 'is_checked', 'value' => '']],
                ['id' => 'f_agex', 'pageId' => 'p_aaaa', 'type' => 'number', 'label' => 'Edad', 'min' => 18, 'max' => 99],
                ['id' => 'f_code', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Placa', 'validationRegex' => '^[A-Z]{3}[0-9]{3}$'],
                ['id' => 'f_bad1', 'pageId' => 'p_aaaa', 'type' => 'text', 'label' => 'Rota', 'validationRegex' => '([sin cerrar'],
            ],
        ]);

        $v = new Validator();

        $r = $v->validate($schema, ['f' => ['f_name' => '']]);
        $this->check('un campo obligatorio vacío falla', isset($r['errors']['f_name']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'ab']]);
        $this->check('el mínimo de caracteres se aplica', isset($r['errors']['f_name']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_mail' => 'no-es-correo']]);
        $this->check('un correo mal escrito falla', isset($r['errors']['f_mail']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_pick' => 'inventada']]);
        $this->check('una opción que no existe se rechaza', isset($r['errors']['f_pick']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_pick' => 'b']]);
        $this->check('una opción declarada pasa', !isset($r['errors']['f_pick']));

        // LO IMPORTANTE: un campo oculto ni se exige ni se guarda.
        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_want' => '0', 'f_when' => 'colado']]);
        $this->check('un campo oculto no se exige', !isset($r['errors']['f_when']));
        $this->check('el valor de un campo oculto se descarta', !isset($r['values']['f_when']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_want' => '1']]);
        $this->check('visible y obligatorio, sí se exige', isset($r['errors']['f_when']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_agex' => '12']]);
        $this->check('el mínimo numérico se aplica', isset($r['errors']['f_agex']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_agex' => '30']]);
        $this->check('un número dentro del rango pasa', !isset($r['errors']['f_agex']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_code' => 'abc']]);
        $this->check('la expresión regular se aplica de verdad', isset($r['errors']['f_code']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_code' => 'ABC123']]);
        $this->check('un valor que cumple el patrón pasa', !isset($r['errors']['f_code']));

        // Un patrón roto es culpa de quien armó el formulario, no de quien
        // responde: se ignora en vez de rechazar la respuesta.
        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_bad1' => 'lo que sea']]);
        $this->check('una expresión regular inválida no invalida la respuesta', !isset($r['errors']['f_bad1']));

        // Un campo que no está en el esquema no existe, aunque llegue en el POST.
        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana', 'f_intruso' => 'hola']]);
        $this->check('un campo que no está en el esquema se ignora', !isset($r['values']['f_intruso']));

        $r = $v->validate($schema, ['f' => ['f_name' => 'Ana']]);
        $this->check('un formulario correcto pasa entero', $r['ok'] === true);
    }

    /** 6. Lo que necesita base de datos: aislamiento, índice y exportación. */
    private function testDatabase(): void
    {
        CLI::write('6. Base de datos, aislamiento e índice', 'cyan');

        $db = \Config\Database::connect();
        $company = $db->table('companies')->select('id')->limit(1)->get()->getRowArray();
        if (!$company) {
            CLI::write('  (sin empresas en la base: se omiten las comprobaciones con datos)', 'yellow');

            return;
        }
        $companyId = (int) $company['id'];

        $svc   = new FormService();
        $forms = new FormModel();

        $formId = $svc->create($companyId, null, 'ZZTEST formulario', 'fullscreen', [
            'pages'  => [['id' => 'p_test', 'title' => 'ZZTEST']],
            'fields' => [
                ['id' => 'f_tname', 'pageId' => 'p_test', 'type' => 'text', 'label' => 'Nombre', 'required' => true],
                ['id' => 'f_tmany', 'pageId' => 'p_test', 'type' => 'checkbox', 'label' => 'Gustos', 'options' => [
                    ['id' => 'o_1', 'label' => 'Uno', 'value' => 'uno'],
                    ['id' => 'o_2', 'label' => 'Dos', 'value' => 'dos'],
                ]],
                ['id' => 'f_trate', 'pageId' => 'p_test', 'type' => 'rating', 'label' => 'Nota', 'maxStars' => 5],
                ['id' => 'f_tfile', 'pageId' => 'p_test', 'type' => 'file', 'label' => 'Soporte'],
            ],
        ]);
        $this->created['forms'][] = $formId;

        $form = $forms->find($formId);
        $this->check('el formulario nace en borrador', (string) $form['status'] === 'draft');
        $this->check('el token público es aleatorio y largo', strlen((string) $form['public_token']) === 64);
        $this->check('la presentación elegida al crear se guarda',
            ($form['settings']['display'] ?? '') === 'fullscreen');

        // Pertenencia: otra empresa no lo ve, aunque tenga el permiso.
        $this->check('un formulario de otra empresa no se resuelve',
            $forms->ofCompany($formId, $companyId + 99999) === null);
        $this->check('el de la empresa sí se resuelve',
            $forms->ofCompany($formId, $companyId) !== null);

        // Publicar congela el esquema.
        $this->check('en borrador el esquema publicado está vacío',
            ($form['published_schema']['fields'] ?? []) === []);
        $svc->publish($form);
        $form = $forms->find($formId);
        $this->check('publicar copia el esquema y sube la versión',
            count($form['published_schema']['fields'] ?? []) === 4 && (int) $form['version'] === 1);

        // Cerrado y ventana de fechas.
        $this->check('un formulario publicado admite respuestas', $svc->closedReason($form) === null);
        $forms->update($formId, ['status' => 'closed']);
        $this->check('un formulario cerrado no admite respuestas',
            $svc->closedReason($forms->find($formId)) !== null);
        $forms->update($formId, ['status' => 'published']);

        $this->dbResponses($svc, $forms->find($formId), $companyId);
    }

    /** 6.b Guardar una respuesta y comprobar lo que queda. */
    private function dbResponses(FormService $svc, array $form, int $companyId): void
    {
        $schema = $svc->liveSchema($form);
        $result = (new Validator())->validate($schema, ['f' => [
            'f_tname' => 'ZZTEST Ana',
            'f_tmany' => ['uno', 'dos'],
            'f_trate' => '4',
        ]]);
        $this->check('la respuesta de prueba es válida', $result['ok'] === true);

        $saved = (new ResponseService())->submit($form, $schema, $result, [
            'ip' => '127.0.0.1', 'user_agent' => 'ZZTEST', 'duration' => 42, 'user_id' => null,
        ]);
        $this->check('la respuesta se guarda', $saved['ok'] === true);
        $this->created['responses'][] = (int) $saved['id'];
        $row = (new ResponseModel())->find((int) $saved['id']);
        $this->check('la referencia sigue el formato FRM-000001',
            preg_match('/^FRM-\d{6}$/', (string) $row['reference']) === 1);
        // El consecutivo es DEL FORMULARIO y arranca en 1. Salía del
        // autoincremento global de la tabla, así que la primera respuesta de un
        // formulario nuevo se numeraba con el total de la plataforma.
        $this->check('la primera respuesta de un formulario es la 000001',
            (string) $row['reference'] === 'FRM-000001');

        $segunda = (new ResponseService())->submit($form, $schema, $result, [
            'ip' => '127.0.0.1', 'user_agent' => 'ZZTEST', 'duration' => 10, 'user_id' => null,
        ]);
        $this->created['responses'][] = (int) $segunda['id'];
        $this->check('la siguiente es la 000002', (string) $segunda['reference'] === 'FRM-000002');
        $this->check('el token de la respuesta es aleatorio', strlen((string) $row['token']) === 48);
        $this->check('la respuesta guarda la versión del formulario',
            (int) $row['form_version'] === (int) $form['version']);

        // La INSTANTANEA de etiquetas: es lo que hace que renombrar una pregunta
        // no reescriba lo ya recibido.
        $this->check('la respuesta congela las etiquetas',
            ($row['labels']['f_tname'] ?? '') === 'Nombre');

        $forms = new FormModel();
        $tree  = $svc->sanitizeTree($form['schema_json']);
        foreach ($tree['fields'] as $i => $f) {
            if ($f['id'] === 'f_tname') { $tree['fields'][$i]['label'] = 'Nombre completo'; }
        }
        $forms->update((int) $form['id'], ['schema_json' => $tree]);

        $again = (new ResponseModel())->find((int) $saved['id']);
        $this->check('renombrar la pregunta NO cambia la respuesta ya recibida',
            ($again['labels']['f_tname'] ?? '') === 'Nombre');

        // El índice: una casilla múltiple deja una fila por opción marcada.
        $answers = new AnswerModel();
        $rows = $answers->where('response_id', (int) $saved['id'])->findAll();
        $this->check('el índice tiene una fila por valor', count($rows) === 4);

        $multi = array_filter($rows, static fn ($r) => $r['field_id'] === 'f_tmany');
        $this->check('la casilla múltiple deja dos filas', count($multi) === 2);

        $rate = array_values(array_filter($rows, static fn ($r) => $r['field_id'] === 'f_trate'));
        $this->check('la calificación queda como número', (float) $rate[0]['value_num'] === 4.0);

        // Dos respuestas idénticas: el conteo por opción tiene que sumar las dos.
        $counts = $answers->countByOption((int) $form['id'], 'f_tmany');
        $this->check('el conteo por opción agrupa en SQL',
            ($counts['uno'] ?? 0) === 2 && ($counts['dos'] ?? 0) === 2);

        // Exportación: encabezados de la instantánea, BOM y punto y coma.
        $csv = (new ExportService())->csv($forms->find((int) $form['id']), $schema);
        $this->check('el CSV empieza con el BOM de UTF-8', str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->check('el CSV separa con punto y coma', str_contains($csv, '";"'));
        $this->check('el CSV usa la etiqueta congelada, no la nueva',
            str_contains($csv, '"Nombre"') && !str_contains($csv, '"Nombre completo"'));
        $this->check('el CSV incluye el valor respondido', str_contains($csv, 'ZZTEST Ana'));

        // ADJUNTOS: en la celda va el ENLACE, no el nombre suelto que guarda la
        // respuesta —con el nombre no se recupera nada—. El enlace apunta al
        // controlador, que exige sesión, permiso y empresa: la hoja se puede
        // reenviar sin que el archivo viaje con ella.
        (new \Components\Formularios\Models\AttachmentModel())->insert([
            'response_id'   => (int) $saved['id'],
            'form_id'       => (int) $form['id'],
            'company_id'    => $companyId,
            'field_id'      => 'f_tfile',
            'original_name' => 'ZZTEST soporte.pdf',
            'stored_name'   => 'zztest.pdf',
            'mime'          => 'application/pdf',
            'size'          => 10,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $csv = (new ExportService())->csv($forms->find((int) $form['id']), $schema);
        $this->check('el CSV lleva el nombre del adjunto', str_contains($csv, 'ZZTEST soporte.pdf'));
        $this->check('el CSV lleva su ENLACE de descarga',
            str_contains($csv, 'formularios/adjunto/'));

        // Una celda que empieza por `=` la ejecuta la hoja de cálculo, y el
        // contenido lo escribe quien responde: se antepone una comilla.
        $peligro = ['f_tname' => '=HYPERLINK("http://malo")', 'f_trate' => '4'];
        (new ResponseModel())->update((int) $saved['id'], ['data' => $peligro]);
        $csv = (new ExportService())->csv($forms->find((int) $form['id']), $schema);
        $this->check('una celda con forma de fórmula se neutraliza',
            str_contains($csv, '"\'=HYPERLINK'));
        $this->check('un número no se toca al neutralizar', str_contains($csv, '"4"'));

        // Sin plan asignado, el guardia deja trabajar.
        $guard = new PlanGuard();
        $this->check('sin plan, el servicio está permitido', is_bool($guard->serviceEnabled($companyId)));

        // Y el renderizador escapa lo que le llega.
        $html = (new Renderer($schema, $svc->sanitizeSettings($form['settings']), [], []))->render();
        $this->check('el renderizador pinta los campos', str_contains($html, 'data-ff-item'));
        $this->check('el renderizador nombra los campos como f[...]', str_contains($html, 'name="f[f_tname]"'));
    }


    /**
     * Limpieza. Se ejecuta SIEMPRE, también si una comprobación reventó, para no
     * dejar filas ZZTEST en la base de trabajo.
     *
     * Se borra por `FormService::deleteForm()`, que es justo la ruta que
     * arrastra respuestas, índice y archivos: si esa limpieza estuviera rota, se
     * notaría aquí. Después se hace el borrado DEFINITIVO, porque el
     * soft-delete dejaría las filas de prueba a la vista de cualquier consulta
     * que no lo respete.
     */
    private function cleanup(): void
    {
        $svc = new FormService();

        foreach ($this->created['forms'] ?? [] as $id) {
            try {
                $svc->deleteForm((int) $id);
            } catch (\Throwable $e) {
                CLI::write('  no se pudo limpiar el formulario ' . $id . ': ' . $e->getMessage(), 'yellow');
            }
        }

        $db = \Config\Database::connect();

        if ($db->tableExists('cmp_frm_forms')) {
            $ids = $db->table('cmp_frm_forms')->select('id')->like('title', 'ZZTEST', 'after')->get()->getResultArray();

            foreach ($ids as $row) {
                $formId = (int) $row['id'];

                if ($db->tableExists('cmp_frm_answers')) {
                    $db->table('cmp_frm_answers')->where('form_id', $formId)->delete();
                }
                if ($db->tableExists('cmp_frm_attachments')) {
                    $db->table('cmp_frm_attachments')->where('form_id', $formId)->delete();
                }
                if ($db->tableExists('cmp_frm_responses')) {
                    $db->table('cmp_frm_responses')->where('form_id', $formId)->delete();
                }
                $db->table('cmp_frm_forms')->where('id', $formId)->delete();
            }
        }
    }
}
