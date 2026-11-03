<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * El acuse de la denuncia.
 *
 * **No dice si entró.** Quien repite desde la misma red ve exactamente esta pantalla,
 * igual que quien denuncia por primera vez: decirle «ya denunciaste» a alguien que
 * comparte IP —una oficina, un café— le confirmaría lo que hizo otra persona.
 *
 * Tampoco promete una respuesta. La plataforma revisa y actúa; comprometerse a
 * contestar siempre sería prometer algo que no depende solo de ella.
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<div class="ll-card ll-pad" style="max-width:620px;margin:0 auto;text-align:center">
  <p style="font-size:34px;margin:0 0 6px;color:var(--ll-gold)"><?= $icon('check') ?></p>
  <h1 style="margin:0 0 8px;font-size:24px"><?= esc(lang('Lfl.repDoneTitle')) ?></h1>
  <p class="ll-muted" style="margin:0 0 6px"><?= esc(lang('Lfl.repDoneBody')) ?></p>
  <p class="ll-small ll-muted" style="margin:0 0 18px"><?= esc(lang('Lfl.repDoneNext')) ?></p>
  <a class="ll-btn ll-btn-ghost" href="<?= base_url('/') ?>"><?= esc(lang('Lfl.dirBackHome')) ?></a>
</div>
<?= $this->endSection() ?>
