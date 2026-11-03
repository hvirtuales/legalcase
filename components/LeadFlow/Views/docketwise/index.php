<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;

?>
<?php if ($integration === null): ?>
    <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
<?php else: ?>
    <?php
    $simulated = ($integration['status'] === 'connected' ? $integration['mode'] : $provider['mode']) === 'simulated';
    $tabs      = ['conexion' => ['icon-plug', 'dwTabConnection'], 'sincronizacion' => ['icon-arrow-repeat', 'dwTabSync'],
        'conversiones' => ['icon-box-arrow-up-right', 'dwTabConversions'], 'errores' => ['icon-exclamation-circle', 'dwTabErrors']];
    ?>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h4 class="text-xl font-semibold"><?= lang('Lfl.navDocketwise') ?></h4>
            <p class="text-sm text-muted"><?= lang('Lfl.dwSubtitle') ?></p>
        </div>
        <?php if ($isSuper): ?>
            <a href="<?= base_url('leadflow/docketwise/plataforma') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-key"></i> <?= lang('Lfl.dwPlatform') ?></a>
        <?php endif; ?>
    </div>

    <?php if ($simulated): ?>
        <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Lfl.dwSimulatedNotice') ?></span></div>
    <?php endif; ?>

    <nav class="tabs mb-4">
        <?php foreach ($tabs as $key => [$icon, $label]): ?>
            <a href="<?= base_url('leadflow/docketwise?tab=' . $key) ?>" class="tab-link <?= $tab === $key ? 'active' : '' ?>"><i class="icon <?= esc($icon, 'attr') ?>"></i> <?= lang('Lfl.' . $label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'conexion'): ?>
        <?= view('Components\LeadFlow\Views\partials\_diagnostic', ['diag' => $diag ?? null]) ?>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="card">
                <div class="card-header"><i class="icon icon-plug"></i><h5 class="font-semibold"><?= lang('Lfl.dwTabConnection') ?></h5></div>
                <div class="card-body">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted"><?= lang('Lfl.status') ?></dt>
                            <dd><span class="badge <?= Meta::color(Meta::INTEGRATION_COLORS, $integration['status']) ?>"><?= esc(Meta::label(Meta::INTEGRATION_STATUSES, $integration['status'])) ?></span></dd></div>
                        <div class="flex justify-between"><dt class="text-muted"><?= lang('Lfl.mode') ?></dt>
                            <dd><span class="badge <?= $simulated ? 'badge-warning' : 'badge-success' ?>"><?= lang($simulated ? 'Lfl.modeSimulated' : 'Lfl.modeLive') ?></span></dd></div>
                        <div class="flex justify-between"><dt class="text-muted"><?= lang('Lfl.connectedAt') ?></dt><dd><?= esc(Meta::date($integration['connected_at'])) ?></dd></div>
                        <div class="flex justify-between"><dt class="text-muted"><?= lang('Lfl.tokenExpires') ?></dt><dd><?= esc(Meta::date($integration['expires_at'], false)) ?></dd></div>
                    </dl>
                    <?php if ($integration['last_error']): ?>
                        <p class="mt-3 text-xs text-danger"><?= esc($integration['last_error']) ?></p>
                    <?php endif; ?>
                    <?php if ($canConfigure): ?>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php if ($integration['status'] !== 'connected'): ?>
                                <form action="<?= base_url('leadflow/docketwise/conectar') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-plug"></i> <?= lang('Lfl.dwConnect') ?></button>
                                </form>
                            <?php else: ?>
                                <form action="<?= base_url('leadflow/docketwise/desconectar') ?>" method="post" onsubmit="return confirm('<?= esc(lang('Lfl.dwDisconnectConfirm'), 'js') ?>')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-x-circle"></i> <?= lang('Lfl.dwDisconnect') ?></button>
                                </form>
                            <?php endif; ?>
                            <?php /* Validar es solo lectura: se puede pulsar aunque la cuenta no esté conectada, y entonces dice por qué. */ ?>
                            <form action="<?= base_url('leadflow/docketwise/validar') ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm"><i class="icon icon-shield-check"></i> <?= lang('Lfl.dwValidate') ?></button>
                            </form>
                        </div>
                        <p class="mt-2 text-xs text-muted"><?= lang('Lfl.dwValidateHint') ?></p>
                    <?php endif; ?>
                    <p class="mt-3 text-xs text-muted"><?= lang('Lfl.dwConnectHint') ?></p>
                </div>
            </div>

            <div class="space-y-4 lg:col-span-2">
                <?php if ($lists['error']): ?>
                    <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i><span><?= esc($lists['error']) ?></span></div>
                <?php endif; ?>
                <?php foreach ([['users', 'dwUsers', 'icon-people'], ['matter_types', 'dwMatterTypes', 'icon-briefcase']] as [$key, $label, $icon]): ?>
                    <div class="card">
                        <div class="card-header"><i class="icon <?= esc($icon, 'attr') ?>"></i><h5 class="font-semibold"><?= lang('Lfl.' . $label) ?></h5></div>
                        <div class="card-body">
                            <?php if (! $lists[$key]): ?>
                                <p class="text-sm text-muted"><?= lang('Lfl.dwListEmpty') ?></p>
                            <?php else: ?>
                                <ul class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                                    <?php foreach ($lists[$key] as $lid => $lname): ?>
                                        <li><span class="font-mono text-xs text-muted">#<?= esc((string) $lid) ?></span> <?= esc($lname) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="text-xs text-muted"><?= lang('Lfl.dwListsHint') ?></p>
            </div>
        </div>

    <?php elseif ($tab === 'sincronizacion'): ?>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="card">
                <div class="card-header"><i class="icon icon-arrow-repeat"></i><h5 class="font-semibold"><?= lang('Lfl.dwContactsCache') ?></h5></div>
                <div class="card-body">
                    <p class="mb-3 text-xs text-muted"><?= lang('Lfl.dwContactsCacheHint') ?></p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted"><?= lang('Lfl.dwContacts') ?></dt><dd class="font-semibold"><?= (int) $contactCount ?></dd></div>
                        <div class="flex justify-between"><dt class="text-muted"><?= lang('Lfl.lastSync') ?></dt><dd><?= esc(Meta::date($integration['contacts_synced_at'])) ?></dd></div>
                    </dl>
                    <?php if ($canConvert && $integration['status'] === 'connected'): ?>
                        <form action="<?= base_url('leadflow/docketwise/contactos') ?>" method="post" class="mt-4">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-arrow-repeat"></i> <?= lang('Lfl.dwSyncNow') ?></button>
                        </form>
                    <?php endif; ?>
                    <h6 class="mb-2 mt-4 text-sm font-semibold"><?= lang('Lfl.externalIds') ?></h6>
                    <dl class="space-y-1 text-sm">
                        <?php foreach (['contact' => 'dwContact', 'matter' => 'dwMatter', 'summary' => 'dwSummaryNote', 'note' => 'dwNote', 'document' => 'dwDocument'] as $type => $label): ?>
                            <div class="flex justify-between"><dt class="text-muted"><?= lang('Lfl.' . $label) ?></dt><dd><?= (int) ($mapCounts[$type] ?? 0) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>
            <div class="card lg:col-span-2">
                <div class="card-header"><i class="icon icon-people"></i><h5 class="font-semibold"><?= lang('Lfl.dwContacts') ?></h5></div>
                <div class="card-body">
                    <?php if (! $contacts): ?>
                        <p class="text-sm text-muted"><?= lang('Lfl.dwNoContacts') ?></p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead><tr><th>#</th><th><?= lang('Lfl.name') ?></th><th><?= lang('Lfl.email') ?></th><th><?= lang('Lfl.phone') ?></th></tr></thead>
                                <tbody>
                                    <?php foreach ($contacts as $c): ?>
                                        <tr>
                                            <td class="font-mono text-xs"><?= esc($c['external_id']) ?></td>
                                            <td><?= esc(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></td>
                                            <td><?= esc($c['email_norm'] ?? '—') ?></td>
                                            <td><?= esc($c['phone_norm'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($contactCount > count($contacts)): ?>
                            <p class="mt-2 text-xs text-muted"><?= esc(lang('Lfl.dwMoreContacts', [$contactCount - count($contacts)])) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'conversiones'): ?>
        <div class="card">
            <div class="card-body">
                <?php if (! $conversions): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.noConversions') ?></p>
                <?php else: ?>
                    <div class="table-card overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= lang('Lfl.lead') ?></th>
                                    <th><?= lang('Lfl.status') ?></th>
                                    <th><?= lang('Lfl.dwContact') ?></th>
                                    <th><?= lang('Lfl.dwMatter') ?></th>
                                    <th><?= lang('Lfl.attemptsCol') ?></th>
                                    <th><?= lang('Lfl.updated') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversions as $c): ?>
                                    <tr>
                                        <td><a class="text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $c['lead_id'] . '?tab=docketwise') ?>"><?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?></a></td>
                                        <td><span class="badge <?= Meta::color(Meta::CONVERSION_COLORS, $c['status']) ?>"><?= esc(Meta::label(Meta::CONVERSION_STATUSES, $c['status'])) ?></span></td>
                                        <td class="font-mono text-xs"><?= esc($c['contact_external_id'] ? '#' . $c['contact_external_id'] : '—') ?></td>
                                        <td class="font-mono text-xs"><?= esc($c['matter_external_id'] ? '#' . $c['matter_external_id'] : '—') ?></td>
                                        <td><?= (int) $c['attempts'] ?></td>
                                        <td class="text-xs"><?= esc(Meta::date($c['updated_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <?php if ($failed): ?>
            <div class="card mb-4">
                <div class="card-header"><i class="icon icon-exclamation-circle"></i><h5 class="font-semibold"><?= lang('Lfl.dwFailedConversions') ?></h5></div>
                <div class="card-body">
                    <ul class="divide-y divide-line">
                        <?php foreach ($failed as $c): ?>
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <a class="text-sm font-medium text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $c['lead_id'] . '?tab=docketwise') ?>"><?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?></a>
                                    <div class="text-xs text-danger"><?= esc($c['last_error'] ?? '') ?></div>
                                </div>
                                <?php if ($canConvert): ?>
                                    <form action="<?= base_url('leadflow/convertir/reintentar/' . (int) $c['id']) ?>" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="leadflow/docketwise?tab=errores">
                                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-repeat"></i> <?= lang('Lfl.retry') ?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lfl.integrationLog') ?></h5></div>
            <div class="card-body">
                <?php if (! $logs): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.dwNoErrors') ?></p>
                <?php else: ?>
                    <div class="table-card overflow-x-auto">
                        <table class="table">
                            <thead><tr><th><?= lang('Lfl.date') ?></th><th><?= lang('Lfl.action') ?></th><th>HTTP</th><th><?= lang('Lfl.message') ?></th><th><?= lang('Lfl.lead') ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-xs"><?= esc(Meta::date($log['created_at'])) ?></td>
                                        <td class="font-mono text-xs"><?= esc($log['action']) ?></td>
                                        <td><?= esc((string) ($log['http_code'] ?? '—')) ?></td>
                                        <td class="text-xs"><?= esc($log['message'] ?? '') ?></td>
                                        <td><?php if ($log['lead_id']): ?><a class="text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $log['lead_id'] . '?tab=docketwise') ?>">#<?= (int) $log['lead_id'] ?></a><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
