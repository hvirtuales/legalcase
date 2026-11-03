<?php

/**
 * Textos del componente Contabilidad (español).
 *
 * **Solo los rótulos del Manifest.** Contabilidad es el componente piloto y es
 * anterior al §9 del contrato, así que sus pantallas siguen con el texto
 * incrustado en español: traducirlas enteras es un trabajo aparte. Lo que hay
 * aquí es lo que la PLATAFORMA pinta —el grupo del menú lateral, sus ítems y
 * los cupos del panel de empresa—, que se ve junto al de los demás componentes
 * y desentonaba al quedarse en español mientras el resto cambiaba de idioma.
 *
 * Simétrico con `Language/en/Cont.php`.
 */

return [
    // --- Rótulos del Manifest (menú lateral, panel y planes). Ver ComponentManager::t().
    'compName'          => 'Contabilidad',
    'navPanel'          => 'Panel',
    'navVouchers'       => 'Comprobantes',
    'navPaymentReqs'    => 'Cuentas de cobro',
    'navBeneficiaries'  => 'Beneficiarios',
    'navBankAccounts'   => 'Cuentas bancarias',
    'navCashBoxes'      => 'Cajas menores',
    'navTaxes'          => 'Impuestos',
    'navPuc'            => 'PUC',
    'limVouchers'       => 'Comprobantes',
    'limPaymentReqs'    => 'Cuentas de cobro',
];
