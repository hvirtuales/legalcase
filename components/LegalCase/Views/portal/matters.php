<?php
/**
 * Mis asuntos: cada uno con su última actualización publicada y lo que se espera del
 * cliente. Preferencias de idioma y avisos al final.
 *
 * @var array $matters @var array $account @var string $help
 */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
$d = static fn (?string $v, bool $t = false) => $v && strtotime($v) ? date($t ? 'Y-m-d H:i' : 'Y-m-d', strtotime($v)) : '—';
ob_start(); ?>
<h1 class="lc-title"><?= esc(lang('Lgc.ptMyMatters')) ?></h1>

<?php if (!$matters): ?>
  <div class="lc-card"><p class="lc-muted"><?= esc(lang('Lgc.ptNoMatters')) ?></p></div>
<?php endif; ?>

<?php foreach ($matters as $m): ?>
  <a href="<?= esc(Portal::url('asunto/' . (int) $m['id']), 'attr') ?>" style="text-decoration:none;color:inherit;display:block">
    <div class="lc-card">
      <div class="lc-row">
        <h2 style="margin:0"><?= lgc_icon('case') ?> <?= esc($m['label']) ?></h2>
        <?php if (!(int) $m['stage_hidden'] && $m['public_stage']): ?><span class="lc-pill lc-stage"><?= esc($m['public_stage']) ?></span><?php endif; ?>
      </div>
      <p class="lc-muted lc-small" style="margin:6px 0 0"><?= esc(lang('Lgc.ptRef')) ?>: <?= esc($m['number']) ?><?= $m['status'] === 'closed' ? ' · ' . esc(lang('Lgc.ptClosed')) : '' ?></p>
      <?php if ($m['last_update']): ?>
        <p style="margin:10px 0 0"><strong><?= esc(lang('Lgc.ptLastUpdate')) ?>:</strong> <?= esc($m['last_update']['title']) ?> <span class="lc-muted lc-small">(<?= esc($d($m['last_update']['published_at'])) ?>)</span></p>
      <?php else: ?>
        <p class="lc-muted" style="margin:10px 0 0"><?= esc(lang('Lgc.ptNoUpdatesYet')) ?></p>
      <?php endif; ?>
      <?php if ((int) $m['pending'] > 0): ?>
        <p class="lc-alert lc-warn" style="margin:10px 0 0"><?= lgc_icon('doc') ?> <?= esc(lang('Lgc.ptPendingCount', [(int) $m['pending']])) ?></p>
      <?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>

<?php if (trim($help) !== ''): ?>
  <div class="lc-card"><h2><?= lgc_icon('info') ?> <?= esc(lang('Lgc.ptHelp')) ?></h2><p class="lc-pre"><?= esc($help) ?></p></div>
<?php endif; ?>

<details class="lc-card">
  <summary><?= esc(lang('Lgc.ptDataRights')) ?></summary>
  <p class="lc-muted lc-small"><?= esc(lang('Lgc.ptDataRightsHint')) ?></p>
  <form method="post" action="<?= esc(Portal::url('datos'), 'attr') ?>">
    <?= csrf_field() ?>
    <label class="lc-label" for="right"><?= esc(lang('Lgc.ptDataRightsType')) ?></label>
    <select class="lc-in" id="right" name="right">
      <?php foreach (array_keys(\Components\LegalCase\Libraries\MessageService::DATA_RIGHTS) as $r): ?>
        <option value="<?= esc($r, 'attr') ?>"><?= esc(lang('Lgc.right_' . $r)) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="lc-label" for="detail"><?= esc(lang('Lgc.fDetail')) ?></label>
    <textarea class="lc-in" id="detail" name="detail" required maxlength="5000"></textarea>
    <p style="margin-top:12px"><button type="submit" class="lc-btn"><?= esc(lang('Lgc.send')) ?></button></p>
  </form>
</details>

<details class="lc-card">
  <summary><?= esc(lang('Lgc.ptPrefs')) ?></summary>
  <form method="post" action="<?= esc(Portal::url('preferencias'), 'attr') ?>">
    <?= csrf_field() ?>
    <label class="lc-label" for="locale"><?= esc(lang('Lgc.fLocale')) ?></label>
    <select class="lc-in" id="locale" name="locale">
      <option value="es" <?= $account['locale'] === 'es' ? 'selected' : '' ?>>Español</option>
      <option value="en" <?= $account['locale'] === 'en' ? 'selected' : '' ?>>English</option>
    </select>
    <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px">
      <input type="checkbox" name="notify_email" value="1" <?= (int) $account['notify_email'] ? 'checked' : '' ?> style="width:20px;height:20px;margin-top:2px">
      <span><?= esc(lang('Lgc.ptNotifyEmail')) ?><br><span class="lc-muted lc-small"><?= esc(lang('Lgc.ptNotifyEmailHint')) ?></span></span>
    </label>
    <p style="margin-top:12px"><button type="submit" class="lc-btn"><?= esc(lang('Lgc.save')) ?></button></p>
  </form>
</details>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
