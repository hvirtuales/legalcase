<?php

/**
 * Texts for the Contabilidad (accounting) component — English.
 *
 * **Manifest labels only.** Contabilidad is the pilot component and predates
 * §9 of the contract, so its own screens still carry hard-coded Spanish;
 * translating them is a separate job. What lives here is what the PLATFORM
 * renders —the sidebar group, its items and the company dashboard quotas—,
 * which sits next to every other component and looked broken staying in
 * Spanish while the rest switched language.
 *
 * Symmetric with `Language/es/Cont.php`.
 */

return [
    // --- Manifest labels (sidebar, dashboard and plans). See ComponentManager::t().
    'compName'          => 'Accounting',
    'navPanel'          => 'Dashboard',
    'navVouchers'       => 'Vouchers',
    'navPaymentReqs'    => 'Payment requests',
    'navBeneficiaries'  => 'Payees',
    'navBankAccounts'   => 'Bank accounts',
    'navCashBoxes'      => 'Petty cash',
    'navTaxes'          => 'Taxes',
    'navPuc'            => 'Chart of accounts',
    'limVouchers'       => 'Vouchers',
    'limPaymentReqs'    => 'Payment requests',
];
