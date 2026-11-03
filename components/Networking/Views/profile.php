<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Ficha de una persona: identidad de `users`, marca y datos de `companies`, y de
 * la red solo cargo, sucursal e intereses.
 *
 * @var array                                           $card
 * @var \Components\Networking\Libraries\NetworkService $net
 */
$uid       = (int) $user['id'];
$isMe      = $uid === $me;
$status    = $relation['status'] ?? null;
$connected = $status === 'accepted';
$seeEmail  = $net->canSeeContact($me, $profile, 'email');
$seePhone  = $net->canSeeContact($me, $profile, 'phone');
$phone     = preg_replace('/\D+/', '', (string) ($user['phone'] ?? ''));
$company   = $card['company'] ?? null;
$subtitle  = trim(implode(' · ', array_filter([$card['position'], $card['branch']])));
?>
<div class="mb-4">
    <a href="<?= base_url('networking/descubrir') ?>" class="btn btn-secondary btn-sm">
        <i class="icon icon-chevron-left"></i> <?= lang('Net.back') ?>
    </a>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <!-- Portada de la empresa (banner 800×300) -->
            <div class="relative aspect-[8/3] w-full bg-surface-muted">
                <?php if (!empty($card['banner'])): ?>
                    <img src="<?= media_url($card['banner']) ?>" alt="" class="size-full object-cover">
                <?php else: ?>
                    <div class="size-full bg-gradient-to-br from-primary/25 to-primary/5"></div>
                <?php endif; ?>
                <?php if (!empty($card['logo'])): ?>
                    <img src="<?= media_url($card['logo']) ?>" alt=""
                        class="absolute -bottom-6 left-5 size-16 rounded-md border border-line bg-surface object-contain p-1 shadow-sm">
                <?php endif; ?>
                <?php if ($connected): ?>
                    <span class="absolute right-3 top-3 badge badge-success"><?= lang('Net.connected') ?></span>
                <?php elseif ($status === 'pending'): ?>
                    <span class="absolute right-3 top-3 badge badge-warning"><?= lang('Net.statusPending') ?></span>
                <?php endif; ?>
            </div>

            <div class="card-body <?= !empty($card['logo']) ? 'pt-9' : '' ?>">
                <h5 class="text-lg font-semibold text-ink"><?= esc($card['name']) ?></h5>
                <?php if ($subtitle !== ''): ?>
                    <p class="text-sm text-muted"><?= esc($subtitle) ?></p>
                <?php endif; ?>

                <table class="table mt-4">
                    <tbody class="text-sm">
                        <?php if (!empty($card['position'])): ?>
                            <tr><td class="text-muted"><?= lang('Net.position') ?></td><td><?= esc($card['position']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($card['branch'])): ?>
                            <tr><td class="text-muted"><?= lang('Net.branch') ?></td><td><?= esc($card['branch']) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($company): ?>
                            <tr>
                                <td class="text-muted"><?= lang('Net.company') ?></td>
                                <td>
                                    <?= esc($company['name']) ?>
                                    <?php if (!empty($company['website'])): ?>
                                        <a href="<?= esc($company['website'], 'attr') ?>" target="_blank" rel="noopener"
                                            class="ml-1 text-xs text-primary"><?= esc($company['website']) ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($company['address'])): ?>
                                        <div class="text-xs text-muted"><?= esc($company['address']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($card['interests'])): ?>
                            <tr><td class="text-muted"><?= lang('Net.interests') ?></td><td><?= esc($card['interests']) ?></td></tr>
                        <?php endif; ?>
                        <tr>
                            <td class="text-muted"><?= lang('Net.email') ?></td>
                            <td><?= $seeEmail ? esc($user['email']) : '<span class="text-muted">' . lang('Net.visibleOnConnect') . '</span>' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= lang('Net.phone') ?></td>
                            <td><?= $seePhone && $phone !== '' ? esc($user['phone']) : '<span class="text-muted">' . lang('Net.visibleOnConnect') . '</span>' ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php if (!empty($profile['bio'])): ?>
                    <h6 class="mb-1 mt-4 font-semibold text-muted"><?= lang('Net.about') ?></h6>
                    <p class="text-sm"><?= nl2br(esc($profile['bio'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><i class="icon icon-send"></i><h6 class="font-semibold"><?= lang('Net.contact') ?></h6></div>
            <div class="card-body space-y-2">
                <?php if ($isMe): ?>
                    <a href="<?= base_url('networking/mi-perfil') ?>" class="btn btn-primary btn-sm w-full">
                        <i class="icon icon-pencil"></i> <?= lang('Net.editMyProfile') ?>
                    </a>
                <?php else: ?>
                    <?php if ($connected): ?>
                        <a href="<?= base_url('networking/mensajes/' . (int) $relation['id']) ?>" class="btn btn-primary btn-sm w-full">
                            <i class="icon icon-chat"></i> <?= lang('Net.sendMessage') ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($seeEmail && !empty($user['email'])): ?>
                        <a href="mailto:<?= esc($user['email'], 'attr') ?>" class="btn btn-secondary btn-sm w-full">
                            <i class="icon icon-envelope"></i> <?= lang('Net.email') ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($seePhone && $phone !== ''): ?>
                        <a href="https://wa.me/<?= esc($phone, 'attr') ?>" target="_blank" rel="noopener"
                            class="btn btn-success btn-sm w-full">
                            <i class="icon icon-whatsapp"></i> <?= lang('Net.whatsapp') ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!$connected && $status !== 'pending'): ?>
                        <form action="<?= base_url('networking/solicitar/' . $uid) ?>" method="post" class="mt-3">
                            <?= csrf_field() ?>
                            <label class="form-label text-xs"><?= lang('Net.whyConnect') ?> <span class="text-danger">*</span></label>
                            <textarea name="message" rows="3" required minlength="10" maxlength="1000"
                                class="form-textarea text-sm" placeholder="<?= esc(lang('Net.whyConnectPlaceholder'), 'attr') ?>"></textarea>
                            <button class="btn btn-primary btn-sm mt-2 w-full">
                                <i class="icon icon-person-plus"></i> <?= lang('Net.sendRequest') ?>
                            </button>
                        </form>
                    <?php elseif ($status === 'pending'): ?>
                        <p class="text-center text-xs text-muted">
                            <?= (int) $relation['requester_id'] === $me
                                ? lang('Net.errAlreadySent')
                                : lang('Net.errIncoming') ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
