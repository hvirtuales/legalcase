<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mx-auto max-w-4xl">
    <div class="card">
        <div class="card-header justify-between">
            <div class="flex items-center gap-2">
                <i class="icon icon-journal-text"></i>
                <h5 class="font-semibold">Detalle de Auditoría #<?= esc($log['id']) ?></h5>
            </div>
            <a href="<?= base_url('audit') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-arrow-left"></i>
                Volver
            </a>
        </div>
        <div class="card-body">
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <h6 class="mb-2 text-xs uppercase text-muted">Usuario</h6>
                    <?php if (isset($log['username'])): ?>
                        <div class="flex items-center gap-2">
                            <div class="avatar"><?= strtoupper(substr($log['username'], 0, 1)) ?></div>
                            <div>
                                <div class="font-bold"><?= esc($log['username']) ?></div>
                                <div class="text-xs text-muted"><?= esc($log['email'] ?? '') ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">Sistema / Desconocido (ID: <?= esc($log['user_id']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="md:text-right">
                    <h6 class="mb-2 text-xs uppercase text-muted">Fecha y Hora</h6>
                    <div class="font-bold"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></div>
                    <div class="text-xs text-muted">
                        <?= \CodeIgniter\I18n\Time::parse($log['created_at'])->humanize() ?>
                    </div>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-md bg-surface-muted p-3 text-center">
                    <div class="mb-1 text-xs uppercase text-muted">Evento</div>
                    <div class="font-bold text-primary"><?= esc($log['event']) ?></div>
                </div>
                <div class="rounded-md bg-surface-muted p-3 text-center">
                    <div class="mb-1 text-xs uppercase text-muted">Entidad</div>
                    <div class="font-bold">
                        <?= esc(basename(str_replace('\\', '/', $log['auditable_type']))) ?>
                        <span class="badge badge-secondary ml-1">#<?= esc($log['auditable_id']) ?></span>
                    </div>
                </div>
                <div class="rounded-md bg-surface-muted p-3 text-center">
                    <div class="mb-1 text-xs uppercase text-muted">Dirección IP</div>
                    <div class="font-mono"><?= esc($log['ip_address']) ?></div>
                </div>
            </div>

            <div class="mb-6">
                <h6 class="mb-2 text-xs uppercase text-muted">Detalles Técnicos</h6>
                <table class="w-full overflow-hidden rounded-md border border-line text-sm">
                    <tr class="border-b border-line">
                        <th class="w-36 bg-surface-muted px-3 py-2 text-left font-medium">URL</th>
                        <td class="px-3 py-2"><?= esc($log['url']) ?></td>
                    </tr>
                    <tr>
                        <th class="bg-surface-muted px-3 py-2 text-left font-medium">User Agent</th>
                        <td class="break-all px-3 py-2 font-mono text-xs"><?= esc($log['user_agent']) ?></td>
                    </tr>
                </table>
            </div>

            <?php
            $oldValues = is_array($log['old_values'] ?? '')
                ? $log['old_values']
                : json_decode($log['old_values'] ?? '', true);
            $newValues = is_array($log['new_values'] ?? '')
                ? $log['new_values']
                : json_decode($log['new_values'] ?? '', true);
            ?>

            <?php if (!empty($oldValues) || !empty($newValues)): ?>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <h6 class="mb-2 text-xs uppercase text-danger">
                            <i class="icon icon-dash-circle"></i> Valores Anteriores
                        </h6>
                        <?php if (!empty($oldValues)): ?>
                            <pre class="overflow-x-auto rounded-md border border-line bg-surface-muted p-3 text-xs text-danger"><?= json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                        <?php else: ?>
                            <div class="text-xs italic text-muted">Sin datos previos</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="mb-2 text-xs uppercase text-success">
                            <i class="icon icon-plus-circle"></i> Nuevos Valores
                        </h6>
                        <?php if (!empty($newValues)): ?>
                            <pre class="overflow-x-auto rounded-md border border-line bg-surface-muted p-3 text-xs text-success"><?= json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                        <?php else: ?>
                            <div class="text-xs italic text-muted">Sin datos nuevos</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
