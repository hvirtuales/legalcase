<?= $this->extend('Components\Pqrsf\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * La consulta del ciudadano.
 *
 * Muestra el estado, la respuesta y el hilo **público**. Las notas internas y los
 * nombres de los gestores no salen de la casa: el hilo llega ya filtrado por
 * `is_private` desde el controlador.
 *
 * @var array  $request @var array $company @var array $thread
 * @var string $typeName @var array $links soportes y enlaces de la respuesta
 */
$ic  = require __DIR__ . '/_icons.php';
$due = $request['due_at'] ? date('Y-m-d', strtotime((string) $request['due_at'])) : '';
?>

<header class="pq-top">
    <div class="pq-top-in">
        <?php if (!empty($company['logo'])): ?>
            <img class="pq-logo" src="<?= media_url($company['logo']) ?>" alt="">
        <?php endif; ?>
        <div>
            <h1><?= esc($company['name']) ?></h1>
            <p><?= lang('Pqr.ticketTitle') ?></p>
        </div>
    </div>
</header>

<div class="pq-wrap">
    <div class="pq-card">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <div>
                <div class="pq-ref" style="margin:0"><?= esc($request['number']) ?></div>
                <p style="margin:8px 0 0;font-size:13px;color:var(--pq-muted)">
                    <?= esc($typeName) ?> ·
                    <?= esc(date('Y-m-d', strtotime((string) $request['created_at']))) ?>
                    <?php if (!empty($request['is_anonymous'])): ?>
                        · <?= lang('Pqr.anonymous') ?>
                    <?php endif; ?>
                </p>
            </div>
            <span class="pq-badge"><?= lang('Pqr.status_' . $request['status']) ?></span>
        </div>

        <h2 style="margin-top:16px"><?= esc($request['subject']) ?></h2>
        <p style="font-size:13.5px;white-space:pre-line;margin:6px 0 0"><?= esc($request['message']) ?></p>

        <?php if ($due && in_array($request['status'], ['recibida', 'en_gestion'], true)): ?>
            <p style="margin-top:14px;font-size:12.5px;color:var(--pq-muted)">
                <span class="pq-i" style="vertical-align:-2px"><?= $ic['clock'] ?></span>
                <?= lang('Pqr.doneDue', [$due]) ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (!empty($request['answer'])): ?>
        <div class="pq-card" style="border-color:var(--pq-brand)">
            <h2><?= lang('Pqr.ticketAnswer') ?></h2>
            <p class="pq-hint"><?= esc(date('Y-m-d', strtotime((string) $request['answered_at']))) ?></p>
            <p style="font-size:14px;white-space:pre-line;margin:0"><?= esc($request['answer']) ?></p>

            <?php if ($links): ?>
                <div class="pq-files">
                    <p class="pq-files-t"><?= lang('Pqr.ticketFiles') ?></p>
                    <?php foreach ($links as $l): ?>
                        <a class="pq-file" href="<?= esc($l['url']) ?>" target="_blank" rel="noopener">
                            <span class="pq-i"><?= $ic['download'] ?></span>
                            <span><?= esc($l['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="pq-card">
        <h2><?= lang('Pqr.ticketThread') ?></h2>
        <?php if (!$thread): ?>
            <p class="pq-hint" style="margin:0"><?= lang('Pqr.ticketNoEvents') ?></p>
        <?php else: ?>
            <ul class="pq-thread">
                <?php foreach ($thread as $e): ?>
                    <li>
                        <time><?= esc(date('Y-m-d H:i', strtotime((string) $e['created_at']))) ?></time>
                        <p><?= esc((string) $e['body']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <p style="margin-top:16px;font-size:12px;color:var(--pq-muted);text-align:center">
        <?= lang('Pqr.ticketKeep') ?>
    </p>
</div>

<footer class="pq-foot"><?= esc($company['name']) ?></footer>
<?= $this->endSection() ?>
