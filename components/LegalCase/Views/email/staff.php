<?php
/** Aviso interno al personal del despacho. Solo el número del asunto, nunca su contenido. @var array $company @var string $kind @var string $number @var string $url */
$brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? '')) ? $company['brand_color'] : '#1f4e79';
$body  = '<p style="margin:0 0 16px">' . esc(lang('Lgc.mailStaffBody_' . $kind, [$number], 'es')) . '</p>'
    . '<p style="margin:0"><a href="' . esc($url, 'attr') . '" style="display:inline-block;padding:10px 18px;border-radius:8px;background:' . $brand . ';color:#ffffff;text-decoration:none;font-weight:600">'
    . esc(lang('Lgc.mailStaffOpen', [], 'es')) . '</a></p>';

echo view('Components\LegalCase\Views\email\_wrap', ['company' => $company, 'locale' => 'es', 'body' => $body]);
