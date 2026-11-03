<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="mx-auto max-w-xl">
    <div class="mb-4 flex items-center gap-2">
        <a href="<?= base_url('mapas') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Map.back') ?></a>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (session('errors')): ?>
                <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i>
                    <ul class="mb-0"><?php foreach ((array) session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="<?= $map ? base_url('mapas/editar/' . $map['id']) : base_url('mapas/crear') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="name"><?= lang('Map.name') ?></label>
                    <input type="text" name="name" id="name" class="form-input" required
                           value="<?= esc($map['name'] ?? old('name'), 'attr') ?>"
                           placeholder="<?= esc(lang('Map.namePh'), 'attr') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="description"><?= lang('Map.description') ?></label>
                    <input type="text" name="description" id="description" class="form-input"
                           value="<?= esc($map['description'] ?? old('description'), 'attr') ?>">
                </div>
                <div class="flex justify-end gap-2">
                    <a href="<?= base_url('mapas') ?>" class="btn btn-secondary"><?= lang('Map.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-check-circle"></i> <?= lang('Map.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
