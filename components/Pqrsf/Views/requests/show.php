<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * La gestión de una solicitud.
 *
 * Izquierda: lo que pasó (solicitud, hilo, adjuntos). Derecha: lo que se hace
 * (asignar, estado, IA y responder). La caja de responder es la única que envía
 * al ciudadano, y solo con los canales que la ley y sus autorizaciones permiten:
 * `$channels` ya trae el motivo cuando uno está bloqueado.
 *
 * @var array  $row @var array $thread @var array $points @var array $agents
 * @var array  $types [código => nombre] @var array $channels @var array $attachments
 * @var array  $answerLinks @var string $ticketUrl @var bool $aiReady
 */
$statuses = \Components\Pqrsf\Models\RequestModel::STATUSES;
$id       = (int) $row['id'];
$late     = $row['due_at'] && $row['due_at'] < date('Y-m-d H:i:s') && in_array($row['status'], ['recibida', 'en_gestion'], true);

$tone = [
    'recibida'   => 'badge-warning',
    'en_gestion' => 'badge-info',
    'respondida' => 'badge-success',
    'cerrada'    => 'badge-secondary',
    'anulada'    => 'badge-danger',
][$row['status']] ?? 'badge-secondary';

$evIcon = [
    'estado'     => 'icon-arrow-repeat',
    'asignacion' => 'icon-person-badge',
    'nota'       => 'icon-journal-text',
    'respuesta'  => 'icon-send',
    'envio'      => 'icon-share',
    'adjunto'    => 'icon-paperclip',
];
?>

<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <div class="flex items-center gap-3">
        <a href="<?= base_url('pqrsf') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i></a>
        <div>
            <h4 class="flex items-center gap-2 font-bold">
                <?= esc($row['number']) ?>
                <span class="badge <?= $tone ?>"><?= lang('Pqr.status_' . $row['status']) ?></span>
                <?php if ($late): ?><span class="badge badge-danger"><?= lang('Pqr.late') ?></span><?php endif; ?>
            </h4>
            <p class="text-xs text-muted">
                <?= esc($types[$row['type']] ?? $row['type']) ?> ·
                <?= esc($points[$row['point_id']] ?? lang('Pqr.pointGone')) ?> ·
                <?= esc(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?>
            </p>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="<?= esc($ticketUrl) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
            <i class="icon icon-external-link"></i> <?= lang('Pqr.openTicket') ?>
        </a>
        <?= view('partials/delete_button', [
            'action'  => base_url('pqrsf/eliminar/' . $id),
            'label'   => lang('Pqr.delete'),
            'text'    => lang('Pqr.delete'),
            'confirm' => lang('Pqr.deleteConfirm', [$row['number']]),
        ]) ?>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <!-- ------------------------------------------------- lo que pasó -->
    <div class="space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <i class="icon icon-chat"></i>
                <h5 class="font-semibold"><?= esc($row['subject']) ?></h5>
            </div>
            <div class="card-body">
                <p class="whitespace-pre-line text-sm"><?= esc($row['message']) ?></p>

                <?php
                $aiActions = $row['ai_actions'] ? (json_decode((string) $row['ai_actions'], true) ?: []) : [];
                $hasAi     = !empty($row['ai_summary']) || !empty($row['ai_category']) || !empty($row['ai_analysis']) || $aiActions;
                ?>
                <?php if ($hasAi): ?>
                    <!-- Lo que propuso la IA. Son sugerencias: no cambian la prioridad
                         ni la respuesta, que las decide una persona. -->
                    <div class="mt-4 rounded-md border border-line bg-surface-muted p-3">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-muted">
                            <i class="icon icon-sparkles text-primary"></i> <?= lang('Pqr.aiAnalysis') ?>
                            <?php if (!empty($row['ai_category'])): ?>
                                <span class="badge badge-info"><?= esc($row['ai_category']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['ai_priority'])): ?>
                                <span class="badge badge-warning"><?= lang('Pqr.priority_' . $row['ai_priority']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($row['ai_summary'])): ?>
                            <p class="text-sm"><?= esc($row['ai_summary']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($row['ai_analysis'])): ?>
                            <p class="mt-2 whitespace-pre-line text-sm text-muted"><?= esc($row['ai_analysis']) ?></p>
                        <?php endif; ?>

                        <?php if ($aiActions): ?>
                            <ul class="mt-2 list-inside list-disc text-sm">
                                <?php foreach ($aiActions as $a): ?>
                                    <li><?= esc((string) $a) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($row['ai_priority']) && $row['ai_priority'] !== $row['priority']): ?>
                            <form method="post" action="<?= base_url('pqrsf/prioridad/' . $id) ?>" class="mt-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="priority" value="<?= esc($row['ai_priority'], 'attr') ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">
                                    <i class="icon icon-check-circle"></i>
                                    <?= lang('Pqr.aiApplyPriority', [lang('Pqr.priority_' . $row['ai_priority'])]) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <hr class="my-4 border-t border-line">

                <?php if (!empty($row['is_anonymous'])): ?>
                    <p class="flex items-center gap-2 text-sm text-muted">
                        <i class="icon icon-eye-slash"></i> <?= lang('Pqr.anonymousNote') ?>
                    </p>
                <?php else: ?>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-muted"><?= lang('Pqr.fName') ?></dt><dd><?= esc($row['citizen_name'] ?: '—') ?></dd></div>
                        <div><dt class="text-xs text-muted"><?= lang('Pqr.fDoc') ?></dt><dd><?= esc($row['citizen_doc'] ?: '—') ?></dd></div>
                        <div><dt class="text-xs text-muted"><?= lang('Pqr.fEmail') ?></dt><dd><?= esc($row['citizen_email'] ?: '—') ?></dd></div>
                        <div><dt class="text-xs text-muted"><?= lang('Pqr.fPhone') ?></dt><dd><?= esc($row['citizen_phone'] ?: '—') ?></dd></div>
                        <div><dt class="text-xs text-muted"><?= lang('Pqr.fCity') ?></dt><dd><?= esc($row['citizen_city'] ?: '—') ?></dd></div>
                    </dl>
                <?php endif; ?>

                <!-- El sello del consentimiento: es lo que se enseña si alguien reclama. -->
                <div class="mt-4 rounded-md border border-line p-3 text-xs text-muted">
                    <div class="mb-1 flex items-center gap-2 font-semibold">
                        <i class="icon icon-shield-check"></i> <?= lang('Pqr.consentTitle') ?>
                    </div>
                    <?= lang('Pqr.consentTermsShort') ?>: <strong><?= $row['accept_terms'] ? lang('Pqr.yes') : lang('Pqr.no') ?></strong> ·
                    <?= lang('Pqr.consentDataShort') ?>: <strong><?= $row['consent_data'] ? lang('Pqr.yes') : lang('Pqr.no') ?></strong> ·
                    <?= lang('Pqr.consentCommsShort') ?>: <strong><?= $row['consent_comms'] ? lang('Pqr.yes') : lang('Pqr.no') ?></strong>
                    <div class="mt-1">
                        <?= esc($row['consent_at'] ?: '—') ?> · IP <?= esc($row['consent_ip'] ?: '—') ?> ·
                        <?= lang('Pqr.policyVersion') ?> <?= esc($row['policy_version'] ?: 'v1') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($row['answer'])): ?>
            <div class="card border-success">
                <div class="card-header">
                    <i class="icon icon-check-circle text-success"></i>
                    <h5 class="font-semibold"><?= lang('Pqr.answerGiven') ?></h5>
                </div>
                <div class="card-body">
                    <p class="whitespace-pre-line text-sm"><?= esc($row['answer']) ?></p>

                    <?php if ($answerLinks): ?>
                        <ul class="mt-3 space-y-1">
                            <?php foreach ($answerLinks as $l): ?>
                                <li class="flex items-center gap-2 text-sm">
                                    <i class="icon icon-link text-muted"></i>
                                    <a href="<?= esc($l['url']) ?>" target="_blank" rel="noopener" class="truncate text-primary"><?= esc($l['label']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <p class="mt-2 text-xs text-muted"><?= esc($row['answered_at']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Adjuntos -->
        <div class="card">
            <div class="card-header">
                <i class="icon icon-paperclip"></i>
                <h5 class="font-semibold"><?= lang('Pqr.attachments') ?> (<?= count($attachments) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!$attachments): ?>
                    <p class="text-sm text-muted"><?= lang('Pqr.attNone') ?></p>
                <?php else: ?>
                    <ul class="mb-4 space-y-2">
                        <?php foreach ($attachments as $a): ?>
                            <?php $shared = !empty($a['is_public']); ?>
                            <li class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm <?= $shared ? 'border-primary' : 'border-line' ?>">
                                <a href="<?= base_url('pqrsf/adjunto/ver/' . (int) $a['id']) ?>" class="flex min-w-0 items-center gap-2 text-primary">
                                    <i class="icon icon-download"></i>
                                    <span class="truncate"><?= esc($a['name']) ?></span>
                                    <?php if ($shared): ?>
                                        <span class="badge badge-success shrink-0"><?= lang('Pqr.attPublic') ?></span>
                                    <?php endif; ?>
                                </a>
                                <span class="flex shrink-0 items-center gap-2">
                                    <span class="text-xs text-muted"><?= number_format((int) $a['size'] / 1024, 0) ?> KB</span>
                                    <!-- Compartirlo es lo que lo hace descargable desde el ticket. -->
                                    <form method="post" action="<?= base_url('pqrsf/adjunto/publicar/' . (int) $a['id']) ?>" class="inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-sm"
                                            title="<?= esc(lang($shared ? 'Pqr.attUnshare' : 'Pqr.attShare'), 'attr') ?>">
                                            <i class="icon <?= $shared ? 'icon-eye-slash' : 'icon-eye' ?>"></i>
                                        </button>
                                    </form>
                                    <?= view('partials/delete_button', [
                                        'action'  => base_url('pqrsf/adjunto/eliminar/' . (int) $a['id']),
                                        'label'   => lang('Pqr.delete'),
                                        'confirm' => lang('Pqr.attConfirm'),
                                    ]) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?= base_url('pqrsf/adjunto/' . $id) ?>" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                    <?= csrf_field() ?>
                    <div class="form-group mb-0 grow">
                        <label for="att" class="form-label text-xs"><?= lang('Pqr.attAdd') ?></label>
                        <input type="file" id="att" name="file" class="form-input" required>
                    </div>
                    <button type="submit" class="btn btn-secondary"><i class="icon icon-upload"></i> <?= lang('Pqr.attUpload') ?></button>
                </form>
                <p class="mt-2 text-xs text-muted"><?= lang('Pqr.attPrivateNote') ?></p>
            </div>
        </div>

        <!-- Hilo -->
        <div class="card">
            <div class="card-header">
                <i class="icon icon-clock-history"></i>
                <h5 class="font-semibold"><?= lang('Pqr.thread') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!$thread): ?>
                    <p class="text-sm text-muted"><?= lang('Pqr.ticketNoEvents') ?></p>
                <?php else: ?>
                    <ol class="space-y-3">
                        <?php foreach (array_reverse($thread) as $e): ?>
                            <li class="flex gap-3 border-l-2 border-line pl-3">
                                <i class="icon <?= $evIcon[$e['type']] ?? 'icon-circle' ?> mt-1 text-muted"></i>
                                <div class="min-w-0">
                                    <p class="whitespace-pre-line text-sm"><?= esc((string) $e['body']) ?></p>
                                    <p class="text-xs text-muted">
                                        <?= esc(date('Y-m-d H:i', strtotime((string) $e['created_at']))) ?>
                                        <?= trim((string) $e['user_name']) !== '' ? ' · ' . esc($e['user_name']) : '' ?>
                                        <?php if (!empty($e['is_private'])): ?>
                                            · <span class="badge badge-light"><?= lang('Pqr.private') ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <form method="post" action="<?= base_url('pqrsf/nota/' . $id) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="body" class="form-label text-xs"><?= lang('Pqr.noteAdd') ?></label>
                        <textarea id="body" name="body" rows="3" class="form-input" required></textarea>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="public" value="1" class="form-check-input">
                            <?= lang('Pqr.notePublic') ?>
                        </label>
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-send"></i> <?= lang('Pqr.noteSave') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------- lo que se hace -->
    <div class="space-y-4">
        <div class="card">
            <div class="card-header">
                <i class="icon icon-person-gear"></i>
                <h5 class="font-semibold"><?= lang('Pqr.manage') ?></h5>
            </div>
            <div class="card-body space-y-4">
                <form method="post" action="<?= base_url('pqrsf/asignar/' . $id) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="assignee_id" class="form-label text-xs"><?= lang('Pqr.assignee') ?></label>
                        <select id="assignee_id" name="assignee_id" class="form-select">
                            <option value=""><?= lang('Pqr.unassigned') ?></option>
                            <?php foreach ($agents as $aid => $name): ?>
                                <option value="<?= (int) $aid ?>" <?= (int) $row['assignee_id'] === (int) $aid ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm btn-block"><?= lang('Pqr.assign') ?></button>
                </form>

                <form method="post" action="<?= base_url('pqrsf/estado/' . $id) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="status" class="form-label text-xs"><?= lang('Pqr.fStatus') ?></label>
                        <select id="status" name="status" class="form-select">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= lang('Pqr.status_' . $s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm btn-block"><?= lang('Pqr.statusChange') ?></button>
                </form>

                <div class="rounded-md border border-line p-3 text-xs">
                    <div class="text-muted"><?= lang('Pqr.due') ?></div>
                    <div class="<?= $late ? 'font-semibold text-danger' : '' ?>">
                        <?= esc($row['due_at'] ? date('Y-m-d', strtotime((string) $row['due_at'])) : '—') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- IA: propone, no responde -->
        <div class="card">
            <div class="card-header">
                <i class="icon icon-sparkles"></i>
                <h5 class="font-semibold"><?= lang('Pqr.aiDraft') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!$aiReady): ?>
                    <p class="text-sm text-muted"><?= lang('Pqr.aiOff') ?></p>
                <?php else: ?>
                    <?php if (!empty($row['ai_draft'])): ?>
                        <p id="aiDraft" class="mb-3 whitespace-pre-line rounded-md border border-line bg-surface-muted p-3 text-sm"><?= esc($row['ai_draft']) ?></p>
                        <button type="button" class="btn btn-secondary btn-sm btn-block" id="useDraft">
                            <i class="icon icon-arrow-down-circle"></i> <?= lang('Pqr.aiUse') ?>
                        </button>
                    <?php else: ?>
                        <p class="mb-3 text-sm text-muted"><?= lang('Pqr.aiNone') ?></p>
                    <?php endif; ?>

                    <form method="post" action="<?= base_url('pqrsf/ia/' . $id) ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="instructions" class="form-label text-xs"><?= lang('Pqr.aiInstructions') ?></label>
                            <input type="text" id="instructions" name="instructions" class="form-input"
                                placeholder="<?= esc(lang('Pqr.aiInstructionsHint'), 'attr') ?>">
                        </div>
                        <button type="submit" class="btn btn-ghost btn-sm btn-block"><i class="icon icon-arrow-repeat"></i> <?= lang('Pqr.aiRegenerate') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Responder: lo único que sale hacia el ciudadano -->
        <div class="card">
            <div class="card-header">
                <i class="icon icon-send"></i>
                <h5 class="font-semibold"><?= lang('Pqr.answerTitle') ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('pqrsf/responder/' . $id) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="answer" class="form-label text-xs"><?= lang('Pqr.answerLabel') ?></label>
                        <textarea id="answer" name="answer" rows="7" class="form-input" required><?= esc($row['answer'] ?: '') ?></textarea>
                    </div>

                    <!-- Lo que acompaña a la respuesta: un archivo, un enlace o los dos.
                         Van al ticket del ciudadano y a los mensajes que se le envíen. -->
                    <fieldset class="form-section">
                        <legend class="form-section-title"><?= lang('Pqr.answerAttach') ?></legend>
                        <p class="form-section-hint"><?= lang('Pqr.answerAttachHint') ?></p>

                        <div class="form-group">
                            <label for="answer_file" class="form-label text-xs"><?= lang('Pqr.answerFile') ?></label>
                            <input type="file" id="answer_file" name="answer_file" class="form-input">
                            <small class="form-hint"><?= lang('Pqr.answerFileHint') ?></small>
                        </div>

                        <div class="form-group">
                            <label for="answer_link" class="form-label text-xs"><?= lang('Pqr.answerLink') ?></label>
                            <input type="url" id="answer_link" name="answer_link" class="form-input" maxlength="500"
                                placeholder="https://…" value="<?= esc($row['answer_link'] ?? '', 'attr') ?>">
                        </div>

                        <div class="form-group mb-0">
                            <label for="answer_link_label" class="form-label text-xs"><?= lang('Pqr.answerLinkLabel') ?></label>
                            <input type="text" id="answer_link_label" name="answer_link_label" class="form-input" maxlength="150"
                                placeholder="<?= esc(lang('Pqr.answerLinkLabelPh'), 'attr') ?>"
                                value="<?= esc($row['answer_link_label'] ?? '', 'attr') ?>">
                        </div>
                    </fieldset>

                    <p class="mb-2 mt-4 text-xs font-semibold text-muted"><?= lang('Pqr.answerChannels') ?></p>
                    <?php foreach ($channels as $key => $ch): ?>
                        <label class="mb-2 flex items-start gap-2 text-sm <?= $ch['available'] ? '' : 'opacity-60' ?>">
                            <input type="checkbox" name="channels[]" value="<?= $key ?>" class="form-check-input"
                                <?= $ch['available'] ? 'checked' : 'disabled' ?>>
                            <span>
                                <?= lang('Pqr.ch_' . $key) ?>
                                <?php if ($ch['available']): ?>
                                    <span class="block text-xs text-muted"><?= esc($ch['to']) ?></span>
                                <?php else: ?>
                                    <span class="block text-xs text-danger"><?= esc($ch['reason']) ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>

                    <p class="mb-3 mt-2 text-xs text-muted"><?= lang('Pqr.answerAlways') ?></p>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="icon icon-check-circle"></i> <?= lang('Pqr.answerSend') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // «Usar el borrador»: lo copia al cuadro de respuesta, no lo envía.
    var use = document.getElementById('useDraft');
    if (!use) { return; }

    use.addEventListener('click', function () {
        var box = document.getElementById('answer');
        box.value = document.getElementById('aiDraft').textContent.trim();
        box.focus();
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}());
</script>
<?= $this->endSection() ?>
