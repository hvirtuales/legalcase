<?= $this->extend('Components\Servicios\Views\public\_card_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * Tarjeta digital del vendedor: lo que se comparte con el cliente.
 *
 * **Autosuficiente**: usa el lienzo propio del componente (`_card_layout.php`,
 * con toda la hoja de estilos) e iconos SVG en línea (`_icons.php`). No depende
 * del CSS ni de los iconos del núcleo, así que la carpeta del componente se
 * puede llevar a otra plataforma sin que la tarjeta se rompa.
 *
 * Formato **CR80 vertical** (54 × 86 mm) con giro 3D: apaisada quedaba una
 * franja ilegible en el teléfono, que es donde se mira. Las acciones viven en
 * una barra fija inferior, fuera de la cara que gira.
 *
 * @var array  $seller  @var array $company @var array $palette
 * @var string $wa      teléfono listo para wa.me @var string $url @var ?string $qr
 */
$ic = require __DIR__ . '/_icons.php';

$web = trim((string) ($company['website'] ?? ''));
if ($web !== '' && !preg_match('#^https?://#i', $web)) {
    $web = 'https://' . $web;
}
$webLabel = $web !== '' ? preg_replace('#^https?://(www\.)?#i', '', rtrim($web, '/')) : '';
$phone    = trim((string) ($seller['phone'] ?: ($company['contact_phone'] ?? '')));
$email    = trim((string) ($seller['email'] ?: ($company['contact_email'] ?? '')));
$place    = trim(trim((string) ($company['address'] ?? '')) . ' · ' . trim((string) ($company['city'] ?? '')), ' ·');
$cover    = !empty($company['banner']) ? media_url($company['banner']) : null;

// Redes de la empresa (las que tengan enlace), ya normalizadas al guardarlas.
$socials = [];
foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'] as $net => $label) {
    $link = \App\Models\CompanyModel::socialUrl($net, $company[$net] ?? null);
    if ($link !== '') {
        $socials[] = ['net' => $net, 'label' => $label, 'url' => $link];
    }
}
?>

<div class="dc-stage">
    <div class="dc-wrap">
        <div class="dc-card" id="dcard">

            <!-- ==================== FRENTE: quién es ==================== -->
            <div class="dc-face dc-front">
                <div class="dc-scroll">
                    <!-- Portada: banner de la empresa o su color de marca. El logo
                         NO va aquí: la marca ya está en el reverso, a tamaño. -->
                    <div class="dc-cover">
                        <?php if ($cover): ?>
                            <img src="<?= $cover ?>" alt="">
                            <div class="dc-cover-fade"></div>
                        <?php endif; ?>
                    </div>

                    <div class="dc-body">
                        <div class="dc-avatar">
                            <?php if (!empty($seller['photo'])): ?>
                                <img src="<?= media_url($seller['photo']) ?>" alt="">
                            <?php else: ?>
                                <span class="dc-i"><?= $ic['user'] ?></span>
                            <?php endif; ?>
                        </div>

                        <h1 class="dc-name"><?= esc($seller['name']) ?></h1>
                        <div class="dc-accent"></div>
                        <?php if (!empty($seller['position'])): ?>
                            <p class="dc-role"><?= esc($seller['position']) ?></p>
                        <?php endif; ?>
                        <p class="dc-org"><?= esc($company['name']) ?></p>

                        <!-- Contacto: cada fila es un enlace vivo -->
                        <div class="dc-rows">
                            <?php if ($email !== ''): ?>
                                <a class="dc-row" href="mailto:<?= esc($email, 'attr') ?>">
                                    <span class="dc-chip dc-i"><?= $ic['mail'] ?></span>
                                    <span><?= esc($email) ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($phone !== ''): ?>
                                <a class="dc-row" href="tel:+<?= esc(preg_replace('/\D+/', '', $phone), 'attr') ?>">
                                    <span class="dc-chip dc-i"><?= $ic['phone'] ?></span>
                                    <span><?= esc($phone) ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($wa !== ''): ?>
                                <a class="dc-row" href="https://wa.me/<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener">
                                    <span class="dc-chip dc-i"><?= $ic['whatsapp'] ?></span>
                                    <span>WhatsApp</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($web !== ''): ?>
                                <a class="dc-row" href="<?= esc($web, 'attr') ?>" target="_blank" rel="noopener">
                                    <span class="dc-chip dc-i"><?= $ic['globe'] ?></span>
                                    <span><?= esc($webLabel) ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($place !== ''): ?>
                                <div class="dc-row">
                                    <span class="dc-chip dc-i"><?= $ic['pin'] ?></span>
                                    <span><?= esc($place) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($socials): ?>
                            <div class="dc-socials">
                                <?php foreach ($socials as $s): ?>
                                    <a class="dc-social dc-i" href="<?= esc($s['url'], 'attr') ?>" target="_blank"
                                        rel="noopener" aria-label="<?= esc($s['label'], 'attr') ?>"
                                        title="<?= esc($s['label'], 'attr') ?>"><?= $ic[$s['net']] ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- Pie a sangre con el color de la empresa. Existe siempre: es
                     el sitio del botón de girar, aunque no haya descripción. -->
                <div class="dc-foot">
                    <span><?= esc($company['description'] ?? '') ?></span>
                    <button type="button" class="dc-flip" aria-label="<?= esc(lang('Quo.cardFlip'), 'attr') ?>">
                        <span class="dc-i dc-pulse"><?= $ic['flip'] ?></span>
                    </button>
                </div>
            </div>

            <!-- ==================== REVERSO: la empresa ==================== -->
            <div class="dc-face dc-back">
                <div class="dc-backbody">
                    <!-- La empresa se presenta ARRIBA del QR: logo, línea fina,
                         nombre, descripción y sitio web. -->
                    <?php if (!empty($company['logo'])): ?>
                        <img class="dc-orglogo" src="<?= media_url($company['logo']) ?>" alt="">
                    <?php endif; ?>
                    <div class="dc-rule"></div>
                    <h2 class="dc-orgname"><?= esc($company['name']) ?></h2>
                    <?php if (!empty($company['description'])): ?>
                        <p class="dc-orgbio"><?= esc($company['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($webLabel !== ''): ?>
                        <?php if ($web !== ''): ?>
                            <a class="dc-web" href="<?= esc($web, 'attr') ?>" target="_blank" rel="noopener"><?= esc($webLabel) ?></a>
                        <?php else: ?>
                            <p class="dc-web"><?= esc($webLabel) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($qr): ?>
                        <img class="dc-qr" src="<?= $qr ?>" alt="" style="margin-top:20px">
                        <p class="dc-scan"><?= lang('Quo.cardScan') ?></p>
                    <?php endif; ?>

                    <?php if ($socials): ?>
                        <div class="dc-socials dc-socials-back">
                            <?php foreach ($socials as $s): ?>
                                <a class="dc-social dc-i" href="<?= esc($s['url'], 'attr') ?>" target="_blank"
                                    rel="noopener" aria-label="<?= esc($s['label'], 'attr') ?>"><?= $ic[$s['net']] ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- El mismo pie del frente: cierra las dos caras igual y aloja
                     el botón de girar. Aquí sin texto — nombre, descripción y
                     web ya están arriba del QR. -->
                <div class="dc-foot">
                    <span></span>
                    <button type="button" class="dc-flip" aria-label="<?= esc(lang('Quo.cardFlip'), 'attr') ?>">
                        <span class="dc-i"><?= $ic['flip'] ?></span>
                    </button>
                </div>
            </div>
        </div>

        <p class="dc-hint"><?= lang('Quo.cardFlipHint') ?></p>
    </div>
</div>

<!-- Acciones: barra fija, pulgar-amigable y fuera de la cara que gira. -->
<div class="dc-bar">
    <div class="dc-bar-in">
        <a class="dc-act" href="<?= esc($url, 'attr') ?>/vcf">
            <span class="dc-i"><?= $ic['download'] ?></span><b><?= lang('Quo.cardSaveShort') ?></b>
        </a>
        <?php if ($wa !== ''): ?>
            <a class="dc-act" href="https://wa.me/<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener">
                <span class="dc-i"><?= $ic['whatsapp'] ?></span><b><?= lang('Quo.cardWhatsapp') ?></b>
            </a>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
            <a class="dc-act" href="mailto:<?= esc($email, 'attr') ?>">
                <span class="dc-i"><?= $ic['mail'] ?></span><b><?= lang('Quo.cardEmail') ?></b>
            </a>
        <?php endif; ?>
        <?php if ($qr): ?>
            <button type="button" class="dc-act" id="dcard-qr-open">
                <span class="dc-i"><?= $ic['qr'] ?></span><b>QR</b>
            </button>
        <?php endif; ?>
        <button type="button" class="dc-act" id="dcard-share"
            data-url="<?= esc($url, 'attr') ?>" data-title="<?= esc($seller['name'], 'attr') ?>">
            <span class="dc-i"><?= $ic['share'] ?></span><b><?= lang('Quo.cardShareShort') ?></b>
        </button>
    </div>
</div>

<?php if ($qr): ?>
    <!-- QR a pantalla completa: para escanearlo desde el portátil -->
    <div class="dc-overlay" id="dcard-qr">
        <div class="dc-modal">
            <img src="<?= $qr ?>" alt="">
            <strong><?= esc($seller['name']) ?></strong>
            <small><?= lang('Quo.cardScanFull') ?></small>
        </div>
    </div>
<?php endif; ?>

<!-- Hoja de compartir: solo se usa si el navegador no trae la nativa -->
<div class="dc-overlay dc-sheet-wrap" id="dcard-sheet">
    <div class="dc-sheet">
        <div class="dc-grab"></div>
        <h2><?= lang('Quo.cardShareTitle') ?></h2>

        <a class="dc-opt" target="_blank" rel="noopener"
            href="https://wa.me/?text=<?= esc(rawurlencode(lang('Quo.cardShareText', [$seller['name']]) . ' ' . $url), 'attr') ?>">
            <span class="dc-chip dc-chip-wa dc-i"><?= $ic['whatsapp'] ?></span>
            <div><b>WhatsApp</b></div>
        </a>
        <button type="button" class="dc-opt" id="dcard-copy">
            <span class="dc-chip dc-i"><?= $ic['link'] ?></span>
            <div>
                <b id="dcard-copy-label"><?= lang('Quo.cardShare') ?></b>
                <small><?= esc($url) ?></small>
            </div>
        </button>
    </div>
</div>

<script>
(function () {
    var card   = document.getElementById('dcard');
    var shareB = document.getElementById('dcard-share');
    var url    = shareB.dataset.url;
    var title  = shareB.dataset.title;

    // --- giro ---------------------------------------------------------------
    var wrap = card.parentNode;
    var turning;

    function flip() {
        card.classList.toggle('is-flipped');

        // `is-turning` dura lo que el giro: encoge el envoltorio y abre la
        // sombra. Se reinicia en cada clic para que dos giros seguidos no se
        // queden a medias.
        wrap.classList.remove('is-turning');
        card.classList.remove('is-turning');
        void wrap.offsetWidth;                       // fuerza el reinicio de la animación
        wrap.classList.add('is-turning');
        card.classList.add('is-turning');
        clearTimeout(turning);
        turning = setTimeout(function () {
            wrap.classList.remove('is-turning');
            card.classList.remove('is-turning');
        }, 800);
    }

    Array.prototype.forEach.call(document.querySelectorAll('.dc-flip'), function (b) {
        b.addEventListener('click', function (e) { e.stopPropagation(); flip(); });
    });

    // Tocar la tarjeta también la gira, salvo sobre un enlace o un botón: quien
    // quiso escribir un correo no espera que la tarjeta se voltee.
    card.addEventListener('click', function (e) {
        if (!e.target.closest('a, button')) { flip(); }
    });

    // --- capas (QR y hoja de compartir) --------------------------------------
    function overlay(el, on) { if (el) { el.classList.toggle('is-open', on); } }

    var qr    = document.getElementById('dcard-qr');
    var sheet = document.getElementById('dcard-sheet');
    var qrBtn = document.getElementById('dcard-qr-open');

    if (qrBtn) { qrBtn.addEventListener('click', function () { overlay(qr, true); }); }
    [qr, sheet].forEach(function (el) {
        if (el) { el.addEventListener('click', function (e) { if (e.target === el) { overlay(el, false); } }); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { overlay(qr, false); overlay(sheet, false); }
    });

    // --- compartir ----------------------------------------------------------
    shareB.addEventListener('click', function () {
        // El teléfono ya tiene su propia hoja de compartir; la nuestra es el respaldo.
        if (navigator.share) {
            navigator.share({ title: title, url: url }).catch(function () {});

            return;
        }
        overlay(sheet, true);
    });

    var copy  = document.getElementById('dcard-copy');
    var label = document.getElementById('dcard-copy-label');
    copy.addEventListener('click', function () {
        var done = function () {
            label.textContent = <?= json_encode(lang('Quo.cardCopied')) ?>;
            setTimeout(function () { label.textContent = <?= json_encode(lang('Quo.cardShare')) ?>; }, 2000);
        };
        // navigator.clipboard no existe fuera de HTTPS: se usa el textarea de siempre.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(done);
        } else {
            var t = document.createElement('textarea');
            t.value = url; t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); done(); } catch (err) { window.prompt('', url); }
            document.body.removeChild(t);
        }
    });
}());
</script>
<?= $this->endSection() ?>
