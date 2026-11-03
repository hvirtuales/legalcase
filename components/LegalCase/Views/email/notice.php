<?php
/** Aviso mínimo de novedad. @var array $company @var string $locale @var string $kind @var string $url */
$brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? '')) ? $company['brand_color'] : '#1f4e79';
$body  = '<p style="margin:0 0 16px">' . esc(lang('Lgc.mailNoticeBody_' . $kind, [], $locale)) . '</p>'
    . '<p style="margin:0 0 16px"><a href="' . esc($url, 'attr') . '" style="display:inline-block;padding:10px 18px;border-radius:8px;background:' . $brand . ';color:#ffffff;text-decoration:none;font-weight:600">'
    . esc(lang('Lgc.mailOpenPortal', [], $locale)) . '</a></p>'
    . '<p style="margin:0;font-size:13px;color:#6b7280">' . esc(lang('Lgc.mailNoticePrivacy', [], $locale)) . '</p>';

echo view('Components\LegalCase\Views\email\_wrap', ['company' => $company, 'locale' => $locale, 'body' => $body]);
