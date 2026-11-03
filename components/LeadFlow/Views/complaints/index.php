<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/*
 * Bandeja de denuncias de toda la plataforma (solo Super Admin).
 *
 * **La firma denunciada no ve esta pantalla.** Son avisos sobre su propia conducta, y
 * entregárselos directamente convertiría el canal en un camino hacia quien lo motivó.
 * La plataforma decide qué traslada y cuándo.
 *
 * Cada denuncia se cierra como **atendida** o **descartada**, y descartar exige nota:
 * un descarte sin explicación es indistinguible de un olvido, y aquí hay que poder
 * decir qué se decidió y por qué.
 *
 * El contacto de quien denuncia se muestra solo si lo dejó: es opcional a propósito,
 * porque obligarlo dejaría fuera a quien teme represalias.
 *
 * @var array $rows
 * @var array $companies  id => fila de companies
 * @var array $profiles   id => perfil señalado
 * @var int   $pending
 */
$badges = ['pending' => 'badge-warning', 'handled' => 'badge-success', 'dismissed' => 'badge-secondary'];
?>
<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <i class="icon icon-shield-check"></i>
            <h5 class="font-semibold"><?= lang('Lfl.modTitle') ?></h5>
            <?php if ($pending > 0): ?>
                <span class="badge badge-warning"><?= esc(lang('Lfl.repPending', [(int) $pending])) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_moderation_tabs',
            ['active' => 'denuncias', 'isSuper' => true, 'pending' => $pending]) ?>
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.repTrayHint') ?></p>

        <?php if (! $rows): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.repTrayEmpty') ?></p>
                <p class="text-sm text-muted"><?= lang('Lfl.repTrayEmptyHint') ?></p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $firma  = $companies[(int) $r['company_id']] ?? null;
                    $perfil = ! empty($r['profile_id']) ? ($profiles[(int) $r['profile_id']] ?? null) : null;
                    ?>
                    <article class="card">
                        <div class="card-body">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="badge <?= $badges[$r['status']] ?? 'badge-light' ?>">
                                    <?= lang('Lfl.repStatus_' . $r['status']) ?>
                                </span>
                                <span class="badge badge-light"><?= lang('Lfl.repCat_' . $r['category']) ?></span>
                                <span class="text-xs text-muted"><?= esc(date('d/m/Y H:i', strtotime((string) $r['created_at']))) ?></span>
                            </div>

                            <p class="font-semibold">
                                <?php if ($firma): ?>
                                    <a href="<?= base_url('firma/' . rawurlencode((string) $firma['slug'])) ?>"
                                        target="_blank" rel="noopener"><?= esc($firma['name']) ?></a>
                                <?php else: ?>
                                    <?= esc(lang('Lfl.repUnknownFirm')) ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($perfil): ?>
                                <p class="text-sm text-muted">
                                    <?= lang('Lfl.repAbout') ?>:
                                    <a href="<?= base_url('abogado/' . rawurlencode((string) $perfil['slug'])) ?>"
                                        target="_blank" rel="noopener"><?= esc($perfil['display_name']) ?></a>
                                </p>
                            <?php endif; ?>

                            <p class="mt-3 whitespace-pre-line text-sm"><?= esc($r['reason']) ?></p>

                            <?php if (! empty($r['contact'])): ?>
                                <p class="mt-2 text-xs text-muted">
                                    <?= lang('Lfl.repContact') ?>: <strong><?= esc($r['contact']) ?></strong>
                                </p>
                            <?php else: ?>
                                <p class="mt-2 text-xs text-muted"><?= lang('Lfl.repNoContact') ?></p>
                            <?php endif; ?>

                            <?php if ($r['status'] === 'pending'): ?>
                                <form action="<?= base_url('leadflow/moderacion/denuncias/cerrar/' . (int) $r['id']) ?>" method="post" class="mt-4">
                                    <?= csrf_field() ?>
                                    <div class="form-group">
                                        <label class="form-label" for="note-<?= (int) $r['id'] ?>"><?= lang('Lfl.repNote') ?></label>
                                        <input type="text" id="note-<?= (int) $r['id'] ?>" name="note" maxlength="255" class="form-input">
                                        <small class="form-hint"><?= lang('Lfl.repNoteHint') ?></small>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" name="decision" value="handled" class="btn btn-primary btn-sm">
                                            <i class="icon icon-check-circle"></i> <?= lang('Lfl.repMarkHandled') ?>
                                        </button>
                                        <button type="submit" name="decision" value="dismissed" class="btn btn-ghost btn-sm">
                                            <i class="icon icon-x-circle"></i> <?= lang('Lfl.repMarkDismissed') ?>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p class="mt-3 text-xs text-muted">
                                    <?= esc(lang('Lfl.repClosedOn', [date('d/m/Y', strtotime((string) $r['handled_at']))])) ?>
                                    <?php if (! empty($r['handled_note'])): ?>
                                        — <?= esc($r['handled_note']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
