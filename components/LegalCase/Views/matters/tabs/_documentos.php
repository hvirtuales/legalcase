<?php
/**
 * Documentos y solicitudes al cliente. La clasificación decide si un documento llega al
 * portal: solo «Compartido». Lo que sube el cliente entra «En revisión».
 *
 * @var array $matter @var array $documents @var array $requests @var array $users
 * @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\DocumentService;
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\DocumentModel;

$id       = (int) $matter['id'];
$canShare = ($canWork && $acc->can('lgc.share'));
// Versiones anteriores agrupadas por el documento al que siguen.
$prevOf = [];
foreach ($versions as $v) {
    $prevOf[(int) $v['id']] = $v;
}
$open = $matter['status'] !== 'closed';
$size = static fn (int $b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
?>
<div class="grid gap-4 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-folder"></i><h5 class="font-semibold"><?= lang('Lgc.documents') ?></h5></div>
            <div class="card-body">
                <?php if (!$documents): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noDocuments') ?></p>
                <?php else: ?>
                    <div class="table-card overflow-x-auto">
                        <table class="table">
                            <thead><tr><th><?= lang('Lgc.fName') ?></th><th><?= lang('Lgc.fClassification') ?></th><th><?= lang('Lgc.fUploaded') ?></th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td>
                                        <?php if ($d['classification'] === 'privileged' && !$insider): ?>
                                            <span class="font-semibold text-muted"><i class="icon icon-lock"></i> <?= lang('Lgc.privilegedDoc') ?></span>
                                        <?php else: ?>
                                            <a class="font-semibold text-primary" href="<?= base_url('legalcase/documentos/descargar/' . (int) $d['id']) ?>"><i class="icon icon-file-earmark"></i> <?= esc($d['name']) ?></a>
                                        <?php endif; ?>
                                        <div class="text-xs text-muted"><?= esc($size((int) $d['size'])) ?> · <span class="font-mono" title="SHA-256"><?= esc(substr((string) $d['sha256'], 0, 12)) ?>…</span>
                                            <?php if ((int) ($d['version'] ?? 1) > 1): ?> · <span class="badge badge-light">v<?= (int) $d['version'] ?></span><?php endif; ?></div>
                                        <?php if ((int) ($d['version'] ?? 1) > 1 && ($d['classification'] !== 'privileged' || $insider)): ?>
                                            <details class="mt-1 text-xs"><summary class="cursor-pointer text-muted"><?= lang('Lgc.versionHistory') ?></summary>
                                                <ul class="mt-1 space-y-1">
                                                    <?php $p = $prevOf[(int) $d['previous_id']] ?? null; $guard = 0; while ($p && $guard++ < 20): ?>
                                                        <li><a class="text-primary" href="<?= base_url('legalcase/documentos/descargar/' . (int) $p['id']) ?>">v<?= (int) $p['version'] ?></a> · <?= esc(Meta::date($p['created_at'])) ?></li>
                                                        <?php $p = $prevOf[(int) $p['previous_id']] ?? null; endwhile; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                        <?php if (($canWork && $acc->can('lgc.create')) && ($d['classification'] !== 'privileged' || $insider)): ?>
                                            <details class="mt-1 text-xs"><summary class="cursor-pointer text-primary"><?= lang('Lgc.newDocVersion') ?></summary>
                                                <form method="post" action="<?= base_url('legalcase/documentos/version/' . (int) $d['id']) ?>" enctype="multipart/form-data" class="mt-1 flex flex-wrap gap-1"><?= csrf_field() ?>
                                                    <input type="file" name="file" required class="form-input" aria-label="<?= esc(lang('Lgc.newDocVersion'), 'attr') ?>">
                                                    <button class="btn btn-secondary btn-sm"><i class="icon icon-upload"></i> <?= lang('Lgc.upload') ?></button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                        <?php if ($canShare && $d['classification'] === 'shared' && $accounts): ?>
                                            <details class="mt-1 text-xs"><summary class="cursor-pointer text-primary"><i class="icon icon-pencil-square"></i> <?= lang('Lgc.requestSignature') ?></summary>
                                                <form method="post" action="<?= base_url('legalcase/documentos/firma/' . (int) $d['id']) ?>" class="mt-1 grid gap-1"><?= csrf_field() ?>
                                                    <select name="account_id" class="form-select" aria-label="<?= esc(lang('Lgc.fRecipient'), 'attr') ?>">
                                                        <?php foreach ($accounts as $aid => $mail): ?><option value="<?= (int) $aid ?>"><?= esc($mail) ?></option><?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="purpose" class="form-input" maxlength="190" placeholder="<?= esc(lang('Lgc.phSignPurpose'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.fSignPurpose'), 'attr') ?>">
                                                    <button class="btn btn-secondary btn-sm"><i class="icon icon-send"></i> <?= lang('Lgc.send') ?></button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($canWork && $acc->can('lgc.edit')) && ($d['classification'] !== 'privileged' || $insider)): ?>
                                            <?php $priv = $d['classification'] === 'privileged'; ?>
                                            <form method="post" action="<?= base_url('legalcase/documentos/clasificar/' . (int) $d['id']) ?>"><?= csrf_field() ?>
                                                <input type="hidden" name="confirm_privileged" value="0">
                                                <select name="classification" class="form-select" aria-label="<?= esc(lang('Lgc.fClassification'), 'attr') ?>"
                                                    onchange="if (this.value !== 'shared' || confirm('<?= esc(lang($priv ? 'Lgc.confirmSharePrivileged' : 'Lgc.confirmShare'), 'js') ?>')) { this.form.confirm_privileged.value = '1'; this.form.submit(); } else { this.value = '<?= esc($d['classification'], 'js') ?>'; }">
                                                    <?php foreach (DocumentModel::CLASSIFICATIONS as $c): ?>
                                                        <option value="<?= $c ?>" <?= $d['classification'] === $c ? 'selected' : '' ?> <?= $c === 'shared' && !$canShare && $d['classification'] !== 'shared' ? 'disabled' : '' ?>><?= lang('Lgc.doc_class_' . $c) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        <?php else: ?>
                                            <?= Meta::badge('doc_class', $d['classification']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-xs text-muted">
                                        <?= esc(Meta::date($d['created_at'])) ?><br>
                                        <?= $d['source'] === 'client' ? '<span class="badge badge-info">' . lang('Lgc.fromClient') . '</span>' : esc($users[(int) $d['uploaded_by']] ?? '—') ?>
                                    </td>
                                    <td class="text-right">
                                        <?php if (($canWork && $acc->can('lgc.delete'))): ?>
                                            <?= view('partials/delete_button', ['action' => base_url('legalcase/documentos/eliminar/' . (int) $d['id']), 'confirm' => lang('Lgc.confirmDelete'), 'label' => lang('Lgc.delete'), 'class' => 'btn btn-danger btn-sm', 'icon' => 'trash', 'text' => '']) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($signatures): ?>
            <div class="card">
                <div class="card-header"><i class="icon icon-pencil-square"></i><h5 class="font-semibold"><?= lang('Lgc.signatures') ?></h5></div>
                <div class="card-body">
                    <p class="mb-2 text-xs text-muted"><?= lang('Lgc.signaturesHint') ?></p>
                    <ul class="divide-y divide-line text-sm">
                        <?php foreach ($signatures as $sg): ?>
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <div>
                                    <span class="font-semibold"><?= esc($sg['purpose']) ?></span>
                                    <span class="badge <?= ['pending' => 'badge-warning', 'signed' => 'badge-success', 'declined' => 'badge-danger', 'cancelled' => 'badge-secondary'][$sg['status']] ?? 'badge-light' ?>"><?= lang('Lgc.sig_' . $sg['status']) ?></span>
                                    <div class="text-xs text-muted"><?= esc($accounts[(int) $sg['account_id']] ?? '#') ?> · <?= esc(Meta::date($sg['signed_at'] ?: $sg['created_at'])) ?></div>
                                </div>
                                <div class="flex gap-1">
                                    <?php if ($sg['status'] === 'signed'): ?>
                                        <a class="btn btn-ghost btn-sm" href="<?= base_url('legalcase/firmas/evidencia/' . (int) $sg['id']) ?>"><i class="icon icon-shield-check"></i> <?= lang('Lgc.signEvidence') ?></a>
                                    <?php elseif ($sg['status'] === 'pending' && $canShare): ?>
                                        <form method="post" action="<?= base_url('legalcase/firmas/anular/' . (int) $sg['id']) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= lang('Lgc.cancelItem') ?></button></form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="icon icon-clipboard"></i><h5 class="font-semibold"><?= lang('Lgc.docRequests') ?></h5></div>
            <div class="card-body">
                <?php if (!$requests): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noDocRequests') ?></p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($requests as $r): ?>
                            <li class="rounded-md border border-line p-3 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-semibold"><?= esc($r['title']) ?> <?= Meta::badge('req', $r['status']) ?>
                                        <span class="text-xs font-normal text-muted"><?= $r['account_id'] ? esc($accounts[(int) $r['account_id']] ?? '#') : lang('Lgc.anyClient') ?></span></div>
                                    <div class="text-xs text-muted"><?= lang('Lgc.fDueAt') ?>: <?= esc(Meta::date($r['due_at'], false)) ?></div>
                                </div>
                                <?php if ($r['instructions']): ?><p class="mt-1 whitespace-pre-line text-muted"><?= esc($r['instructions']) ?></p><?php endif; ?>
                                <?php if ($r['review_note']): ?><p class="mt-1 text-xs"><?= lang('Lgc.reviewNote') ?>: <?= esc($r['review_note']) ?></p><?php endif; ?>
                                <?php if (in_array($r['status'], ['pending', 'submitted', 'rejected'], true) && ($canWork && $acc->can('lgc.edit'))): ?>
                                    <form method="post" action="<?= base_url('legalcase/solicitudes/revisar/' . (int) $r['id']) ?>" class="mt-2 flex flex-wrap items-end gap-2">
                                        <?= csrf_field() ?>
                                        <input type="text" name="note" class="form-input" maxlength="255" placeholder="<?= esc(lang('Lgc.phReviewNote'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.reviewNote'), 'attr') ?>">
                                        <?php if ($r['status'] === 'submitted'): ?>
                                            <button name="decision" value="accept" class="btn btn-success btn-sm"><i class="icon icon-check-circle"></i> <?= lang('Lgc.accept') ?></button>
                                            <button name="decision" value="reject" class="btn btn-danger btn-sm"><i class="icon icon-x-circle"></i> <?= lang('Lgc.reject') ?></button>
                                        <?php endif; ?>
                                        <button name="decision" value="cancel" class="btn btn-ghost btn-sm" onclick="return confirm('<?= esc(lang('Lgc.confirmCancel'), 'js') ?>')"><?= lang('Lgc.cancelItem') ?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (($canWork && $acc->can('lgc.create'))): ?>
        <div class="min-w-0 space-y-4">
            <div class="card">
                <div class="card-header"><i class="icon icon-upload"></i><h5 class="font-semibold"><?= lang('Lgc.uploadDocument') ?></h5></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('legalcase/asuntos/documento/' . $id) ?>" enctype="multipart/form-data" class="space-y-3">
                        <?= csrf_field() ?>
                        <input type="file" name="file" required class="form-input" aria-label="<?= esc(lang('Lgc.uploadDocument'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Lgc.uploadHint', [(int) (DocumentService::MAX_BYTES / 1048576)]) ?></small>
                        <div class="form-group">
                            <label class="form-label" for="up-class"><?= lang('Lgc.fClassification') ?></label>
                            <select id="up-class" name="classification" class="form-select">
                                <?php foreach (DocumentModel::CLASSIFICATIONS as $c): ?><option value="<?= $c ?>" <?= $c === 'internal' ? 'selected' : '' ?> <?= $c === 'shared' && !$canShare ? 'disabled' : '' ?>><?= lang('Lgc.doc_class_' . $c) ?></option><?php endforeach; ?>
                            </select>
                            <small class="form-hint"><?= lang('Lgc.classHint') ?></small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-upload"></i> <?= lang('Lgc.upload') ?></button>
                    </form>
                </div>
            </div>

            <?php if ($open): ?>
                <div class="card">
                    <div class="card-header"><i class="icon icon-clipboard"></i><h5 class="font-semibold"><?= lang('Lgc.docRequestNew') ?></h5></div>
                    <div class="card-body">
                        <form method="post" action="<?= base_url('legalcase/asuntos/solicitud/' . $id) ?>" class="space-y-3">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="form-label" for="rq-title"><?= lang('Lgc.fTitle') ?> <span class="text-danger">*</span></label>
                                <input id="rq-title" type="text" name="title" class="form-input" required maxlength="190" placeholder="<?= esc(lang('Lgc.phDocRequest'), 'attr') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="rq-ins"><?= lang('Lgc.fInstructions') ?></label>
                                <textarea id="rq-ins" name="instructions" rows="3" class="form-textarea" placeholder="<?= esc(lang('Lgc.phInstructions'), 'attr') ?>"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="rq-to"><?= lang('Lgc.fRecipient') ?></label>
                                <select id="rq-to" name="account_id" class="form-select">
                                    <option value=""><?= lang('Lgc.anyClient') ?></option>
                                    <?php foreach ($accounts as $aid => $mail): ?><option value="<?= (int) $aid ?>"><?= esc($mail) ?></option><?php endforeach; ?>
                                </select>
                                <small class="form-hint"><?= lang('Lgc.recipientHint') ?></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="rq-due"><?= lang('Lgc.fDueAt') ?></label>
                                <input id="rq-due" type="date" name="due_at" class="form-input">
                            </div>
                            <small class="form-hint"><?= lang('Lgc.docRequestHint') ?></small>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-send"></i> <?= lang('Lgc.request') ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
