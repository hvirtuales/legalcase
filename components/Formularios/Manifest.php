<?php

/**
 * Componente Formularios — constructor de formularios personalizados.
 *
 * Porta a la plataforma la app autónoma «Formulario HV» (React + localStorage,
 * un solo formulario por navegador y sin servidor). Lo que se porta es su
 * **modelo de datos** —19 tipos de campo, páginas multi-paso, lógica
 * condicional, anchos 100/50/33—; lo que cambia es la **frontera de
 * confianza**: allí el navegador decidía y guardaba, aquí **propone y el
 * servidor decide**.
 *
 * Un formulario **es un árbol JSON**, no HTML guardado: se renderiza en cada
 * visita con `Libraries\Renderer`, única autoridad sobre la salida, y lo que
 * llega del editor no se limpia —se **reconstruye** contra `FieldRegistry`.
 *
 * Dos esquemas y no uno: `schema_json` (en edición) y `published_schema` (lo
 * que ven quienes responden), para que editar un formulario vivo no le cambie
 * las preguntas a quien lo está contestando.
 *
 * **Sin IA, y comprobado por máquina** (`spark formularios:contrato` falla si
 * aparece una referencia): un formulario recoge datos personales, quejas,
 * documentos y firmas; mandarlos a un tercero para «resumirlos» es un
 * tratamiento que nadie autorizó al responder.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 * Ver plan: proyectos/PLAN_COMPONENTE_FORMULARIOS.md
 */

return [
    'code'          => 'frm',
    'name'          => 'Formularios',
    'name_key'      => 'Frm.compName',
    'version'       => '1.0.0',
    'description'   => 'Constructor de formularios personalizados: 19 tipos de campo, páginas, lógica condicional, informe y exportación.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-list-check',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_frm_',
    'scope'         => 'company',
    // Bandera y cupos en `plans`, que añade la migración propia
    // 2026-08-30-100007_AddFormsLimitsToPlans (contrato §11).
    'plan_flag'     => 'forms_enabled',
    'plan_limits'   => [
        ['column' => 'max_forms', 'label' => 'Formularios', 'label_key' => 'Frm.limForms', 'table' => 'cmp_frm_forms'],
        ['column' => 'max_responses', 'label' => 'Respuestas', 'label_key' => 'Frm.limResponses', 'table' => 'cmp_frm_responses'],
    ],
    'permissions'   => [
        'frm.view'      => 'Ver formularios, respuestas e informes',
        'frm.create'    => 'Crear formularios y duplicarlos',
        'frm.edit'      => 'Editar el contenido de los formularios, publicarlos y cerrarlos',
        'frm.delete'    => 'Eliminar formularios, respuestas y adjuntos',
        'frm.configure' => 'Gestionar plantillas y la configuración del componente',
        'frm.export'    => 'Exportar el conjunto de respuestas (CSV / JSON)',
    ],
    'menus'         => [
        ['label' => 'Formularios', 'label_key' => 'Frm.navForms', 'icon' => 'icon-list-check', 'route' => 'formularios', 'permission' => 'frm.view', 'order' => 110],
        ['label' => 'Respuestas', 'label_key' => 'Frm.navResponses', 'icon' => 'icon-file-earmark', 'route' => 'formularios/respuestas', 'permission' => 'frm.view', 'order' => 111],
        ['label' => 'Plantillas', 'label_key' => 'Frm.navTemplates', 'icon' => 'icon-clipboard', 'route' => 'formularios/plantillas', 'permission' => 'frm.view', 'order' => 112],
        ['label' => 'Configuración', 'label_key' => 'Frm.navSettings', 'icon' => 'icon-gear', 'route' => 'formularios/configuracion', 'permission' => 'frm.configure', 'order' => 113],
    ],
    // `formulario` es el prefijo público (la URL que se comparte) y también
    // sirve los assets del editor: el núcleo nunca implementó la publicación a
    // public/components/<code>/ que menciona el contrato §2.
    'routes'        => ['formularios', 'formulario'],
    'settings'      => [],
];
