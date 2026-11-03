<?php
/**
 * Firma electrónica de un documento por el cliente. Primero lee el documento; luego declara su
 * nombre (y documento de identidad) y acepta. Puede rechazar con una observación.
 *
 * @var array $sig @var array $matter @var ?array $doc
 */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
ob_start(); ?>
<p><a href="<?= esc(Portal::url('asunto/' . (int) $sig['matter_id']), 'attr') ?>"><?= lgc_icon('back') ?> <?= esc($matter['label'] ?? lang('Lgc.ptBack')) ?></a></p>
<div class="lc-card" style="max-width:640px">
  <h1 class="lc-title"><?= lgc_icon('doc') ?> <?= esc(lang('Lgc.ptSignTitle')) ?></h1>
  <p><strong><?= esc($sig['purpose']) ?></strong></p>
  <?php if ($doc): ?>
    <p><a class="lc-btn lc-btn-ghost" href="<?= esc(Portal::url('documento/' . (int) $doc['id']), 'attr') ?>"><?= lgc_icon('down') ?> <?= esc(lang('Lgc.ptReadDoc', [$doc['name']])) ?></a></p>
  <?php endif; ?>
  <p class="lc-muted lc-small"><?= esc(lang('Lgc.ptSignIntro')) ?></p>
  <form method="post" action="<?= esc(Portal::url('firma/' . (int) $sig['id']), 'attr') ?>">
    <?= csrf_field() ?>
    <label class="lc-label" for="signer_name"><?= esc(lang('Lgc.ptSignerName')) ?></label>
    <input class="lc-in" type="text" id="signer_name" name="signer_name" required minlength="5" maxlength="190" autocomplete="name">
    <label class="lc-label" for="signer_doc"><?= esc(lang('Lgc.fDocNumber')) ?></label>
    <input class="lc-in" type="text" id="signer_doc" name="signer_doc" maxlength="40">
    <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px">
      <input type="checkbox" name="accept" value="1" required style="width:20px;height:20px;margin-top:2px">
      <span><?= esc(lang('Lgc.ptSignAccept')) ?></span>
    </label>
    <p style="margin-top:12px"><button type="submit" name="decision" value="sign" class="lc-btn"><?= lgc_icon('check') ?> <?= esc(lang('Lgc.ptSignNow')) ?></button></p>
  </form>
  <details style="margin-top:8px">
    <summary><?= esc(lang('Lgc.ptSignDecline')) ?></summary>
    <form method="post" action="<?= esc(Portal::url('firma/' . (int) $sig['id']), 'attr') ?>">
      <?= csrf_field() ?>
      <label class="lc-label" for="decline_note"><?= esc(lang('Lgc.ptDeclineReason')) ?></label>
      <textarea class="lc-in" id="decline_note" name="decline_note" maxlength="255"></textarea>
      <p style="margin-top:8px"><button type="submit" name="decision" value="decline" class="lc-btn lc-btn-ghost"><?= esc(lang('Lgc.ptSignDecline')) ?></button></p>
    </form>
  </details>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
