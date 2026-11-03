<?php
/**
 * Pantalla principal del asunto para el cliente (§6.3): etapa, última actualización
 * (qué ocurrió / qué significa / qué estamos haciendo / qué necesitamos), próxima
 * comunicación, pendientes, documentos y mensajes. Solo datos PUBLICADOS.
 *
 * @var array $matter @var array $updates @var array $requests @var array $docs @var array $mine
 * @var array $threads @var string $lawyer
 */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
$d      = static fn (?string $v, bool $t = false) => $v && strtotime($v) ? date($t ? 'Y-m-d H:i' : 'Y-m-d', strtotime($v)) : '—';
$last   = $updates[0] ?? null;
$older  = array_slice($updates, 1);
$open   = $matter['status'] !== 'closed';
$mineBy = [];
foreach ($mine as $doc) {
    $mineBy[(int) $doc['request_id']][] = $doc;
}
$secs = ['what_happened', 'what_it_means', 'what_we_do', 'client_action'];
ob_start(); ?>
<p><a href="<?= esc(Portal::url(), 'attr') ?>"><?= lgc_icon('back') ?> <?= esc(lang('Lgc.ptMyMatters')) ?></a></p>
<h1 class="lc-title"><?= esc($matter['label']) ?></h1>
<p class="lc-muted" style="margin-top:-6px"><?= esc(lang('Lgc.ptRef')) ?>: <?= esc($matter['number']) ?> · <?= esc(lang('Lgc.ptLawyer')) ?>: <?= esc($lawyer) ?></p>

<?php if (!(int) $matter['stage_hidden'] && $matter['public_stage']): ?>
  <div class="lc-card">
    <h2><?= esc(lang('Lgc.ptStage')) ?></h2>
    <p style="margin:0"><span class="lc-pill lc-stage"><?= esc($matter['public_stage']) ?></span></p>
    <p class="lc-muted lc-small" style="margin:8px 0 0"><?= esc(lang('Lgc.ptStageNote')) ?></p>
  </div>
<?php endif; ?>

<div class="lc-card">
  <h2><?= lgc_icon('clock') ?> <?= esc(lang('Lgc.ptLastUpdate')) ?></h2>
  <?php if (!$last): ?>
    <p class="lc-muted"><?= esc(lang('Lgc.ptNoUpdatesYet')) ?></p>
  <?php else: ?>
    <p style="margin:0"><strong><?= esc($last['title']) ?></strong></p>
    <p class="lc-muted lc-small" style="margin:2px 0 0"><?= esc($d($last['published_at'], true)) ?></p>
    <?php foreach ($secs as $s): if (trim((string) $last[$s]) === '') { continue; } ?>
      <div class="lc-sec"><h3><?= esc(lang('Lgc.pt_' . $s)) ?></h3><p class="lc-pre"><?= esc($last[$s]) ?></p></div>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="lc-sec">
    <h3><?= esc(lang('Lgc.pt_next')) ?></h3>
    <p style="margin:0"><?= $matter['next_update_due_at'] && $open ? esc($d($matter['next_update_due_at'])) : esc(lang('Lgc.ptNextByPlan')) ?></p>
    <p class="lc-muted lc-small" style="margin:4px 0 0"><?= esc(lang('Lgc.ptNextDisclaimer')) ?></p>
  </div>
</div>

<?php if (!empty($hearings)): ?>
<div class="lc-card" id="audiencias">
  <h2><?= lgc_icon('clock') ?> <?= esc(lang('Lgc.ptHearings')) ?></h2>
  <ul class="lc-list">
    <?php foreach ($hearings as $h): ?>
      <li>
        <div class="lc-row"><strong><?= esc($h['title']) ?></strong><span class="lc-pill"><?= esc(lang('Lgc.hmode_' . $h['mode'])) ?></span></div>
        <p style="margin:4px 0 0"><?= esc($d($h['scheduled_at'], true)) ?><?= $h['status'] === 'postponed' ? ' · ' . esc(lang('Lgc.hstatus_postponed')) : '' ?></p>
        <?php if ($h['location']): ?><p class="lc-muted lc-small" style="margin:2px 0 0"><?= esc($h['location']) ?></p><?php endif; ?>
        <?php if ($h['link']): ?><p style="margin:6px 0 0"><a class="lc-btn lc-btn-ghost" href="<?= esc($h['link'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Lgc.ptJoin')) ?></a></p><?php endif; ?>
        <?php if ((int) $h['client_attendance']): ?><p class="lc-alert lc-warn lc-small" style="margin:8px 0 0"><?= esc(lang('Lgc.ptMustAttend')) ?></p><?php endif; ?>
        <?php if ($h['client_notes']): ?><p class="lc-pre lc-small" style="margin-top:6px"><?= esc($h['client_notes']) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="lc-muted lc-small"><?= esc(lang('Lgc.ptHearingNote')) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($signatures)): ?>
<div class="lc-card" id="firmas">
  <h2><?= lgc_icon('doc') ?> <?= esc(lang('Lgc.ptToSign')) ?></h2>
  <ul class="lc-list">
    <?php foreach ($signatures as $sg): ?>
      <li class="lc-row">
        <span><?= esc($sg['purpose']) ?> <span class="lc-pill"><?= esc(lang('Lgc.sig_' . $sg['status'])) ?></span></span>
        <?php if ($sg['status'] === 'pending'): ?><a class="lc-btn" href="<?= esc(Portal::url('firma/' . (int) $sg['id']), 'attr') ?>"><?= esc(lang('Lgc.ptReviewSign')) ?></a><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="lc-card" id="pendientes">
  <h2><?= lgc_icon('doc') ?> <?= esc(lang('Lgc.ptWhatWeNeed')) ?></h2>
  <?php $pend = array_filter($requests, static fn ($r) => in_array($r['status'], ['pending', 'rejected', 'submitted'], true)); ?>
  <?php if (!$pend): ?>
    <p class="lc-muted"><?= esc(lang('Lgc.ptNothingPending')) ?></p>
  <?php else: ?>
    <ul class="lc-list">
      <?php foreach ($pend as $r): ?>
        <li>
          <div class="lc-row"><strong><?= esc($r['title']) ?></strong><span class="lc-pill"><?= esc(lang('Lgc.ptReq_' . $r['status'])) ?></span></div>
          <?php if ($r['due_at']): ?><p class="lc-muted lc-small" style="margin:2px 0 0"><?= esc(lang('Lgc.ptDueBy', [$d($r['due_at'])])) ?></p><?php endif; ?>
          <?php if ($r['instructions']): ?><p class="lc-pre lc-small" style="margin-top:6px"><?= esc($r['instructions']) ?></p><?php endif; ?>
          <?php if ($r['status'] === 'rejected' && $r['review_note']): ?><p class="lc-alert lc-warn lc-small" style="margin:8px 0 0"><?= esc(lang('Lgc.ptRejectedNote', [$r['review_note']])) ?></p><?php endif; ?>
          <?php foreach ($mineBy[(int) $r['id']] ?? [] as $doc): ?>
            <p class="lc-small" style="margin:6px 0 0"><?= lgc_icon('check') ?> <?= esc(lang('Lgc.ptReceived', [$doc['name'], $d($doc['created_at'], true)])) ?></p>
          <?php endforeach; ?>
          <?php if ($open && in_array($r['status'], ['pending', 'rejected'], true)): ?>
            <form method="post" action="<?= esc(Portal::url('solicitud/' . (int) $r['id'] . '/entregar'), 'attr') ?>" enctype="multipart/form-data" style="margin-top:10px">
              <?= csrf_field() ?>
              <label class="lc-label" for="f-<?= (int) $r['id'] ?>"><?= esc(lang('Lgc.ptChooseFile')) ?></label>
              <input class="lc-in" type="file" id="f-<?= (int) $r['id'] ?>" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt" capture="environment">
              <p class="lc-muted lc-small" style="margin:4px 0 8px"><?= esc(lang('Lgc.ptFileHint')) ?></p>
              <button type="submit" class="lc-btn"><?= lgc_icon('up') ?> <?= esc(lang('Lgc.ptDeliver')) ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="lc-card">
  <h2><?= lgc_icon('doc') ?> <?= esc(lang('Lgc.ptSharedDocs')) ?></h2>
  <?php if (!$docs): ?>
    <p class="lc-muted"><?= esc(lang('Lgc.ptNoDocs')) ?></p>
  <?php else: ?>
    <ul class="lc-list">
      <?php foreach ($docs as $doc): ?>
        <li class="lc-row">
          <span><?= esc($doc['name']) ?> <span class="lc-muted lc-small">(<?= esc($d($doc['shared_at'] ?: $doc['created_at'])) ?>)</span></span>
          <a class="lc-btn lc-btn-ghost" href="<?= esc(Portal::url('documento/' . (int) $doc['id']), 'attr') ?>"><?= lgc_icon('down') ?> <?= esc(lang('Lgc.ptDownload')) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="lc-card" id="mensajes">
  <h2><?= lgc_icon('chat') ?> <?= esc(lang('Lgc.ptMessages')) ?></h2>
  <?php foreach ($threads as $t): ?>
    <details style="border-top:1px solid var(--lc-line);padding-top:6px;margin-top:6px" <?= $t === $threads[0] ? 'open' : '' ?>>
      <summary><?= esc($t['subject']) ?>&nbsp;<span class="lc-pill"><?= esc(lang('Lgc.ptThread_' . $t['status'])) ?></span></summary>
      <?php foreach ($t['messages'] as $msg): $out = $msg['direction'] === 'out'; ?>
        <div class="lc-msg <?= $out ? 'lc-msg-out' : '' ?>">
          <div class="lc-muted lc-small"><?= esc($out ? ((int) $msg['is_auto'] ? lang('Lgc.ptAutoAck') : lang('Lgc.ptFromFirm')) : lang('Lgc.ptFromYou')) ?> · <?= esc($d($msg['created_at'], true)) ?></div>
          <p class="lc-pre"><?= esc($msg['body']) ?></p>
        </div>
      <?php endforeach; ?>
      <?php if ($open && $t['status'] !== 'closed'): ?>
        <form method="post" action="<?= esc(Portal::url('asunto/' . (int) $matter['id'] . '/mensaje'), 'attr') ?>" style="margin-top:8px">
          <?= csrf_field() ?>
          <input type="hidden" name="thread_id" value="<?= (int) $t['id'] ?>">
          <label class="lc-label" for="r-<?= (int) $t['id'] ?>"><?= esc(lang('Lgc.ptReply')) ?></label>
          <textarea class="lc-in" id="r-<?= (int) $t['id'] ?>" name="body" required maxlength="5000"></textarea>
          <p style="margin-top:8px"><button type="submit" class="lc-btn"><?= esc(lang('Lgc.send')) ?></button></p>
        </form>
      <?php endif; ?>
    </details>
  <?php endforeach; ?>

  <?php if ($open): ?>
    <form method="post" action="<?= esc(Portal::url('asunto/' . (int) $matter['id'] . '/mensaje'), 'attr') ?>" style="margin-top:14px">
      <?= csrf_field() ?>
      <h3 style="font-size:1rem;margin-bottom:4px"><?= esc(lang('Lgc.ptNewMessage')) ?></h3>
      <p class="lc-muted lc-small" style="margin:0"><?= esc(lang('Lgc.ptNewMessageHint')) ?></p>
      <label class="lc-label" for="subject"><?= esc(lang('Lgc.fSubject')) ?></label>
      <input class="lc-in" type="text" id="subject" name="subject" maxlength="190">
      <label class="lc-label" for="body"><?= esc(lang('Lgc.fMessage')) ?></label>
      <textarea class="lc-in" id="body" name="body" required maxlength="5000"></textarea>
      <p style="margin-top:8px"><button type="submit" class="lc-btn"><?= lgc_icon('chat') ?> <?= esc(lang('Lgc.send')) ?></button></p>
    </form>
  <?php endif; ?>
</div>

<?php if ($older): ?>
  <details class="lc-card">
    <summary><?= esc(lang('Lgc.ptHistory', [count($older)])) ?></summary>
    <ul class="lc-list" style="margin-top:8px">
      <?php foreach ($older as $u): ?>
        <li>
          <strong><?= esc($u['title']) ?></strong> <span class="lc-muted lc-small">(<?= esc($d($u['published_at'])) ?>)</span>
          <p class="lc-pre lc-small" style="margin-top:4px"><?= esc($u['what_happened']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </details>
<?php endif; ?>

<p class="lc-muted lc-small"><?= esc(lang('Lgc.ptDisclaimer')) ?></p>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
