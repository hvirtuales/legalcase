<?php

/**
 * Componente Mailer — envío de correos masivos (tipo Mailchimp).
 *
 * Listas de contactos, plantillas y campañas por **Amazon SES**, con validación
 * anti-spam, lista de supresión, control de rebotes y seguimiento de aperturas
 * y clics.
 *
 * El envío lo hace `Libraries\SesMailer` —del componente, no el `SesService`
 * del núcleo—: hace falta `SendRawEmail` para poder mandar la cabecera
 * `List-Unsubscribe`, que es lo que exigen Gmail y Yahoo a los remitentes
 * masivos. El HTML del correo lo produce el SERVIDOR (`EmailRenderer`) a partir
 * del árbol de bloques, nunca el navegador.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'mailer',
    'name'          => 'Correos masivos',
    'name_key'      => 'Mailer.compName',
    'version'       => '2.1.0',
    'description'   => 'Campañas de correo masivo por Amazon SES: listas de contactos, plantillas, validación anti-spam, bajas y control de rebotes.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-envelope',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_mailer_',
    'scope'         => 'company',
    // Bandera y cupo en `plans`, que añade la migración propia
    // AddMailerLimitsToPlans. Sin ella, `!empty($plan['mailer_enabled'])` sobre
    // una clave ausente da false y el servicio quedaba APAGADO para toda
    // empresa con plan.
    'plan_flag'     => 'mailer_enabled',
    'permissions'   => [
        'mailer.view'   => 'Ver correos masivos',
        'mailer.create' => 'Crear listas/plantillas/campañas',
        'mailer.edit'   => 'Editar listas/plantillas/campañas',
        'mailer.delete' => 'Eliminar registros de correos masivos',
        'mailer.send'   => 'Enviar campañas de correo',
    ],
    'menus'         => [
        ['label' => 'Campañas', 'label_key' => 'Mailer.navCampaigns', 'icon' => 'icon-send', 'route' => 'mailer/campanas', 'permission' => 'mailer.view', 'order' => 80],
        ['label' => 'Listas de contactos', 'label_key' => 'Mailer.navLists', 'icon' => 'icon-people', 'route' => 'mailer/listas', 'permission' => 'mailer.view', 'order' => 82],
        ['label' => 'Plantillas', 'label_key' => 'Mailer.navTemplates', 'icon' => 'icon-clipboard', 'route' => 'mailer/plantillas', 'permission' => 'mailer.view', 'order' => 84],
        ['label' => 'Bajas y rebotes', 'label_key' => 'Mailer.navSuppressions', 'icon' => 'icon-shield-check', 'route' => 'mailer/supresiones', 'permission' => 'mailer.view', 'order' => 86],
    ],
    // `mailer-publico` es el prefijo público: la baja, el alta por formulario,
    // la confirmación y «ver el correo en el navegador».
    'routes'        => ['mailer', 'mailer-publico'],
    'settings'      => [],
];
