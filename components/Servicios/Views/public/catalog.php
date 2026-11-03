<?= $this->extend('Components\Servicios\Views\public\_shop_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * La vitrina: el cliente elige cantidades y, en «Mi pedido», revisa la orden,
 * registra sus datos y la envía — todo en la misma capa.
 *
 * La **descripción no se muestra en la rejilla** (a propósito: las tarjetas
 * quedan parejas y se leen de un vistazo); vive en el detalle que abre al tocar
 * la foto. El precio que se ve es el del **valor** configurado en el catálogo; el
 * servidor lo vuelve a leer al recibir el pedido, así que del navegador solo
 * viajan producto y cantidad.
 *
 * @var array   $catalog @var array $seller @var array $company @var array $items
 * @var string  $token   @var ?string $qr  QR de esta misma URL @var string $url
 */
$ic    = require __DIR__ . '/_icons.php';
$money = static fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.');
$show  = !empty($catalog['show_prices']);

// Precio neto (con el descuento del valor ya aplicado): es lo que el cliente
// paga por unidad, sin letra pequeña.
$net = static function (array $p): float {
    $unit = (float) $p['unit_price'];
    $disc = $p['discount_type'] === '$'
        ? min((float) $p['discount_value'], $unit)
        : $unit * ((float) $p['discount_value'] / 100);

    return max(0, $unit - $disc);
};
?>

<!-- Banner: solo el logo y el título del catálogo. Quién vende y quién atiende
     están a un toque, en la tarjeta de la empresa: aquí estorbaban. -->
<header class="sh-top">
    <div class="sh-wrap">
        <div class="sh-hero">
            <button type="button" class="sh-logobtn" data-open="aboutModal"
                aria-label="<?= esc(lang('Quo.shopAbout'), 'attr') ?>" title="<?= esc(lang('Quo.shopAbout'), 'attr') ?>">
                <?php if (!empty($company['logo'])): ?>
                    <img class="sh-logo" src="<?= media_url($company['logo']) ?>" alt="">
                <?php else: ?>
                    <span class="sh-i"><?= $ic['company'] ?></span>
                <?php endif; ?>
            </button>
            <div class="sh-hero-txt">
                <h1 class="sh-title"><?= esc($catalog['name']) ?></h1>
            </div>
        </div>

        <label class="sh-search">
            <span class="sh-i"><?= $ic['search'] ?></span>
            <input type="search" id="shopSearch" autocomplete="off"
                placeholder="<?= esc(lang('Quo.shopSearch'), 'attr') ?>">
            <button type="button" id="shopSearchClear" class="sh-i" hidden
                aria-label="<?= esc(lang('Quo.clear'), 'attr') ?>"><?= $ic['close'] ?></button>
        </label>

        <?php if ($cats): ?>
            <!-- Categorías en carrusel: se desplazan con el dedo, sin envolver -->
            <div class="sh-cats" id="shopCats">
                <button type="button" class="is-on" data-cat=""><?= lang('Quo.all') ?></button>
                <?php foreach ($cats as $cid => $cname): ?>
                    <button type="button" data-cat="<?= (int) $cid ?>"><?= esc($cname) ?></button>
                <?php endforeach; ?>
                <?php if ($hasLoose): ?>
                    <button type="button" data-cat="0"><?= lang('Quo.uncategorized') ?></button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</header>

<div class="sh-wrap">
    <?php // La presentación del catálogo (titular e introducción) vive ahora en la
          // tarjeta que abre el logo: en la portada empujaba los productos hacia abajo. ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="sh-alert"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <!-- Un solo formulario: la rejilla aporta las cantidades y la capa «Mi
         pedido» los datos del cliente y el botón de enviar. -->
    <form method="post" action="<?= base_url('servicios/catalogo/' . $token . '/pedido') ?>" id="shopForm">
        <?= csrf_field() ?>

        <?php if (!$items): ?>
            <div class="sh-alert"><?= lang('Quo.shopEmpty') ?></div>
        <?php else: ?>
            <!-- Primera página pintada en el servidor (buena primera impresión y
                 rastreable); el resto entra por `items` al bajar. -->
            <div class="sh-grid" id="shopGrid">
                <?php foreach ($items as $p): ?>
                    <?= view('Components\Servicios\Views\public\_card', [
                        'p' => $p, 'ic' => $ic, 'show' => $show, 'net' => $net, 'money' => $money,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <p id="noHits" class="sh-alert" hidden><?= lang('Quo.shopNoHits') ?></p>

            <!-- Centinela del desplazamiento infinito -->
            <div id="shopMore" class="sh-more" data-page="1" data-pages="<?= (int) $page['pages'] ?>">
                <span class="sh-spin" aria-hidden="true"></span>
                <span><?= lang('Quo.shopLoading') ?></span>
            </div>
            <p id="shopCount" class="sh-count"><?= lang('Quo.shopShowing', [count($items), (int) $page['total']]) ?></p>

            <!-- ============ Mi pedido: orden + datos + enviar ============ -->
            <div class="sh-modal" id="cartModal">
                <div class="sh-modal-box">
                    <button type="button" class="sh-modal-close sh-i" data-close
                        aria-label="<?= esc(lang('Quo.shopClose'), 'attr') ?>"><?= $ic['close'] ?></button>
                    <div style="padding:22px">
                        <h3 style="margin:0 0 14px;font-size:18px;font-weight:800"><?= lang('Quo.shopOrderTitle') ?></h3>

                        <!-- Dos pestañas: lo que lleva y quién lo pide -->
                        <div class="sh-tabs">
                            <button type="button" data-tab="paneItems" class="is-on"><?= lang('Quo.shopTabItems') ?></button>
                            <button type="button" data-tab="paneData"><?= lang('Quo.shopYourData') ?></button>
                        </div>

                        <div class="sh-pane" id="paneItems">
                        <p id="cartEmpty" style="color:var(--sh-muted);font-size:13px"><?= lang('Quo.shopOrderEmpty') ?></p>

                        <div id="cartWrap" hidden style="overflow-x:auto">
                            <table class="sh-cart">
                                <thead>
                                    <tr>
                                        <th colspan="2"><?= lang('Quo.colProduct') ?></th>
                                        <?php if ($show): ?><th class="sh-num"><?= lang('Quo.colUnitPrice') ?></th><?php endif; ?>
                                        <th class="sh-num"><?= lang('Quo.colQuantity') ?></th>
                                        <?php if ($show): ?><th class="sh-num"><?= lang('Quo.colTotal') ?></th><?php endif; ?>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cartBody"></tbody>
                            </table>
                        </div>

                        </div><!-- /paneItems -->

                        <!-- Los datos del cliente, en su pestaña: no hay que salir de la capa -->
                        <section class="sh-pane sh-order" id="paneData" hidden style="border:0;padding:0">
                            <p class="sh-hint"><?= lang('Quo.shopYourDataHint') ?></p>

                            <!-- Lo imprescindible para poder llamar: nombre y teléfono.
                                 Lo demás está a un clic, para quien quiera darlo. -->
                            <div class="sh-fields">
                                <div class="sh-field">
                                    <label for="name"><?= lang('Quo.shopName') ?> *</label>
                                    <input type="text" id="name" name="name" required maxlength="200" value="<?= esc(old('name'), 'attr') ?>">
                                </div>
                                <div class="sh-field">
                                    <label for="phone"><?= lang('Quo.phone') ?> *</label>
                                    <input type="tel" id="phone" name="phone" required maxlength="50" value="<?= esc(old('phone'), 'attr') ?>">
                                </div>
                            </div>

                            <button type="button" class="sh-more-btn" id="moreDataBtn"
                                aria-expanded="false" aria-controls="moreData"
                                data-more="<?= esc(lang('Quo.shopMoreData'), 'attr') ?>"
                                data-less="<?= esc(lang('Quo.shopLessData'), 'attr') ?>">
                                <span class="sh-i" id="moreDataIcon"><?= $ic['plus'] ?></span>
                                <span id="moreDataTxt"><?= lang('Quo.shopMoreData') ?></span>
                            </button>

                            <div class="sh-fields" id="moreData" hidden>
                                <div class="sh-field">
                                    <label for="nit"><?= lang('Quo.nit') ?></label>
                                    <input type="text" id="nit" name="nit" maxlength="50" value="<?= esc(old('nit'), 'attr') ?>">
                                </div>
                                <div class="sh-field">
                                    <label for="email"><?= lang('Quo.email') ?></label>
                                    <input type="email" id="email" name="email" maxlength="190" value="<?= esc(old('email'), 'attr') ?>">
                                </div>
                                <div class="sh-field">
                                    <label for="city"><?= lang('Quo.city') ?></label>
                                    <input type="text" id="city" name="city" maxlength="100" value="<?= esc(old('city'), 'attr') ?>">
                                </div>
                                <div class="sh-field">
                                    <label for="address"><?= lang('Quo.address') ?></label>
                                    <input type="text" id="address" name="address" maxlength="255" value="<?= esc(old('address'), 'attr') ?>">
                                </div>
                                <div class="sh-field sh-wide">
                                    <label for="notes"><?= lang('Quo.shopNotes') ?></label>
                                    <textarea id="notes" name="notes" rows="2" maxlength="500"><?= esc(old('notes')) ?></textarea>
                                </div>
                            </div>
                        </section>

                        <div class="sh-cart-foot">
                            <?php if ($show): ?>
                                <div class="sh-total"><?= lang('Quo.shopEstimated') ?><b id="cartTotal"><?= $money(0) ?></b></div>
                            <?php else: ?>
                                <div class="sh-total"><span id="cartUnits">0</span> <?= lang('Quo.shopItems') ?></div>
                            <?php endif; ?>
                            <button type="submit" class="sh-btn" id="sendBtn" disabled>
                                <span class="sh-i"><?= $ic['whatsapp'] ?></span> <?= lang('Quo.shopSend') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php // Sin pie: los datos de la empresa (razón social, NIT, ciudad) están en la
      // tarjeta que abre el logo. La vitrina termina en los productos. ?>

<!-- ===================== detalle del producto ===================== -->
<div class="sh-modal" id="detailModal">
    <div class="sh-modal-box">
        <button type="button" class="sh-modal-close sh-i" data-close
            aria-label="<?= esc(lang('Quo.shopClose'), 'attr') ?>"><?= $ic['close'] ?></button>
        <div class="sh-detail">
            <div class="sh-detail-photo" id="dPhoto"></div>
            <div class="sh-detail-body">
                <div class="sh-code" id="dCode" style="margin-top:0"></div>
                <h3 id="dName"></h3>
                <p class="sh-detail-desc" id="dDesc"></p>
                <?php if ($show): ?>
                    <div class="sh-detail-price"><span id="dPrice"></span><small id="dBefore"></small></div>
                <?php endif; ?>
                <div class="sh-qty" id="dQty">
                    <button type="button" data-dstep="-1" aria-label="−">−</button>
                    <input type="number" min="0" step="1" value="0" id="dInput">
                    <button type="button" data-dstep="1" aria-label="+">+</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Tarjeta de la empresa (se abre desde el logo) ============ -->
<div class="sh-modal" id="aboutModal">
    <div class="sh-modal-box sh-modal-sm">
        <button type="button" class="sh-modal-close sh-i" data-close
            aria-label="<?= esc(lang('Quo.shopClose'), 'attr') ?>"><?= $ic['close'] ?></button>
        <div class="sh-about">
            <?php if (!empty($company['logo'])): ?>
                <img class="sh-about-logo" src="<?= media_url($company['logo']) ?>" alt="">
            <?php endif; ?>
            <h3><?= esc($company['name']) ?></h3>
            <?php
            // Lo que se quitó del pie: sigue disponible, pero sin ensuciar la vitrina.
            $legal = array_filter([
                !empty($company['nit']) ? 'NIT ' . $company['nit'] : '',
                $company['city'] ?? '',
            ]);
            ?>
            <?php if ($legal): ?>
                <p class="sh-about-legal"><?= esc(implode(' · ', $legal)) ?></p>
            <?php endif; ?>

            <?php if (!empty($company['description'])): ?>
                <p class="sh-about-desc"><?= esc($company['description']) ?></p>
            <?php endif; ?>

            <?php
            // Lo que la empresa escribió para ESTE catálogo. Ya no está en la
            // portada, así que aquí es donde se puede leer.
            $notes = array_filter([$catalog['headline'] ?? '', $catalog['intro'] ?? '']);
            ?>
            <?php if ($notes): ?>
                <div class="sh-about-cat">
                    <span><?= esc($catalog['name']) ?></span>
                    <?php foreach ($notes as $n): ?>
                        <p><?= esc($n) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="sh-about-seller">
                <span><?= lang('Quo.shopAdvisor') ?></span>
                <b><?= esc($seller['name']) ?></b>
            </div>
        </div>
    </div>
</div>

<?php if ($qr): ?>
    <!-- ===================== QR para compartir ===================== -->
    <div class="sh-modal" id="qrModal">
        <div class="sh-modal-box sh-modal-sm">
            <button type="button" class="sh-modal-close sh-i" data-close
                aria-label="<?= esc(lang('Quo.shopClose'), 'attr') ?>"><?= $ic['close'] ?></button>
            <div style="padding:28px;text-align:center">
                <img class="sh-qr" src="<?= $qr ?>" alt="">
                <p style="margin:14px 0 0;font-size:14px;font-weight:700"><?= esc($catalog['name']) ?></p>
                <p style="margin:4px 0 0;font-size:12px;color:var(--sh-muted)"><?= lang('Quo.shopQrHint') ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============ Navbar inferior, tipo app ============ -->
<nav class="sh-nav">
    <div class="sh-wrap sh-nav-in">
        <a href="<?= base_url('servicios/catalogo/' . $token . '/pdf') ?>" target="_blank" rel="noopener">
            <span class="sh-i"><?= $ic['download'] ?></span><b><?= lang('Quo.shopPdf') ?></b>
        </a>
        <button type="button" id="shareBtn" data-url="<?= esc($url, 'attr') ?>" data-title="<?= esc($catalog['name'], 'attr') ?>">
            <span class="sh-i"><?= $ic['share'] ?></span><b><?= lang('Quo.cardShareShort') ?></b>
        </button>
        <?php if ($qr): ?>
            <button type="button" data-open="qrModal">
                <span class="sh-i"><?= $ic['qr'] ?></span><b>QR</b>
            </button>
        <?php endif; ?>
        <button type="button" data-open="cartModal">
            <span class="sh-i"><?= $ic['bag'] ?></span><span class="sh-badge" id="navCount" hidden>0</span>
            <b><?= lang('Quo.shopViewOrder') ?></b>
        </button>
    </div>
</nav>

<script>
(function () {
    'use strict';

    var form   = document.getElementById('shopForm');
    var grid   = document.getElementById('shopGrid');
    var more   = document.getElementById('shopMore');
    var countP = document.getElementById('shopCount');
    var send   = document.getElementById('sendBtn');
    var navN   = document.getElementById('navCount');
    var noHits = document.getElementById('noHits');
    var SHOW   = <?= $show ? 'true' : 'false' ?>;
    var URL_   = '<?= base_url('servicios/catalogo/' . $token . '/items') ?>';
    var TXT    = {
        before:   <?= json_encode(lang('Quo.shopBefore')) ?>,
        showing:  <?= json_encode(lang('Quo.shopShowing', ['{0}', '{1}'])) ?>,
        remove:   <?= json_encode(lang('Quo.shopRemove')) ?>,
        copied:   <?= json_encode(lang('Quo.cardCopied')) ?>
    };

    /**
     * El carrito vive EN MEMORIA, no en los inputs: con el catálogo paginado y
     * filtrado, las tarjetas entran y salen del DOM y el pedido no puede
     * perderse por eso. Al enviar se escriben los `qty[id]` ocultos.
     */
    var cart = {};   // id → {qty, name, code, price, photo}

    function money(v) {
        return '$ ' + Number(v || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function units() {
        var n = 0;
        Object.keys(cart).forEach(function (k) { n += cart[k].qty; });

        return n;
    }

    function setQty(card, qty) {
        var d = card.dataset;
        qty = Math.max(0, qty || 0);

        if (qty > 0) {
            cart[d.id] = { qty: qty, name: d.name, code: d.code, price: parseFloat(d.price || 0), photo: d.photo };
        } else {
            delete cart[d.id];
        }

        card.querySelector('[data-qty]').value = qty;
        card.classList.toggle('is-picked', qty > 0);
        refresh();
    }

    function refresh() {
        var n = units();
        navN.textContent = n;
        navN.hidden      = n <= 0;
        send.disabled    = n <= 0;
    }

    /** Deja la tarjeta con la cantidad que ya tenga el carrito. */
    function syncCard(card) {
        var entry = cart[card.dataset.id];
        card.querySelector('[data-qty]').value = entry ? entry.qty : 0;
        card.classList.toggle('is-picked', !!entry);
    }

    // ------------------------------------------------ carga progresiva
    // Misma estructura que `_card.php`: si cambia una, cambia la otra.
    function cardHtml(p) {
        return '<article class="sh-card" data-card data-id="' + p.id + '" data-price="' + p.price + '"'
            + ' data-name="' + esc(p.name) + '" data-code="' + esc(p.code) + '"'
            + ' data-desc="' + esc(p.description) + '" data-photo="' + esc(p.photo) + '"'
            + ' data-before="' + (p.before ? esc(money(p.before)) : '') + '">'
            + '<button type="button" class="sh-photo" data-detail aria-label="' + esc(p.name) + '">'
            + (p.photo
                ? '<img src="' + esc(p.photo) + '" alt="' + esc(p.name) + '" loading="lazy" decoding="async">'
                : '<span class="sh-i"><?= addslashes($ic['image']) ?></span>')
            + '</button>'
            + (p.code ? '<div class="sh-code">' + esc(p.code) + '</div>' : '')
            + '<h2 class="sh-name">' + esc(p.name) + '</h2>'
            + (SHOW
                ? '<div class="sh-price">' + money(p.price)
                    + (p.before ? '<small>' + TXT.before + ' ' + money(p.before) + '</small>' : '') + '</div>'
                : '')
            + '<div class="sh-qty">'
            + '<button type="button" data-step="-1" aria-label="−">−</button>'
            + '<input type="number" min="0" step="1" value="0" data-qty aria-label="' + esc(p.name) + '">'
            + '<button type="button" data-step="1" aria-label="+">+</button>'
            + '</div></article>';
    }

    var query = '', cat = '', loading = false, shown = 0;

    function load(reset) {
        if (loading || !more) { return; }
        var page = reset ? 1 : parseInt(more.dataset.page, 10) + 1;
        if (!reset && page > parseInt(more.dataset.pages, 10)) { return; }

        loading = true;
        more.hidden = false;

        fetch(URL_ + '?page=' + page + '&q=' + encodeURIComponent(query) + '&cat=' + encodeURIComponent(cat),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (reset) { grid.innerHTML = ''; shown = 0; }

                grid.insertAdjacentHTML('beforeend', res.rows.map(cardHtml).join(''));
                shown += res.rows.length;

                more.dataset.page  = res.page;
                more.dataset.pages = res.pages;
                more.hidden        = res.page >= res.pages;

                if (noHits) { noHits.hidden = res.total > 0; }
                if (countP) {
                    countP.textContent = TXT.showing.replace('{0}', shown).replace('{1}', res.total);
                    countP.hidden = res.total === 0;
                }

                // Las tarjetas nuevas recuperan lo que ya estaba en el carrito.
                grid.querySelectorAll('[data-card]').forEach(syncCard);
                loading = false;
            })
            .catch(function () { loading = false; more.hidden = true; });
    }

    // Se dispara cuando el centinela entra en pantalla (o al hacer clic).
    if (more) {
        shown = grid.querySelectorAll('[data-card]').length;
        more.hidden = parseInt(more.dataset.pages, 10) <= 1;

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) { load(false); }
            }, { rootMargin: '600px' }).observe(more);
        }
        more.addEventListener('click', function () { load(false); });
    }

    // ------------------------------------------- buscador y categorías
    var search  = document.getElementById('shopSearch');
    var clearB  = document.getElementById('shopSearchClear');
    var catsBox = document.getElementById('shopCats');
    var timer;

    if (search) {
        search.addEventListener('input', function () {
            clearTimeout(timer);
            if (clearB) { clearB.hidden = search.value.trim() === ''; }
            // El filtro va al servidor: con miles de referencias no se puede
            // filtrar lo que no está en la página.
            timer = setTimeout(function () { query = search.value.trim(); load(true); }, 350);
        });
    }
    if (clearB) {
        clearB.addEventListener('click', function () {
            search.value = ''; clearB.hidden = true; query = ''; load(true); search.focus();
        });
    }
    if (catsBox) {
        catsBox.addEventListener('click', function (e) {
            var b = e.target.closest('[data-cat]');
            if (!b) { return; }
            cat = b.dataset.cat;
            catsBox.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            load(true);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ------------------------------------------------------------ capas
    function open(id)  { document.getElementById(id).classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    function close(el) { el.classList.remove('is-open'); document.body.style.overflow = ''; }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-open]');
        if (opener) { if (opener.dataset.open === 'cartModal') { renderCart(); } open(opener.dataset.open); return; }

        var closer = e.target.closest('[data-close]');
        if (closer) { close(closer.closest('.sh-modal')); return; }

        if (e.target.classList && e.target.classList.contains('sh-modal')) { close(e.target); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { document.querySelectorAll('.sh-modal.is-open').forEach(close); }
    });

    // ------------------------------------------- detalle del producto
    var current = null;
    var dPhoto  = document.getElementById('dPhoto');
    var dInput  = document.getElementById('dInput');

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-detail]');
        if (!btn) { return; }

        current = btn.closest('[data-card]');
        var d   = current.dataset;

        dPhoto.innerHTML = d.photo ? '<img src="' + esc(d.photo) + '" alt="">' : '';
        document.getElementById('dCode').textContent = d.code || '';
        document.getElementById('dName').textContent = d.name;
        document.getElementById('dDesc').textContent = d.desc || '';
        if (SHOW) {
            document.getElementById('dPrice').textContent  = money(d.price);
            document.getElementById('dBefore').textContent = d.before ? (TXT.before + ' ' + d.before) : '';
        }
        dInput.value = cart[d.id] ? cart[d.id].qty : 0;
        open('detailModal');
    });

    document.getElementById('dQty').addEventListener('click', function (e) {
        var b = e.target.closest('[data-dstep]');
        if (!b) { return; }
        dInput.value = Math.max(0, (parseFloat(dInput.value) || 0) + parseFloat(b.dataset.dstep));
        if (current) { setQty(current, parseFloat(dInput.value)); }
    });
    dInput.addEventListener('input', function () {
        if (current) { setQty(current, parseFloat(dInput.value) || 0); }
    });

    // ------------------------------------------------------------ pestañas
    function showTab(id) {
        document.querySelectorAll('.sh-pane').forEach(function (p) { p.hidden = p.id !== id; });
        document.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.toggle('is-on', b.dataset.tab === id); });
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-tab]');
        if (t) { showTab(t.dataset.tab); }
    });

    // Si falta un dato del cliente, el navegador no puede enfocar un campo
    // escondido: se salta a su pestaña ANTES de que valide.
    form.addEventListener('invalid', function (e) {
        if (e.target.closest('#paneData')) { showTab('paneData'); }
        // Y si el campo está en el bloque plegado, se despliega.
        if (e.target.closest('#moreData')) { toggleMore(true); }
    }, true);

    // --------------------------------------- datos opcionales del cliente
    // Solo nombre y teléfono a la vista; el resto, para quien quiera darlo.
    var moreBox  = document.getElementById('moreData');
    var moreBtn  = document.getElementById('moreDataBtn');
    var moreTxt  = document.getElementById('moreDataTxt');
    var moreIcon = document.getElementById('moreDataIcon');
    var iconPlus  = <?= json_encode($ic['plus'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var iconMinus = <?= json_encode($ic['minus'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function toggleMore(open) {
        moreBox.hidden = !open;
        moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        moreTxt.textContent = open ? moreBtn.dataset.less : moreBtn.dataset.more;
        moreIcon.innerHTML  = open ? iconMinus : iconPlus;
    }
    moreBtn.addEventListener('click', function () { toggleMore(moreBox.hidden); });

    // Si el navegador recuerda lo escrito, se abre para que se vea.
    if (Array.prototype.some.call(moreBox.querySelectorAll('input, textarea'), function (f) { return f.value.trim() !== ''; })) {
        toggleMore(true);
    }

    // ------------------------------------------------------- orden de pedido
    var cartBody  = document.getElementById('cartBody');
    var cartWrap  = document.getElementById('cartWrap');
    var cartEmpty = document.getElementById('cartEmpty');

    function renderCart() {
        var rows = '', sum = 0, n = 0;

        Object.keys(cart).forEach(function (id) {
            var it   = cart[id];
            var line = it.qty * it.price;
            sum += line;
            n   += it.qty;

            rows += '<tr>'
                + '<td style="width:56px">' + (it.photo ? '<img src="' + esc(it.photo) + '" alt="">' : '') + '</td>'
                + '<td><strong>' + esc(it.name) + '</strong>' + (it.code ? '<div style="font-size:11px;color:var(--sh-muted)">' + esc(it.code) + '</div>' : '') + '</td>'
                + (SHOW ? '<td class="sh-num">' + money(it.price) + '</td>' : '')
                + '<td class="sh-num">' + it.qty + '</td>'
                + (SHOW ? '<td class="sh-num"><strong>' + money(line) + '</strong></td>' : '')
                + '<td class="sh-num"><button type="button" class="sh-x" data-drop="' + id + '" title="' + TXT.remove + '">✕</button></td>'
                + '</tr>';
        });

        cartBody.innerHTML = rows;
        cartWrap.hidden    = rows === '';
        cartEmpty.hidden   = rows !== '';
        if (SHOW) { document.getElementById('cartTotal').textContent = money(sum); }
        else { document.getElementById('cartUnits').textContent = n; }
    }

    cartBody.addEventListener('click', function (e) {
        var x = e.target.closest('[data-drop]');
        if (!x) { return; }
        delete cart[x.dataset.drop];
        var card = grid.querySelector('[data-card][data-id="' + x.dataset.drop + '"]');
        if (card) { syncCard(card); }
        refresh();
        renderCart();
    });

    // El pedido se manda desde el carrito en memoria, no desde el DOM.
    form.addEventListener('submit', function () {
        form.querySelectorAll('[data-cartfield]').forEach(function (i) { i.remove(); });
        Object.keys(cart).forEach(function (id) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = 'qty[' + id + ']'; i.value = cart[id].qty;
            i.setAttribute('data-cartfield', '');
            form.appendChild(i);
        });
    });

    // ------------------------------------------------------------ compartir
    var share = document.getElementById('shareBtn');
    share.addEventListener('click', function () {
        var url = share.dataset.url;
        if (navigator.share) {
            navigator.share({ title: share.dataset.title, url: url }).catch(function () {});

            return;
        }
        var done = function () {
            var b = share.querySelector('b'), t = b.textContent;
            b.textContent = TXT.copied;
            setTimeout(function () { b.textContent = t; }, 2000);
        };
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

    // ------------------------------------------------------------ cantidades
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-step]');
        if (!btn) { return; }
        var card = btn.closest('[data-card]');
        if (!card) { return; }
        setQty(card, (parseFloat(card.querySelector('[data-qty]').value) || 0) + parseFloat(btn.dataset.step));
    });
    grid.addEventListener('input', function (e) {
        if (e.target.matches('[data-qty]')) { setQty(e.target.closest('[data-card]'), parseFloat(e.target.value) || 0); }
    });

    refresh();
}());
</script>

<?= $this->endSection() ?>
