<?php
/** @var string $active */
?>
<nav class="tabs mb-4">
    <a href="<?= base_url('leadflow/automatizaciones') ?>" class="tab-link <?= $active === 'list' ? 'active' : '' ?>">
        <i class="icon icon-robot"></i> <?= lang('Lfl.autTabList') ?>
    </a>
    <a href="<?= base_url('leadflow/automatizaciones/ejecuciones') ?>" class="tab-link <?= $active === 'runs' ? 'active' : '' ?>">
        <i class="icon icon-clock-history"></i> <?= lang('Lfl.autTabRuns') ?>
    </a>
</nav>
