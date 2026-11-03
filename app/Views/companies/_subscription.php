<?php
/** @var array $plans  @var array|null $subscription */
$subscription ??= null;
$sv = static function (string $k, $def = '') use ($subscription) {
    return old($k, $subscription[$k] ?? $def);
};
$fmt = static fn ($dt) => $dt ? date('Y-m-d', strtotime($dt)) : '';
$statuses = ['trial' => 'Prueba', 'active' => 'Activa', 'expired' => 'Expirada', 'cancelled' => 'Cancelada'];
?>
<div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
    <div class="form-group">
        <label for="plan_id" class="form-label">Plan *</label>
        <select class="form-select" id="plan_id" name="plan_id" required>
            <option value="">— Selecciona un plan —</option>
            <?php foreach ($plans as $plan): ?>
                <option value="<?= esc($plan['id'], 'attr') ?>" <?= (string) $sv('plan_id') === (string) $plan['id'] ? 'selected' : '' ?>>
                    <?= esc($plan['name']) ?> (<?= esc(number_format((float) $plan['price_amount'], 2)) ?> <?= esc($plan['price_currency']) ?> / <?= esc($plan['service_days']) ?>d)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="status" class="form-label">Estado *</label>
        <select class="form-select" id="status" name="status" required>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= $key ?>" <?= $sv('status', 'trial') === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
    <div class="form-group">
        <label for="start_date" class="form-label">Inicio</label>
        <input type="date" class="form-input" id="start_date" name="start_date" value="<?= esc(old('start_date', $fmt($subscription['start_date'] ?? null)), 'attr') ?>">
    </div>
    <div class="form-group">
        <label for="end_date" class="form-label">Fin</label>
        <input type="date" class="form-input" id="end_date" name="end_date" value="<?= esc(old('end_date', $fmt($subscription['end_date'] ?? null)), 'attr') ?>">
        <small class="form-hint">Si se deja vacío, se calcula con los días del plan.</small>
    </div>
</div>
