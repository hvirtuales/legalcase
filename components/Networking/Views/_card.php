<?php
/**
 * Modelo de tarjeta de persona. Toda la información se deriva: identidad de
 * `users`, marca (banner 800×300 y logo) y nombre de `companies`, y de la red
 * solo cargo, sucursal e intereses.
 *
 * @var array       $card     salida de NetworkService::card()
 * @var string|null $state    pending|accepted|rejected|cancelled|null
 * @var array|null  $rel      solicitud relacionada (para el botón de mensaje)
 * @var bool        $showForm ¿pintar el formulario de solicitud?
 */
$uid       = (int) $card['user_id'];
$company   = $card['company'] ?? null;
$state     = $state ?? null;
$rel       = $rel ?? null;
$showForm  = $showForm ?? true;
$subtitle  = trim(implode(' · ', array_filter([$card['position'], $card['branch']])));
?>
<article class="overflow-hidden rounded-lg border border-line bg-surface">
    <!-- Portada: banner de la empresa (800×300) o degradado de marca -->
    <div class="relative aspect-[8/3] w-full bg-surface-muted">
        <?php if (!empty($card['banner'])): ?>
            <img src="<?= media_url($card['banner']) ?>" alt="" class="size-full object-cover">
        <?php else: ?>
            <div class="size-full bg-gradient-to-br from-primary/25 to-primary/5"></div>
        <?php endif; ?>

        <?php if (!empty($card['logo'])): ?>
            <img src="<?= media_url($card['logo']) ?>" alt=""
                class="absolute -bottom-5 left-4 size-12 rounded-md border border-line bg-surface object-contain p-1 shadow-sm">
        <?php endif; ?>

        <?php if ($state === 'accepted'): ?>
            <span class="absolute right-2 top-2 badge badge-success"><?= lang('Net.connected') ?></span>
        <?php elseif ($state === 'pending'): ?>
            <span class="absolute right-2 top-2 badge badge-warning"><?= lang('Net.statusPending') ?></span>
        <?php endif; ?>
    </div>

    <div class="p-4 <?= !empty($card['logo']) ? 'pt-7' : '' ?>">
        <a href="<?= base_url('networking/perfil/' . $uid) ?>" class="font-semibold text-primary"><?= esc($card['name']) ?></a>

        <?php if ($subtitle !== ''): ?>
            <div class="text-sm text-ink"><?= esc($subtitle) ?></div>
        <?php endif; ?>

        <?php if ($company): ?>
            <div class="mt-0.5 text-xs text-muted">
                <i class="icon icon-buildings"></i> <?= esc($company['name']) ?>
            </div>
        <?php endif; ?>

        <?php if ($card['interests'] !== ''): ?>
            <p class="mt-2 line-clamp-2 text-xs text-muted"><?= esc($card['interests']) ?></p>
        <?php endif; ?>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <a href="<?= base_url('networking/perfil/' . $uid) ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-eye"></i> <?= lang('Net.viewProfile') ?>
            </a>
            <?php if ($state === 'accepted' && $rel): ?>
                <a href="<?= base_url('networking/mensajes/' . (int) $rel['id']) ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-chat"></i> <?= lang('Net.message') ?>
                </a>
            <?php elseif ($state !== 'pending' && $showForm): ?>
                <button type="button" class="btn btn-primary btn-sm"
                    onclick="document.getElementById('req-<?= $uid ?>').classList.toggle('hidden')">
                    <i class="icon icon-person-plus"></i> <?= lang('Net.connect') ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($state !== 'accepted' && $state !== 'pending' && $showForm): ?>
            <form id="req-<?= $uid ?>" action="<?= base_url('networking/solicitar/' . $uid) ?>" method="post" class="mt-3 hidden">
                <?= csrf_field() ?>
                <label class="form-label text-xs"><?= lang('Net.whyConnect') ?> <span class="text-danger">*</span></label>
                <textarea name="message" rows="3" required minlength="10" maxlength="1000"
                    class="form-textarea text-sm" placeholder="<?= esc(lang('Net.whyConnectPlaceholder'), 'attr') ?>"></textarea>
                <div class="mt-2 flex gap-2">
                    <button class="btn btn-primary btn-sm"><i class="icon icon-send"></i> <?= lang('Net.sendRequest') ?></button>
                    <button type="button" class="btn btn-ghost btn-sm"
                        onclick="document.getElementById('req-<?= $uid ?>').classList.add('hidden')"><?= lang('Net.cancel') ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</article>
