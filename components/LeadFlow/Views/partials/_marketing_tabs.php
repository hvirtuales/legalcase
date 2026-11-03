<?php
/** @var string $active */
?>
<nav class="tabs mb-4">
    <a href="<?= base_url('leadflow/marketing/fuentes') ?>" class="tab-link <?= $active === 'sources' ? 'active' : '' ?>">
        <i class="icon icon-signpost"></i> <?= lang('Lfl.sources') ?>
    </a>
    <a href="<?= base_url('leadflow/marketing/campanas') ?>" class="tab-link <?= $active === 'campaigns' ? 'active' : '' ?>">
        <i class="icon icon-bar-chart"></i> <?= lang('Lfl.campaigns') ?>
    </a>
</nav>
