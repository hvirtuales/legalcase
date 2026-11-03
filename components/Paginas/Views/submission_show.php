<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Un mensaje recibido. Los campos se muestran tal como los definía el formulario
 * el día que se envió (se guardaron etiqueta → valor), así que cambiar el
 * formulario después no reescribe lo ya recibido.
 *
 * @var array      $row
 * @var array|null $page
 */
$data = is_array($row['data'] ?? null) ? $row['data'] : [];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-envelope"></i>
            <h5 class="font-semibold"><?= esc($row['form_title'] ?: lang('Pag.formNoTitle')) ?></h5>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form action="<?= base_url('paginas/mensajes/leido/' . (int) $row['id']) ?>" method="post" class="inline">
                <?= csrf_field() ?>
                <button class="btn btn-secondary btn-sm">
                    <i class="icon icon-<?= (int) $row['is_read'] === 1 ? 'eye-slash' : 'check-circle' ?>"></i>
                    <?= (int) $row['is_read'] === 1 ? lang('Pag.markUnread') : lang('Pag.markRead') ?>
                </button>
            </form>
            <?= view('partials/delete_button', [
                'action'  => base_url('paginas/mensajes/eliminar/' . (int) $row['id']),
                'confirm' => lang('Pag.confirmDeleteMessage'),
                'label'   => lang('Pag.delete'),
                'text'    => lang('Pag.delete'),
            ]) ?>
            <a href="<?= base_url('paginas/mensajes') ?>" class="btn btn-ghost btn-sm">
                <i class="icon icon-arrow-left"></i> <?= lang('Pag.back') ?>
            </a>
        </div>
    </div>

    <div class="card-body">
        <dl class="mb-5 grid gap-3 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold text-muted"><?= lang('Pag.receivedCol') ?></dt>
                <dd class="text-sm"><?= esc(substr((string) $row['created_at'], 0, 16)) ?></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted"><?= lang('Pag.pageCol') ?></dt>
                <dd class="text-sm">
                    <?php if ($page): ?>
                        <a href="<?= base_url('paginas/editor/' . (int) $page['id']) ?>" class="text-primary">
                            <?= esc($page['title']) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted"><?= lang('Pag.ipCol') ?></dt>
                <dd class="text-sm text-muted"><?= esc($row['ip']) ?></dd>
            </div>
        </dl>

        <?php if (!$data): ?>
            <p class="py-6 text-center text-muted"><?= lang('Pag.messageEmpty') ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Pag.fieldCol') ?></th>
                            <th><?= lang('Pag.valueCol') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $label => $value): ?>
                            <tr>
                                <th class="w-1/3 align-top font-semibold"><?= esc((string) $label) ?></th>
                                <td class="whitespace-pre-line align-top">
                                    <?php if ($value === ''): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif (filter_var((string) $value, FILTER_VALIDATE_EMAIL)): ?>
                                        <a href="mailto:<?= esc((string) $value, 'attr') ?>" class="text-primary"><?= esc((string) $value) ?></a>
                                    <?php else: ?>
                                        <?= esc((string) $value) ?>
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
<?= $this->endSection() ?>
