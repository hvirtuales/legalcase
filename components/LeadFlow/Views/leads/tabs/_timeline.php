<div class="card">
    <div class="card-header"><i class="icon icon-clock-history"></i><h5 class="font-semibold"><?= lang('Lfl.tabTimeline') ?></h5></div>
    <div class="card-body">
        <p class="mb-4 text-xs text-muted"><?= lang('Lfl.timelineHint') ?></p>
        <?= $this->include('Components\LeadFlow\Views\leads\tabs\_timeline_list') ?>
    </div>
</div>
