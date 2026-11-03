<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * Consulta del estado de un caso, en la página de la firma.
 *
 * ── Tres decisiones que no son de estilo ──────────────────────────────────────
 *
 * **(a) El texto es el de USCIS, palabra por palabra.** No se traduce, no se resume y
 * no se clasifica en «bien» o «mal». USCIS devuelve el estado en español además de en
 * inglés, así que se enseña el del idioma del visitante **con sus palabras**. Escribir
 * nosotros «tu caso va lento» sería interpretar un trámite legal, que es exactamente lo
 * que el aviso del directorio promete no hacer.
 *
 * **(b) Si el dato no es de ahora, se dice.** Un estado servido de la caché por un
 * fallo de red o por cupo agotado lleva su fecha delante. Sin ese aviso, un dato de
 * hace horas se lee como si fuera de este momento, y en un trámite migratorio esa
 * diferencia importa.
 *
 * **(c) La llamada a la acción va DESPUÉS del resultado, no antes.** Quien llega aquí
 * viene a saber de su caso; pedirle que contrate antes de responderle sería usar su
 * preocupación como anzuelo. Primero se le responde, y luego se le ofrece ayuda.
 *
 * @var array      $company
 * @var array|null $result  ['ok','message','case','stale','checked_at']
 * @var string     $stamp
 * @var bool       $hasGd
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$url  = base_url('firma/' . rawurlencode((string) $company['slug']) . '/caso');

// El idioma del visitante decide qué texto de USCIS se enseña. Sin español, el inglés.
$esES = str_starts_with(service('request')->getLocale(), 'es');
$txt  = static function (?array $c, string $campo) use ($esES): ?string {
    if ($c === null) {
        return null;
    }

    return $esES ? ($c[$campo . '_es'] ?? $c[$campo] ?? null) : ($c[$campo] ?? null);
};

$caso = $result['case'] ?? null;
$fmt  = static fn (?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '—';

// La identidad de la firma, con el mismo criterio que su ficha: color validado contra
// un patrón y emitido crudo (`$…Hex`), porque `esc()` lo dejaría inválido.
$brandHex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? ''))
    ? (string) $company['brand_color']
    : '#bf8f23';

$logo = '';
if (! empty($company['logo'])) {
    $logo = preg_match('~^https?://~i', (string) $company['logo'])
        ? (string) $company['logo']
        : (function_exists('media_url') ? media_url((string) $company['logo']) : base_url(ltrim((string) $company['logo'], '/')));
}
$iniciales = \Components\LeadFlow\Models\ProfileModel::initials((string) $company['name']);
?>
<section class="ll-card" style="overflow:hidden;margin-bottom:18px">
  <div class="ll-biz-stripe" style="background:<?= $brandHex ?>"></div>
  <div class="ll-biz-id">
    <span class="ll-biz-logo">
      <?php if ($logo !== ''): ?>
        <img src="<?= esc($logo, 'attr') ?>" alt="" width="64" height="64">
      <?php else: ?>
        <?= esc($iniciales) ?>
      <?php endif; ?>
    </span>
    <div style="min-width:0;flex:1">
      <h1 style="margin:0 0 2px;font-size:clamp(19px,3vw,24px)"><?= esc(lang('Lfl.uscPubHeading')) ?></h1>
      <p class="ll-small ll-muted" style="margin:0"><?= esc(lang('Lfl.uscPubSub', [$company['name']])) ?></p>
    </div>
  </div>
</section>

<div class="ll-split">
  <div>
    <form class="ll-card ll-pad" method="post" action="<?= esc($url, 'attr') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

      <div class="ll-hp" aria-hidden="true">
        <label><?= esc(lang('Lfl.pubHoneypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <label class="ll-field" style="margin-bottom:6px">
        <span class="ll-label"><?= esc(lang('Lfl.uscPubReceipt')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="text" name="receipt" maxlength="13" required
          autocomplete="off" spellcheck="false" style="text-transform:uppercase"
          placeholder="<?= esc(lang('Lfl.uscPubReceiptPh'), 'attr') ?>"
          value="<?= esc(old('receipt'), 'attr') ?>">
      </label>
      <p class="ll-small ll-muted" style="margin:0 0 14px"><?= esc(lang('Lfl.uscPubReceiptHint')) ?></p>

      <?php if ($hasGd): ?>
        <div class="ll-row" style="margin-bottom:14px;align-items:flex-end">
          <img src="<?= esc($url . '/captcha', 'attr') ?>" alt="" data-captcha hidden width="150" height="46"
            style="border:1px solid var(--ll-line);border-radius:8px;background:#fff">
          <label class="ll-field" style="max-width:200px">
            <span class="ll-label"><?= esc(lang('Lfl.pubCaptcha')) ?> <span class="ll-req">*</span></span>
            <input class="ll-input" type="text" name="captcha" inputmode="numeric" autocomplete="off" required>
          </label>
        </div>
        <script>
        (function () {
          var img = document.querySelector('[data-captcha]');
          if (!img) { return; }
          fetch(<?= json_encode($url . '/captcha') ?>, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.img) { img.src = d.img; img.hidden = false; } })
            .catch(function () {});
        })();
        </script>
      <?php endif; ?>

      <button class="ll-btn" type="submit"><?= $icon('search') ?><?= esc(lang('Lfl.uscPubSubmit')) ?></button>
    </form>

    <?php if ($result !== null && ! $result['ok']): ?>
      <div class="ll-alert ll-alert-bad" style="margin-top:18px"><?= esc($result['message']) ?></div>
    <?php endif; ?>

    <?php if ($caso !== null): ?>
      <section class="ll-card ll-pad" style="margin-top:18px">
        <?php // Un dato que no es de ahora lo dice ANTES de enseñarlo. ?>
        <?php if (! empty($result['stale'])): ?>
          <div class="ll-alert" style="margin-bottom:14px">
            <?= esc($result['message'] ?: lang('Lfl.uscServedStale')) ?>
          </div>
        <?php endif; ?>

        <p class="ll-kicker" style="margin-bottom:8px"><?= esc($caso['receipt']) ?></p>
        <h2 style="margin:0 0 6px;font-size:21px"><?= esc($txt($caso, 'status') ?? lang('Lfl.uscPubNoStatus')) ?></h2>

        <?php if ($d = $txt($caso, 'detail')): ?>
          <p style="margin:0 0 14px"><?= esc($d) ?></p>
        <?php endif; ?>

        <dl class="ll-kv" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
          <?php foreach ([
              'uscPubForm'      => $caso['form_type'] ?: '—',
              'uscPubSubmitted' => $fmt($caso['submitted_at']),
              'uscPubUpdated'   => $fmt($caso['updated_at']),
          ] as $clave => $valor): ?>
            <div>
              <dt><?= esc(lang('Lfl.' . $clave)) ?></dt>
              <dd><?= esc($valor) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>

        <?php if (! empty($caso['history'])): ?>
          <h3 style="margin:18px 0 8px;font-size:16px"><?= esc(lang('Lfl.uscPubHistory')) ?></h3>
          <ul class="ll-svc">
            <?php foreach ($caso['history'] as $h): ?>
              <li>
                <p class="ll-small ll-muted" style="margin:0"><?= esc($fmt($h['date'] ?? null)) ?></p>
                <p style="margin:2px 0 0"><?= esc($esES ? ($h['text_es'] ?? $h['text'] ?? '') : ($h['text'] ?? '')) ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if (! empty($result['checked_at'])): ?>
          <p class="ll-small ll-muted" style="margin:14px 0 0">
            <?= esc(lang('Lfl.uscCheckedAt', [date('d/m/Y H:i', strtotime((string) $result['checked_at']))])) ?>
          </p>
        <?php endif; ?>

        <p class="ll-legal" style="margin-top:12px"><?= esc(lang('Lfl.uscPubDisclaimer')) ?></p>
      </section>

      <?php // La ayuda se ofrece DESPUÉS de responder, nunca antes. ?>
      <section class="ll-band" style="margin-top:18px">
        <div>
          <strong><?= esc(lang('Lfl.uscPubHelpTitle')) ?></strong><br>
          <span class="ll-small ll-muted"><?= esc(lang('Lfl.uscPubHelpBody', [$company['name']])) ?></span>
        </div>
        <a class="ll-btn" href="<?= esc(base_url('firma/' . rawurlencode((string) $company['slug'])) . '#consulta', 'attr') ?>">
          <?= $icon('mail') ?><?= esc(lang('Lfl.dirFirmAsk')) ?>
        </a>
      </section>
    <?php endif; ?>
  </div>

  <aside>
    <div class="ll-card ll-pad">
      <p style="margin:0 0 8px;font-weight:600"><?= esc(lang('Lfl.uscPubAboutTitle')) ?></p>
      <ul class="ll-list ll-small">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <li><?= esc(lang('Lfl.uscPubAbout' . $i)) ?></li>
        <?php endfor; ?>
      </ul>
      <p class="ll-small" style="margin:12px 0 0">
        <a href="<?= esc(base_url('firma/' . rawurlencode((string) $company['slug'])), 'attr') ?>">
          <?= esc(lang('Lfl.repBackToFirm')) ?>
        </a>
      </p>
    </div>
    <p class="ll-legal"><?= esc(lang('Lfl.uscPubPrivacy')) ?></p>
  </aside>
</div>
<?= $this->endSection() ?>
