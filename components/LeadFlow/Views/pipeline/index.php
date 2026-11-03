<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;

/*
 * El Kanban (§10). Arrastrar una tarjeta a otra columna la mueve de etapa por
 * `POST leadflow/pipeline/mover`; el servidor aplica las mismas reglas que la ficha
 * (a «Convertido» solo se llega convirtiendo; los estados alternativos piden
 * motivo) y devuelve el token CSRF nuevo, que el tablero repone antes del
 * siguiente movimiento.
 */
$now     = date('Y-m-d H:i:s');
// Sin empresa activa el controlador no manda filtros: la vista no puede darlos por hechos.
$filtros = array_filter($filters ?? [], static fn ($v) => $v !== '' && $v !== null);
?>
<?php if ($columns === null): ?>
    <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
<?php elseif ($access->scope === 'none'): ?>
    <?= view('Components\LeadFlow\Views\partials\_noaccess') ?>
<?php else: ?>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h4 class="text-xl font-semibold"><?= lang('Lfl.navPipeline') ?></h4>
            <p class="text-sm text-muted"><?= lang($canMove ? 'Lfl.pipeHint' : 'Lfl.pipeReadOnly') ?></p>
        </div>
        <a href="<?= base_url('leadflow/leads') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-list"></i> <?= lang('Lfl.viewList') ?></a>
    </div>

    <details class="card mb-4" <?= $filtros ? 'open' : '' ?>>
        <summary class="card-header cursor-pointer"><i class="icon icon-funnel"></i><span class="font-semibold"><?= lang('Lfl.filters') ?></span>
            <?php if ($filtros): ?><span class="badge badge-primary"><?= count($filtros) ?></span><?php endif; ?></summary>
        <form method="get" action="<?= base_url('leadflow/pipeline') ?>" class="card-body">
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php
                $selects = [
                    ['asesor', lang('Lfl.advisor'), $team], ['abogado', lang('Lfl.lawyer'), $team],
                    ['servicio', lang('Lfl.service'), $services], ['fuente', lang('Lfl.source'), $sources],
                    ['campana', lang('Lfl.campaign'), $campaigns], ['prioridad', lang('Lfl.priority'), Meta::options(Meta::PRIORITIES)],
                ];
                foreach ($selects as [$name, $label, $opts]): ?>
                    <div class="form-group">
                        <label for="p-<?= esc($name, 'attr') ?>" class="form-label text-xs"><?= esc($label) ?></label>
                        <select id="p-<?= esc($name, 'attr') ?>" name="<?= esc($name, 'attr') ?>" class="form-select">
                            <option value=""><?= lang('Lfl.all') ?></option>
                            <?php foreach ($opts as $val => $lbl): ?>
                                <option value="<?= esc((string) $val, 'attr') ?>" <?= (string) $filters[$name] === (string) $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div class="form-group">
                    <label for="p-score" class="form-label text-xs"><?= lang('Lfl.scoreMin') ?></label>
                    <input type="number" id="p-score" name="score_min" min="0" max="100" class="form-input" value="<?= esc((string) $filters['score_min'], 'attr') ?>">
                </div>
                <div class="form-group">
                    <label for="p-desde" class="form-label text-xs"><?= lang('Lfl.createdFrom') ?></label>
                    <input type="date" id="p-desde" name="desde" class="form-input" value="<?= esc($filters['desde'], 'attr') ?>">
                </div>
                <div class="form-group">
                    <label for="p-hasta" class="form-label text-xs"><?= lang('Lfl.createdTo') ?></label>
                    <input type="date" id="p-hasta" name="hasta" class="form-input" value="<?= esc($filters['hasta'], 'attr') ?>">
                </div>
            </div>
            <div class="mb-3 flex flex-wrap gap-4">
                <?php foreach (['sin_actividad' => 'filterNoActivity', 'vencidas' => 'filterOverdue', 'alt' => 'filterAlt'] as $name => $label): ?>
                    <label class="form-check">
                        <input type="checkbox" name="<?= esc($name, 'attr') ?>" value="1" class="form-check-input" <?= $filters[$name] ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= lang('Lfl.' . $label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-funnel"></i> <?= lang('Lfl.filter') ?></button>
                <a href="<?= base_url('leadflow/pipeline') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-x-lg"></i> <?= lang('Lfl.clear') ?></a>
            </div>
        </form>
    </details>

    <div class="overflow-x-auto pb-2">
        <div class="flex min-h-[24rem] gap-3" data-board>
            <?php foreach ($columns as $col): ?>
                <?php $s = $col['stage']; ?>
                <section class="flex w-72 shrink-0 flex-col rounded-lg border border-line bg-surface-muted/60"
                    data-column data-stage-id="<?= (int) $s['id'] ?>" data-kind="<?= esc($s['kind'], 'attr') ?>">
                    <header class="flex items-center justify-between gap-2 border-b border-line px-3 py-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold"><?= esc($s['name']) ?></div>
                            <div class="text-xs text-muted"><?= (int) $s['probability'] ?> %</div>
                        </div>
                        <span class="badge <?= esc(Meta::STAGE_BADGES[$s['color']] ?? 'badge-light', 'attr') ?>" data-count><?= (int) $col['total'] ?></span>
                    </header>
                    <div class="flex flex-1 flex-col gap-2 p-2" data-list>
                        <?php foreach ($col['leads'] as $l): ?>
                            <?php
                            $late    = $l['next_activity_at'] && $l['next_activity_at'] < $now;
                            $idle    = $l['status'] === 'open' && ! $l['next_activity_at'];
                            ?>
                            <article class="card cursor-grab p-3 text-sm" data-card data-lead-id="<?= (int) $l['id'] ?>" <?= $canMove ? 'draggable="true"' : '' ?>>
                                <div class="flex items-start justify-between gap-2">
                                    <a href="<?= base_url('leadflow/leads/ficha/' . (int) $l['id']) ?>" class="font-semibold text-primary hover:underline"><?= esc(LeadModel::fullName($l)) ?></a>
                                    <?= Meta::scoreBadge((int) $l['score'], $l['priority']) ?>
                                </div>
                                <div class="mt-1 truncate text-xs text-muted"><?= esc($services[(int) $l['service_id']] ?? lang('Lfl.noService')) ?></div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-muted">
                                    <span title="<?= esc(lang('Lfl.owner'), 'attr') ?>"><i class="icon icon-person"></i> <?= esc($l['assigned_user_id'] ? ($team[(int) $l['assigned_user_id']] ?? '—') : lang('Lfl.unassigned')) ?></span>
                                    <span title="<?= esc(lang('Lfl.timeInStage'), 'attr') ?>"><i class="icon icon-clock"></i> <?= esc(Meta::since($l['stage_entered_at'])) ?></span>
                                </div>
                                <?php if ($l['next_activity_at'] || $idle): ?>
                                    <div class="mt-1 text-xs <?= $late ? 'text-danger' : ($idle ? 'text-warning' : 'text-muted') ?>">
                                        <?php if ($late): ?>
                                            <i class="icon icon-exclamation-circle"></i> <?= esc(lang('Lfl.overdueSince', [Meta::date($l['next_activity_at'])])) ?>
                                        <?php elseif ($idle): ?>
                                            <i class="icon icon-exclamation-circle"></i> <?= lang('Lfl.noNextActivity') ?>
                                        <?php else: ?>
                                            <i class="icon icon-list-check"></i> <?= esc(Meta::date($l['next_activity_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($col['total'] > count($col['leads'])): ?>
                            <a href="<?= base_url('leadflow/leads?etapa=' . (int) $s['id']) ?>" class="text-center text-xs text-primary hover:underline">
                                <?= esc(lang('Lfl.pipeMore', [$col['total'] - count($col['leads'])])) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($canMove): ?>
        <dialog class="m-auto w-full max-w-md rounded-lg p-0 backdrop:bg-black/40" data-lost-dialog>
            <form method="dialog" class="card border-0 shadow-none">
                <div class="card-header"><i class="icon icon-x-circle"></i><h5 class="font-semibold"><?= lang('Lfl.lostDialogTitle') ?></h5></div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="dlg-reason" class="form-label"><?= lang('Lfl.lostReason') ?> <span class="text-danger">*</span></label>
                        <select id="dlg-reason" class="form-select" data-reason>
                            <option value=""><?= lang('Lfl.choose') ?></option>
                            <?php foreach (Meta::options(Meta::LOST_REASONS) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dlg-note" class="form-label"><?= lang('Lfl.lostNote') ?></label>
                        <input type="text" id="dlg-note" maxlength="255" class="form-input" data-note>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button value="cancel" class="btn btn-secondary btn-sm"><?= lang('Lfl.cancel') ?></button>
                        <button value="ok" class="btn btn-primary btn-sm"><?= lang('Lfl.move') ?></button>
                    </div>
                </div>
            </form>
        </dialog>
        <?= csrf_field() ?>

        <script>
        (function () {
            var board = document.querySelector('[data-board]');
            if (!board) { return; }
            var url = <?= json_encode(base_url('leadflow/pipeline/mover')) ?>;
            var msgConverted = <?= json_encode(lang('Lfl.errConvertManual')) ?>;
            var msgError = <?= json_encode(lang('Lfl.pipeMoveError')) ?>;
            var dialog = document.querySelector('[data-lost-dialog]');
            var dragged = null;

            var token = function () {
                var el = document.querySelector('input[name="<?= csrf_token() ?>"]');
                return el ? el.value : '';
            };
            // El token rota en cada POST: se repone en TODOS los campos de la página.
            var setToken = function (t) {
                if (!t) { return; }
                document.querySelectorAll('input[name="<?= csrf_token() ?>"]').forEach(function (el) { el.value = t; });
            };
            var recount = function () {
                board.querySelectorAll('[data-column]').forEach(function (col) {
                    var badge = col.querySelector('[data-count]');
                    if (badge) { badge.textContent = col.querySelectorAll('[data-card]').length; }
                });
            };

            var send = function (card, column, reason, note) {
                var from = card.parentNode;
                column.querySelector('[data-list]').prepend(card);
                recount();

                var body = new FormData();
                body.append('lead_id', card.getAttribute('data-lead-id'));
                body.append('stage_id', column.getAttribute('data-stage-id'));
                body.append('reason', reason || '');
                body.append('note', note || '');
                body.append('<?= csrf_token() ?>', token());

                fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token() }, credentials: 'same-origin' })
                    .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return null; } }); })
                    .then(function (res) {
                        if (!res) { window.location.reload(); return; }
                        setToken(res.csrf);
                        if (!res.ok) {
                            from.prepend(card);
                            recount();
                            window.alert(res.message || msgError);
                        }
                    })
                    .catch(function () { from.prepend(card); recount(); window.alert(msgError); });
            };

            board.addEventListener('dragstart', function (e) {
                var card = e.target.closest('[data-card]');
                if (!card) { return; }
                dragged = card;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.getAttribute('data-lead-id'));
                card.classList.add('opacity-50');
            });
            board.addEventListener('dragend', function () {
                if (dragged) { dragged.classList.remove('opacity-50'); }
                board.querySelectorAll('[data-column]').forEach(function (c) { c.classList.remove('ring-2', 'ring-primary'); });
            });
            board.addEventListener('dragover', function (e) {
                var col = e.target.closest('[data-column]');
                if (!col || !dragged) { return; }
                e.preventDefault();
                col.classList.add('ring-2', 'ring-primary');
            });
            board.addEventListener('dragleave', function (e) {
                var col = e.target.closest('[data-column]');
                if (col && !col.contains(e.relatedTarget)) { col.classList.remove('ring-2', 'ring-primary'); }
            });
            board.addEventListener('drop', function (e) {
                var col = e.target.closest('[data-column]');
                if (!col || !dragged) { return; }
                e.preventDefault();
                col.classList.remove('ring-2', 'ring-primary');
                var card = dragged;
                dragged = null;
                if (card.parentNode === col.querySelector('[data-list]')) { return; }

                var kind = col.getAttribute('data-kind');
                if (kind === 'converted') { window.alert(msgConverted); return; }
                if (kind !== 'lost' || !dialog || !dialog.showModal) { send(card, col); return; }

                var reason = dialog.querySelector('[data-reason]');
                var note = dialog.querySelector('[data-note]');
                reason.value = '';
                note.value = '';
                dialog.onclose = function () {
                    if (dialog.returnValue === 'ok' && reason.value) { send(card, col, reason.value, note.value); }
                };
                dialog.showModal();
            });
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
