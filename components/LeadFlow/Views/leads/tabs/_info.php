<?php

use Components\LeadFlow\Libraries\Meta;

$id = (int) $lead['id'];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-person"></i><h5 class="font-semibold"><?= lang('Lfl.tabInfo') ?></h5></div>
        <?php if ($canEdit): ?>
            <a href="<?= base_url('leadflow/leads/editar/' . $id) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i> <?= lang('Lfl.edit') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php
        $blocks = [
            lang('Lfl.secIdentity') => [
                [lang('Lfl.firstName'), $lead['first_name']], [lang('Lfl.middleName'), $lead['middle_name']],
                [lang('Lfl.lastName'), $lead['last_name']],
            ],
            lang('Lfl.secContact') => [
                [lang('Lfl.email'), $lead['email']], [lang('Lfl.phone'), $lead['phone']], [lang('Lfl.whatsapp'), $lead['whatsapp']],
                [lang('Lfl.country'), Meta::countryName($lead['country'])], [lang('Lfl.city'), $lead['city']],
                [lang('Lfl.language'), $lead['preferred_language'] ? lang('Lfl.lang_' . $lead['preferred_language']) : null],
            ],
            lang('Lfl.secAttribution') => [
                [lang('Lfl.source'), $source['name'] ?? null], [lang('Lfl.campaign'), $campaign['name'] ?? null],
                ['utm_source', $lead['utm_source']], ['utm_medium', $lead['utm_medium']], ['utm_campaign', $lead['utm_campaign']],
                ['utm_content', $lead['utm_content']], ['utm_term', $lead['utm_term']],
                [lang('Lfl.landingPage'), $lead['landing_page']], [lang('Lfl.referrer'), $lead['referrer']],
            ],
            lang('Lfl.secConsent') => [
                [lang('Lfl.consentAt'), Meta::date($lead['consent_at'])], [lang('Lfl.consentIp'), $lead['consent_ip']],
            ],
        ];
        foreach ($blocks as $title => $rows): ?>
            <h6 class="mb-2 mt-4 text-sm font-semibold first:mt-0"><?= esc($title) ?></h6>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <?php foreach ($rows as [$label, $value]): ?>
                    <div class="flex justify-between gap-2 border-b border-line py-1">
                        <dt class="text-muted"><?= esc($label) ?></dt>
                        <dd class="break-all text-right"><?= esc(($value ?? '') !== '' ? (string) $value : '—') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endforeach; ?>
    </div>
</div>
