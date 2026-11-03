<?php
/**
 * Tabla reutilizable server-side (búsqueda + orden + paginación).
 *
 * @var array         $dt       ['rows','pager','q','sort','dir','perPage']
 * @var array         $columns  [['key','label','sortable'?,'render'?,'class'?,'tdclass'?], ...]
 * @var string        $baseUrl  URL del listado (ej. base_url('users'))
 * @var callable|null $actions  fn($row) => html de la celda de acciones
 * @var string|null   $searchPlaceholder
 * @var string|null   $emptyText
 */
$rows        = $dt['rows'] ?? [];
$pager       = $dt['pager'] ?? null;
$q           = $dt['q'] ?? '';
$sort        = $dt['sort'] ?? '';
$dir         = $dt['dir'] ?? 'asc';
$extraParams = $extraParams ?? []; // filtros extra a preservar (type_id, status, from, to, …)
$showSearch  = $showSearch ?? true; // false: el buscador lo aporta la vista (fila unificada con filtros)

$buildUrl = static function (array $ov = []) use ($baseUrl, $q, $sort, $dir, $extraParams) {
    $params = array_merge($extraParams, [
        'q'    => array_key_exists('q', $ov) ? $ov['q'] : ($q !== '' ? $q : null),
        'sort' => array_key_exists('sort', $ov) ? $ov['sort'] : ($sort ?: null),
        'dir'  => array_key_exists('dir', $ov) ? $ov['dir'] : ($dir ?: null),
        'page' => $ov['page'] ?? null,
    ]);
    $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');

    return $baseUrl . ($params ? '?' . http_build_query($params) : '');
};
$sortLink = static function (string $col) use ($buildUrl, $sort, $dir) {
    $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';

    return $buildUrl(['sort' => $col, 'dir' => $newDir, 'page' => null]);
};
?>
<?php if ($showSearch): ?>
<!-- Búsqueda -->
<form method="get" action="<?= $baseUrl ?>" class="mb-4 flex flex-wrap items-center gap-2">
    <div class="input-group max-w-xs">
        <span class="input-group-text"><i class="icon icon-search"></i></span>
        <input type="text" name="q" value="<?= esc($q, 'attr') ?>" class="form-input"
            placeholder="<?= esc($searchPlaceholder ?? 'Buscar…', 'attr') ?>">
    </div>
    <?php if ($sort): ?>
        <input type="hidden" name="sort" value="<?= esc($sort, 'attr') ?>">
        <input type="hidden" name="dir" value="<?= esc($dir, 'attr') ?>">
    <?php endif; ?>
    <?php foreach ($extraParams as $ek => $ev): ?>
        <?php if ($ev !== null && $ev !== ''): ?>
            <input type="hidden" name="<?= esc($ek, 'attr') ?>" value="<?= esc($ev, 'attr') ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-secondary">Buscar</button>
    <?php if ($q !== ''): ?>
        <a href="<?= $baseUrl ?>" class="btn btn-ghost" title="Limpiar"><i class="icon icon-x-lg"></i></a>
    <?php endif; ?>
</form>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <p class="py-6 text-center text-muted"><?= esc($emptyText ?? 'No hay registros.') ?></p>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <?php $scol = $col['sortKey'] ?? $col['key']; ?>
                        <th class="<?= $col['class'] ?? '' ?>">
                            <?php if (!empty($col['sortable'])): ?>
                                <a href="<?= $sortLink($scol) ?>" class="inline-flex items-center gap-1 hover:text-primary">
                                    <?= esc($col['label']) ?>
                                    <?php if ($sort === $scol): ?>
                                        <i class="icon icon-caret-<?= $dir === 'asc' ? 'up' : 'down' ?>-fill text-xs"></i>
                                    <?php else: ?>
                                        <i class="icon icon-arrow-down-up text-xs opacity-40"></i>
                                    <?php endif; ?>
                                </a>
                            <?php else: ?>
                                <?= esc($col['label']) ?>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                    <?php if (!empty($actions)): ?><th class="text-right">Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <td class="<?= $col['tdclass'] ?? '' ?>">
                                <?php if (!empty($col['render']) && is_callable($col['render'])): ?>
                                    <?= $col['render']($row) ?>
                                <?php else: ?>
                                    <?= esc($row[$col['key']] ?? '') ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if (!empty($actions)): ?><td><?= $actions($row) ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación server-side -->
    <?php if ($pager && $pager->getPageCount() > 1): ?>
        <?php
        $cur   = $pager->getCurrentPage();
        $last  = $pager->getPageCount();
        $start = max(1, $cur - 2);
        $end   = min($last, $cur + 2);
        ?>
        <nav class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="text-muted">Página <?= $cur ?> de <?= $last ?> · <?= $pager->getTotal() ?> registros</span>
            <div class="flex items-center gap-1">
                <a href="<?= $buildUrl(['page' => max(1, $cur - 1)]) ?>"
                    class="btn btn-outline btn-sm <?= $cur <= 1 ? 'pointer-events-none opacity-50' : '' ?>"><i class="icon icon-chevron-left"></i></a>
                <?php if ($start > 1): ?>
                    <a href="<?= $buildUrl(['page' => 1]) ?>" class="btn btn-outline btn-sm">1</a>
                    <?php if ($start > 2): ?><span class="px-1 text-muted">…</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <a href="<?= $buildUrl(['page' => $p]) ?>" class="btn btn-sm <?= $p === $cur ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($end < $last): ?>
                    <?php if ($end < $last - 1): ?><span class="px-1 text-muted">…</span><?php endif; ?>
                    <a href="<?= $buildUrl(['page' => $last]) ?>" class="btn btn-outline btn-sm"><?= $last ?></a>
                <?php endif; ?>
                <a href="<?= $buildUrl(['page' => min($last, $cur + 1)]) ?>"
                    class="btn btn-outline btn-sm <?= $cur >= $last ? 'pointer-events-none opacity-50' : '' ?>"><i class="icon icon-chevron-right"></i></a>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>
