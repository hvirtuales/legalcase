<?php
/**
 * Marco de correo con estilos en línea (los clientes de correo ignoran <style>).
 * Sin datos del asunto: el correo es solo un aviso para entrar al portal (§6.7).
 *
 * @var array $company @var string $locale @var string $body HTML ya escapado
 */
$brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? '')) ? $company['brand_color'] : '#1f4e79';
?>
<!DOCTYPE html>
<html lang="<?= esc($locale, 'attr') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111827">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px">
    <tr><td style="padding:20px 24px;border-bottom:3px solid <?= $brand ?>;font-weight:600;font-size:16px"><?= esc($company['name'] ?? '') ?></td></tr>
    <tr><td style="padding:24px;font-size:15px;line-height:1.55"><?= $body ?></td></tr>
    <tr><td style="padding:16px 24px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280"><?= esc(lang('Lgc.mailFooter', [], $locale)) ?></td></tr>
  </table>
</body>
</html>
