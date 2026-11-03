<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="mx-auto max-w-lg">
    <div class="mb-4 flex items-center gap-2">
        <a href="<?= base_url('mapas/categorias') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Map.back') ?></a>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (session('errors')): ?>
                <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i>
                    <ul class="mb-0"><?php foreach ((array) session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="<?= $row ? base_url('mapas/categorias/editar/' . $row['id']) : base_url('mapas/categorias/crear') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="name"><?= lang('Map.categoryName') ?></label>
                    <input type="text" name="name" id="name" class="form-input" required
                           value="<?= esc($row['name'] ?? old('name'), 'attr') ?>"
                           placeholder="<?= esc(lang('Map.categoryNamePh'), 'attr') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="color"><?= lang('Map.categoryColor') ?></label>
                    <input type="color" name="color" id="color" class="form-input h-11 w-24 p-1"
                           value="<?= esc(\Components\Mapas\Libraries\MapService::color($row['color'] ?? null), 'attr') ?>">
                    <p class="mt-1 text-xs text-muted"><?= lang('Map.categoryColorHint') ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="sort_order"><?= lang('Map.order') ?></label>
                    <input type="number" name="sort_order" id="sort_order" class="form-input"
                           value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
                <label class="flex items-center gap-2 mb-4 text-sm">
                    <input type="checkbox" name="is_active" value="1" <?= (!$row || $row['is_active']) ? 'checked' : '' ?>>
                    <?= lang('Map.active') ?>
                </label>
                <div class="flex justify-end gap-2">
                    <a href="<?= base_url('mapas/categorias') ?>" class="btn btn-secondary"><?= lang('Map.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-check-circle"></i> <?= lang('Map.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
