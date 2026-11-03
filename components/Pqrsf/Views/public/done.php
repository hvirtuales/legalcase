<?= $this->extend('Components\Pqrsf\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * Radicado con éxito.
 *
 * Cuando la solicitud es anónima o el ciudadano no autorizó comunicaciones, el
 * **enlace del ticket es la única forma de volver**: por eso aquí se muestra
 * grande, copiable y con la advertencia de guardarlo.
 *
 * @var array  $request @var array $company @var array $point
 * @var string $ticketUrl @var bool $onlyLink @var string $typeName
 */
$ic = require __DIR__ . '/_icons.php';
?>

<header class="pq-top">
    <div class="pq-top-in">
        <?php if (!empty($company['logo'])): ?>
            <img class="pq-logo" src="<?= media_url($company['logo']) ?>" alt="">
        <?php endif; ?>
        <div>
            <h1><?= esc($company['name']) ?></h1>
            <p><?= lang('Pqr.pubHeadline') ?></p>
        </div>
    </div>
</header>

<div class="pq-wrap">
    <div class="pq-card" style="text-align:center">
        <div class="pq-i" style="font-size:34px;color:var(--pq-brand);justify-content:center"><?= $ic['check'] ?></div>
        <h2 style="font-size:20px;margin-top:8px"><?= lang('Pqr.doneTitle') ?></h2>
        <p class="pq-hint"><?= lang('Pqr.doneHint', [mb_strtolower($typeName)]) ?></p>

        <div class="pq-ref"><?= esc($request['number']) ?></div>

        <p style="font-size:13px;color:var(--pq-muted);margin:0">
            <span class="pq-i" style="vertical-align:-2px"><?= $ic['clock'] ?></span>
            <?= lang('Pqr.doneDue', [date('Y-m-d', strtotime((string) $request['due_at']))]) ?>
        </p>
    </div>

    <div class="pq-card">
        <h2><?= lang('Pqr.doneTicket') ?></h2>
        <p class="pq-hint">
            <?= $onlyLink ? lang('Pqr.doneTicketOnly') : lang('Pqr.doneTicketAlso') ?>
        </p>

        <div class="pq-link">
            <input type="text" id="ticketUrl" readonly value="<?= esc($ticketUrl) ?>">
            <button type="button" class="pq-btn pq-btn-ghost" id="copyBtn">
                <span class="pq-i"><?= $ic['link'] ?></span> <?= lang('Pqr.copy') ?>
            </button>
        </div>

        <p style="margin-top:14px">
            <a class="pq-btn" href="<?= esc($ticketUrl) ?>"><?= lang('Pqr.doneOpenTicket') ?></a>
        </p>

        <?php if ($onlyLink): ?>
            <div class="pq-note" style="margin-top:14px;border-color:var(--pq-brand)">
                <strong><?= lang('Pqr.doneKeepIt') ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="pq-foot"><?= esc($company['name']) ?> · <?= esc($point['name']) ?></footer>

<script>
(function () {
    var btn = document.getElementById('copyBtn');
    var box = document.getElementById('ticketUrl');

    btn.addEventListener('click', function () {
        box.select();
        var done = function () {
            var html = btn.innerHTML;
            btn.textContent = <?= json_encode(lang('Pqr.copied')) ?>;
            setTimeout(function () { btn.innerHTML = html; }, 2000);
        };
        // navigator.clipboard no existe fuera de HTTPS: el textarea de siempre.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(box.value).then(done);
        } else {
            try { document.execCommand('copy'); done(); } catch (e) {}
        }
    });
}());
</script>
<?= $this->endSection() ?>
