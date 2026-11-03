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

$id   = (int) $matter['id'];
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
                                        <a class="font-semibold text-primary" href="<?= base_url('legalcase/documentos/descargar/' . (int) $d['id']) ?>"><i class="icon icon-file-earmark"></i> <?= esc($d['name']) ?></a>
                                        <div class="text-xs text-muted"><?= esc($size((int) $d['size'])) ?> · <span class="font-mono" title="SHA-256"><?= esc(substr((string) $d['sha256'], 0, 12)) ?>…</span></div>
                                    </td>
                                    <td>
                                        <?php if ($acc->can('lgc.edit')): ?>
                                            <form method="post" action="<?= base_url('legalcase/documentos/clasificar/' . (int) $d['id']) ?>"><?= csrf_field() ?>
                                                <select name="classification" class="form-select" onchange="if (this.value !== 'shared' || confirm('<?= esc(lang('Lgc.confirmShare'), 'js') ?>')) { this.form.submit(); } else { this.value = '<?= esc($d['classification'], 'js') ?>'; }" aria-label="<?= esc(lang('Lgc.fClassification'), 'attr') ?>">
                                                    <?php foreach (DocumentModel::CLASSIFICATIONS as $c): ?><option value="<?= $c ?>" <?= $d['classification'] === $c ? 'selected' : '' ?>><?= lang('Lgc.doc_class_' . $c) ?></option><?php endforeach; ?>
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
                                        <?php if ($acc->can('lgc.delete')): ?>
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
                                    <div class="font-semibold"><?= esc($r['title']) ?> <?= Meta::badge('req', $r['status']) ?></div>
                                    <div class="text-xs text-muted"><?= lang('Lgc.fDueAt') ?>: <?= esc(Meta::date($r['due_at'], false)) ?></div>
                                </div>
                                <?php if ($r['instructions']): ?><p class="mt-1 whitespace-pre-line text-muted"><?= esc($r['instructions']) ?></p><?php endif; ?>
                                <?php if ($r['review_note']): ?><p class="mt-1 text-xs"><?= lang('Lgc.reviewNote') ?>: <?= esc($r['review_note']) ?></p><?php endif; ?>
                                <?php if (in_array($r['status'], ['pending', 'submitted', 'rejected'], true) && $acc->can('lgc.edit')): ?>
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

    <?php if ($acc->can('lgc.create')): ?>
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
                                <?php foreach (DocumentModel::CLASSIFICATIONS as $c): ?><option value="<?= $c ?>" <?= $c === 'internal' ? 'selected' : '' ?>><?= lang('Lgc.doc_class_' . $c) ?></option><?php endforeach; ?>
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
