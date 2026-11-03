<?php
/** Enlace de acceso de un solo uso. @var array $company @var string $locale @var string $purpose @var string $url @var int $minutes */
$brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? '')) ? $company['brand_color'] : '#1f4e79';
$body  = '<p style="margin:0 0 16px">' . esc(lang($purpose === 'invite' ? 'Lgc.mailInviteBody' : 'Lgc.mailLoginBody', [(string) ($company['name'] ?? '')], $locale)) . '</p>'
    . '<p style="margin:0 0 16px"><a href="' . esc($url, 'attr') . '" style="display:inline-block;padding:10px 18px;border-radius:8px;background:' . $brand . ';color:#ffffff;text-decoration:none;font-weight:600">'
    . esc(lang('Lgc.mailEnter', [], $locale)) . '</a></p>'
    . '<p style="margin:0 0 8px;font-size:13px;color:#6b7280">' . esc(lang('Lgc.mailExpires', [$minutes], $locale)) . '</p>'
    . '<p style="margin:0;font-size:13px;color:#6b7280">' . esc(lang('Lgc.mailNotYou', [], $locale)) . '</p>';

echo view('Components\LegalCase\Views\email\_wrap', ['company' => $company, 'locale' => $locale, 'body' => $body]);
