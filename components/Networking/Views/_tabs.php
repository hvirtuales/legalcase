<?php
/**
 * Barra de pestañas de la red. Mismo patrón visual que las pestañas de Cobros.
 *
 * @var array  $counts  ['received','sent','connections','unread']
 * @var string $current descubrir|recibidas|enviadas|conexiones|mi-perfil
 */
$counts  = $counts ?? ['received' => 0, 'sent' => 0, 'connections' => 0, 'unread' => 0];
$current = $current ?? '';

$tabs = [
    'descubrir'  => [lang('Net.discover'), null],
    'recibidas'  => [lang('Net.received'), (int) $counts['received']],
    'enviadas'   => [lang('Net.sent'), (int) $counts['sent']],
    'conexiones' => [lang('Net.connections'), (int) $counts['connections']],
];
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-line">
    <div class="flex flex-wrap gap-1">
        <?php foreach ($tabs as $key => [$label, $count]): ?>
            <a href="<?= base_url('networking/' . $key) ?>"
                class="border-b-2 px-3 py-2 text-sm <?= $current === $key ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
                <?= esc($label) ?>
                <?php if ($count !== null): ?><span class="text-xs">(<?= $count ?>)</span><?php endif; ?>
                <?php if ($key === 'conexiones' && (int) $counts['unread'] > 0): ?>
                    <span class="badge badge-danger ml-1"><?= (int) $counts['unread'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <a href="<?= base_url('networking/mi-perfil') ?>"
        class="mb-1 btn btn-secondary btn-sm <?= $current === 'mi-perfil' ? 'btn-primary' : '' ?>">
        <i class="icon icon-person"></i> <?= lang('Net.myProfile') ?>
    </a>
</div>
