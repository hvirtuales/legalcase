<?php

/**
 * Componente Contabilidad — comprobantes contables y cuentas de cobro.
 * Piloto: módulo Beneficiarios / Terceros (CRUD por empresa).
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'cont',
    'name'          => 'Contabilidad',
    'name_key'      => 'Cont.compName',
    'version'       => '1.0.0',
    'description'   => 'Comprobantes contables y cuentas de cobro. Piloto: Beneficiarios/Terceros.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-calculator', // icono del grupo en el sidebar
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_cont_',
    'plan_flag'     => 'service_enabled', // columna booleana de `plans` que habilita el servicio (oculta el menú si el plan de la empresa activa la tiene en 0)
    'plan_limits'   => [
        ['column' => 'max_vouchers', 'label' => 'Comprobantes', 'label_key' => 'Cont.limVouchers', 'table' => 'cmp_cont_vouchers'],
        ['column' => 'max_payment_requests', 'label' => 'Cuentas de cobro', 'label_key' => 'Cont.limPaymentReqs', 'table' => 'cmp_cont_payment_requests'],
    ],
    'permissions'   => [
        'cont.view'   => 'Ver contabilidad (beneficiarios)',
        'cont.create' => 'Crear registros de contabilidad',
        'cont.edit'   => 'Editar registros de contabilidad',
        'cont.delete' => 'Eliminar registros de contabilidad',
    ],
    'menus'         => [
        ['label' => 'Panel', 'label_key' => 'Cont.navPanel', 'icon' => 'icon-bar-chart', 'route' => 'contabilidad/panel', 'permission' => 'cont.view', 'order' => 50],
        ['label' => 'Comprobantes', 'label_key' => 'Cont.navVouchers', 'icon' => 'icon-receipt', 'route' => 'contabilidad/comprobantes', 'permission' => 'cont.view', 'order' => 55],
        ['label' => 'Cuentas de cobro', 'label_key' => 'Cont.navPaymentReqs', 'icon' => 'icon-cash-stack', 'route' => 'contabilidad/cuentas-cobro', 'permission' => 'cont.view', 'order' => 58],
        ['label' => 'Beneficiarios', 'label_key' => 'Cont.navBeneficiaries', 'icon' => 'icon-people', 'route' => 'contabilidad/beneficiarios', 'permission' => 'cont.view', 'order' => 60],
        ['label' => 'Cuentas bancarias', 'label_key' => 'Cont.navBankAccounts', 'icon' => 'icon-landmark', 'route' => 'contabilidad/cuentas-bancarias', 'permission' => 'cont.view', 'order' => 62],
        ['label' => 'Cajas menores', 'label_key' => 'Cont.navCashBoxes', 'icon' => 'icon-wallet', 'route' => 'contabilidad/cajas', 'permission' => 'cont.view', 'order' => 63],
        ['label' => 'Impuestos', 'label_key' => 'Cont.navTaxes', 'icon' => 'icon-percent', 'route' => 'contabilidad/impuestos', 'permission' => 'cont.view', 'order' => 65],
        ['label' => 'PUC', 'label_key' => 'Cont.navPuc', 'icon' => 'icon-diagram-3', 'route' => 'contabilidad/puc', 'permission' => 'cont.view', 'order' => 70],
    ],
    'routes'        => ['contabilidad'],
    'settings'      => [],
];
