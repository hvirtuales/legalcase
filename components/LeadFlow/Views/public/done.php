<?= $this->extend('Components\LeadFlow\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php include __DIR__ . '/_icons.php'; ?>
<?= view('Components\LeadFlow\Views\public\_header', ['company' => $company, 'subtitle' => $form['name']]) ?>
<main class="lf-wrap">
    <section class="lf-card lf-ok">
        <?= lfl_icon('check') ?>
        <h2><?= lang('Lfl.pubDoneTitle') ?></h2>
        <p class="lf-hint" style="white-space:pre-line"><?= esc($form['success_message'] ?: lang('Lfl.pubDoneDefault')) ?></p>
    </section>
</main>
<footer class="lf-foot"><?= esc($company['name'] ?? '') ?></footer>
<?= $this->endSection() ?>
