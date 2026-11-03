<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<!-- Filters Card -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-funnel"></i>
        <h6 class="font-semibold"><?= lang('App.filters') ?></h6>
    </div>
    <div class="card-body">
        <form action="<?= base_url('audit') ?>" method="get">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="event" class="form-label"><?= lang('App.event') ?></label>
                    <select class="form-select" id="event" name="event">
                        <option value=""><?= lang('App.all') ?></option>
                        <option value="login" <?= ($filters['event'] ?? '') === 'login' ? 'selected' : '' ?>>Login</option>
                        <option value="logout" <?= ($filters['event'] ?? '') === 'logout' ? 'selected' : '' ?>>Logout</option>
                        <option value="created" <?= ($filters['event'] ?? '') === 'created' ? 'selected' : '' ?>><?= lang('App.created') ?></option>
                        <option value="updated" <?= ($filters['event'] ?? '') === 'updated' ? 'selected' : '' ?>><?= lang('App.updated') ?></option>
                        <option value="deleted" <?= ($filters['event'] ?? '') === 'deleted' ? 'selected' : '' ?>><?= lang('App.deleted') ?></option>
                    </select>
                </div>
                <div>
                    <label for="start_date" class="form-label"><?= lang('App.startDate') ?></label>
                    <input type="date" class="form-input" id="start_date" name="start_date"
                        value="<?= $filters['start_date'] ?? '' ?>">
                </div>
                <div>
                    <label for="end_date" class="form-label"><?= lang('App.endDate') ?></label>
                    <input type="date" class="form-input" id="end_date" name="end_date"
                        value="<?= $filters['end_date'] ?? '' ?>">
                </div>
                <div>
                    <label class="form-label">&nbsp;</label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="icon icon-search"></i>
                            <?= lang('App.filter') ?>
                        </button>
                        <a href="<?= base_url('audit') ?>" class="btn btn-secondary"><i class="icon icon-x"></i></a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Audit Logs Card -->
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-journal-text"></i>
            <h5 class="font-semibold"><?= lang('App.auditLogs') ?></h5>
        </div>
        <div class="flex gap-2">
            <a href="<?= base_url('audit/statistics') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-bar-chart"></i>
                <?= lang('App.statistics') ?>
            </a>
            <a href="<?= base_url('audit/export') ?>" class="btn btn-success btn-sm">
                <i class="icon icon-download"></i>
                <?= lang('App.exportCSV') ?>
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="py-4 text-center text-muted"><?= lang('App.noAuditRecords') ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('App.dateTime') ?></th>
                            <th><?= lang('App.user') ?></th>
                            <th><?= lang('App.event') ?></th>
                            <th><?= lang('App.type') ?></th>
                            <th>IP</th>
                            <th class="text-right"><?= lang('App.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><span class="text-xs"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></span></td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                        <strong><?= esc($log['username'] ?? 'Usuario #' . $log['user_id']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted"><?= lang('App.system') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-secondary';
                                    if (str_contains($log['event'], 'login'))
                                        $badgeClass = 'badge-success';
                                    elseif (str_contains($log['event'], 'logout'))
                                        $badgeClass = 'badge-info';
                                    elseif (str_contains($log['event'], 'delete'))
                                        $badgeClass = 'badge-danger';
                                    elseif (str_contains($log['event'], 'create'))
                                        $badgeClass = 'badge-primary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= esc($log['event']) ?></span>
                                </td>
                                <td><span class="text-xs text-muted"><?= esc(class_basename($log['auditable_type'] ?? 'N/A')) ?></span></td>
                                <td><span class="text-xs"><?= esc($log['ip_address']) ?></span></td>
                                <td>
                                    <div class="flex justify-end">
                                        <a href="<?= base_url('audit/view/' . $log['id']) ?>" class="btn btn-secondary btn-sm">
                                            <i class="icon icon-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-center text-xs text-muted">
                <?= lang('App.totalRecords') ?>: <?= $total ?? count($logs) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
