<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/*
 * Módulo USCIS de la firma.
 *
 * Tres cosas, en este orden: **consultar**, que es a lo que se entra; **el cupo**, que
 * es lo que gobierna el servicio; y **los enlaces**, que es lo que la firma reparte.
 *
 * Los últimos casos salen de la caché, no de USCIS: una lista que refrescara veinte
 * casos al abrirse se comería el cupo del día cada vez que alguien pasa por aquí.
 *
 * @var array      $cfg        conexión de la firma
 * @var array      $quota      used, left, limit
 * @var array      $recent     filas de la caché
 * @var array      $leadsBy    recibo => lead, para enlazar
 * @var string|null $publicUrl la página que la firma le pasa a sus clientes
 * @var array|null $result     resultado de la última consulta
 * @var bool       $canConfigure
 */
$conectado = $cfg['mode'] === 'live' && $cfg['configured'];
$esES      = str_starts_with(service('request')->getLocale(), 'es');
$caso      = $result['case'] ?? null;
$fecha     = static fn (?string $d, string $f = 'd/m/Y'): string => $d ? date($f, strtotime($d)) : '—';
?>
<div class="card mb-4">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-landmark"></i>
            <h5 class="font-semibold"><?= lang('Lfl.uscModuleTitle') ?></h5>
            <span class="badge <?= $conectado ? 'badge-success' : 'badge-warning' ?>">
                <?= lang('Lfl.uscMode_' . $cfg['mode']) ?>
            </span>
        </div>
        <?php if ($canConfigure): ?>
            <a href="<?= base_url('leadflow/configuracion/uscis') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-gear"></i> <?= lang('Lfl.uscModuleConfig') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (! $conectado): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.uscModuleOff') ?></p>
                <p class="mb-4 text-sm text-muted"><?= lang('Lfl.uscModuleOffHint') ?></p>
                <?php if ($canConfigure): ?>
                    <a href="<?= base_url('leadflow/configuracion/uscis') ?>" class="btn btn-primary btn-sm">
                        <i class="icon icon-plug"></i> <?= lang('Lfl.uscModuleConnect') ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Lfl.uscModuleHint') ?></p>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[2fr_1fr]">
                <div>
                    <?php // Consultar: lo primero, porque es a lo que se entra. ?>
                    <form action="<?= base_url('leadflow/uscis/consultar') ?>" method="post" class="flex flex-wrap items-end gap-2">
                        <?= csrf_field() ?>
                        <div class="form-group mb-0 flex-1" style="min-width:220px">
                            <label for="receipt" class="form-label"><?= lang('Lfl.uscLeadReceipt') ?></label>
                            <input type="text" id="receipt" name="receipt" class="form-input font-mono"
                                maxlength="13" autocomplete="off" spellcheck="false"
                                placeholder="EAC9999103403" value="<?= esc((string) ($result['receipt'] ?? ''), 'attr') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="icon icon-search"></i> <?= lang('Lfl.uscPubSubmit') ?>
                        </button>
                    </form>
                    <p class="form-hint"><?= lang('Lfl.uscModuleCost') ?></p>

                    <?php if ($result !== null && ! $result['ok']): ?>
                        <div class="alert alert-danger mt-4"><i class="icon icon-x-circle"></i><span><?= esc($result['message']) ?></span></div>
                    <?php endif; ?>

                    <?php if ($caso !== null): ?>
                        <div class="mt-4 rounded border border-line p-4">
                            <?php if (! empty($result['stale'])): ?>
                                <div class="alert alert-warning mb-3"><i class="icon icon-exclamation-circle"></i><span><?= esc($result['message']) ?></span></div>
                            <?php endif; ?>
                            <p class="flex flex-wrap items-center gap-2">
                                <code class="badge badge-light"><?= esc($caso['receipt']) ?></code>
                                <strong><?= esc(($esES ? ($caso['status_es'] ?? $caso['status']) : $caso['status']) ?: lang('Lfl.uscPubNoStatus')) ?></strong>
                            </p>
                            <?php if ($d = ($esES ? ($caso['detail_es'] ?? $caso['detail']) : $caso['detail'])): ?>
                                <p class="mt-1 text-sm text-muted"><?= esc($d) ?></p>
                            <?php endif; ?>
                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                                <?php foreach ([
                                    'uscPubForm'      => $caso['form_type'] ?: '—',
                                    'uscPubSubmitted' => $fecha($caso['submitted_at']),
                                    'uscPubUpdated'   => $fecha($caso['updated_at']),
                                ] as $cl => $val): ?>
                                    <div><dt class="text-muted"><?= lang('Lfl.' . $cl) ?></dt><dd class="font-medium"><?= esc($val) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                            <?php if (isset($leadsBy[$caso['receipt']])): ?>
                                <?php $l = $leadsBy[$caso['receipt']]; ?>
                                <p class="mt-3 text-sm">
                                    <?= lang('Lfl.uscModuleIsLead') ?>:
                                    <a class="font-semibold text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $l['id']) ?>">
                                        <?= esc(trim($l['first_name'] . ' ' . $l['last_name'])) ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col gap-4">
                    <?php // El cupo: el dato que de verdad gobierna este servicio. ?>
                    <div class="rounded border border-line p-4">
                        <p class="text-sm text-muted"><?= lang('Lfl.uscQuotaTitle') ?></p>
                        <p class="text-2xl font-semibold"><?= (int) $quota['used'] ?> / <?= (int) $quota['limit'] ?></p>
                        <p class="mt-1 text-xs text-muted"><?= esc(lang('Lfl.uscQuotaHint', [(int) $quota['used'], (int) $quota['limit']])) ?></p>
                    </div>

                    <?php // El enlace que la firma le pasa a sus clientes. ?>
                    <?php if ($publicUrl): ?>
                        <div class="rounded border border-line p-4">
                            <p class="mb-1 text-sm font-semibold"><?= lang('Lfl.uscModulePublic') ?></p>
                            <p class="mb-2 text-xs text-muted"><?= lang('Lfl.uscModulePublicHint') ?></p>
                            <input type="text" class="form-input font-mono text-xs" readonly
                                onclick="this.select()" value="<?= esc($publicUrl, 'attr') ?>">
                            <a href="<?= esc($publicUrl, 'attr') ?>" target="_blank" rel="noopener"
                                class="btn btn-ghost btn-sm mt-2">
                                <i class="icon icon-box-arrow-up-right"></i> <?= lang('Lfl.covSee') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php // Los últimos casos, de la CACHÉ: no se vuelve a preguntar para listar. ?>
            <h6 class="mb-2 mt-6 text-sm font-semibold"><?= lang('Lfl.uscModuleRecent') ?></h6>
            <?php if (! $recent): ?>
                <p class="rounded border border-line p-4 text-sm text-muted"><?= lang('Lfl.uscModuleNoRecent') ?></p>
            <?php else: ?>
                <div class="table-card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Lfl.uscLeadReceipt') ?></th>
                                <th><?= lang('Lfl.uscPubForm') ?></th>
                                <th><?= lang('Lfl.uscisStatusField') ?></th>
                                <th><?= lang('Lfl.uscModuleChecked') ?></th>
                                <th><?= lang('Lfl.lead') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td><code class="text-xs"><?= esc($r['receipt']) ?></code></td>
                                    <td class="text-sm"><?= esc($r['form_type'] ?: '—') ?></td>
                                    <td class="text-sm"><?= esc(($esES ? ($r['status_es'] ?: $r['status']) : $r['status']) ?: '—') ?></td>
                                    <td class="text-xs text-muted"><?= esc($fecha($r['checked_at'], 'd/m/Y H:i')) ?></td>
                                    <td class="text-sm">
                                        <?php if (isset($leadsBy[$r['receipt']])): ?>
                                            <a class="text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $leadsBy[$r['receipt']]['id']) ?>">
                                                <?= esc(trim($leadsBy[$r['receipt']]['first_name'] . ' ' . $leadsBy[$r['receipt']]['last_name'])) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
