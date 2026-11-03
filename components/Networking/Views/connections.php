<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Lista de conexiones + hilo de mensajes de la conexión activa.
 *
 * @var array                                              $rows
 * @var array|null                                         $active   solicitud aceptada abierta
 * @var \Components\Networking\Libraries\NetworkService    $net
 */
$active   = $active ?? null;
$messages = $messages ?? [];
$other    = $other ?? null;
$profile  = $profile ?? [];
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-chat"></i>
        <h5 class="font-semibold"><?= lang('Net.connections') ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Networking\Views\_tabs', ['counts' => $counts, 'current' => 'conexiones']) ?>

        <?php if (empty($rows)): ?>
            <p class="py-8 text-center text-muted"><?= lang('Net.noConnections') ?>
                <a href="<?= base_url('networking/descubrir') ?>" class="text-primary"><?= lang('Net.discoverPeopleLink') ?></a> <?= lang('Net.andSendRequest') ?></p>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Lista de conexiones -->
                <div class="lg:col-span-1">
                    <div class="space-y-2">
                        <?php foreach ($rows as $c): ?>
                            <?php
                            $otherId  = $net->otherUserId($c, $me);
                            $isActive = $active && (int) $active['id'] === (int) $c['id'];
                            ?>
                            <?php
                            $cCard = $c['card'] ?? [];
                            $cSub  = trim(implode(' · ', array_filter([$cCard['position'] ?? '', $cCard['branch'] ?? ''])));
                            ?>
                            <a href="<?= base_url('networking/mensajes/' . (int) $c['id']) ?>"
                                class="flex items-start justify-between gap-2 rounded-lg border p-3 <?= $isActive ? 'border-primary bg-body' : 'border-line hover:bg-body' ?>">
                                <div class="flex min-w-0 items-start gap-2">
                                    <?php if (!empty($cCard['logo'])): ?>
                                        <img src="<?= media_url($cCard['logo']) ?>" alt=""
                                            class="size-8 shrink-0 rounded border border-line bg-surface object-contain p-0.5">
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold <?= $isActive ? 'text-primary' : 'text-ink' ?>">
                                            <?= esc($net->displayName($c['other'])) ?>
                                        </div>
                                        <?php if ($cSub !== ''): ?>
                                            <div class="truncate text-xs text-muted"><?= esc($cSub) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($cCard['company']['name'])): ?>
                                            <div class="truncate text-xs text-faint"><?= esc($cCard['company']['name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ((int) $c['unread'] > 0): ?>
                                    <span class="badge badge-danger shrink-0"><?= (int) $c['unread'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Hilo -->
                <div class="lg:col-span-2">
                    <?php if (!$active): ?>
                        <div class="flex h-full items-center justify-center rounded-lg border border-dashed border-line p-8 text-center text-muted">
                            <?= lang('Net.pickConversation') ?>
                        </div>
                    <?php else: ?>
                        <?php
                        $otherId  = $net->otherUserId($active, $me);
                        $phone    = preg_replace('/\D+/', '', (string) ($other['phone'] ?? ''));
                        $card     = $card ?? $net->card($otherId);
                        $subtitle = trim(implode(' · ', array_filter([$card['position'] ?? '', $card['branch'] ?? ''])));
                        ?>
                        <div class="rounded-lg border border-line">
                            <!-- Cabecera con acciones de contacto -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line p-3">
                                <div class="flex items-center gap-2">
                                    <?php if (!empty($card['logo'])): ?>
                                        <img src="<?= media_url($card['logo']) ?>" alt=""
                                            class="size-9 rounded border border-line bg-surface object-contain p-0.5">
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-semibold text-ink"><?= esc($net->displayName($other)) ?></div>
                                        <?php if ($subtitle !== ''): ?>
                                            <div class="text-xs text-muted"><?= esc($subtitle) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($card['company']['name'])): ?>
                                            <div class="text-xs text-faint"><?= esc($card['company']['name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-1">
                                    <a href="<?= base_url('networking/perfil/' . $otherId) ?>" class="btn btn-secondary btn-sm">
                                        <i class="icon icon-person"></i> <?= lang('Net.viewProfile') ?>
                                    </a>
                                    <?php if (!empty($other['email'])): ?>
                                        <a href="mailto:<?= esc($other['email'], 'attr') ?>" class="btn btn-secondary btn-sm">
                                            <i class="icon icon-envelope"></i> <?= lang('Net.email') ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($phone !== ''): ?>
                                        <a href="https://wa.me/<?= esc($phone, 'attr') ?>" target="_blank" rel="noopener"
                                            class="btn btn-success btn-sm">
                                            <i class="icon icon-whatsapp"></i> <?= lang('Net.whatsapp') ?>
                                        </a>
                                    <?php endif; ?>
                                    <form action="<?= base_url('networking/conexiones/eliminar/' . (int) $active['id']) ?>" method="post" class="inline"
                                        onsubmit="return confirm('<?= esc(lang('Net.confirmRemove'), 'js') ?>')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm text-danger" title="<?= esc(lang('Net.removeConnection'), 'attr') ?>">
                                            <i class="icon icon-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Mensajes -->
                            <div class="max-h-96 space-y-2 overflow-y-auto p-3">
                                <?php if (empty($messages)): ?>
                                    <p class="py-6 text-center text-sm text-muted"><?= lang('Net.noMessages') ?></p>
                                <?php else: ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <?php $mine = (int) $msg['sender_id'] === $me; ?>
                                        <div class="flex <?= $mine ? 'justify-end' : 'justify-start' ?>">
                                            <div class="max-w-[75%] rounded-lg px-3 py-2 text-sm <?= $mine ? 'bg-primary text-white' : 'bg-body text-ink' ?>">
                                                <?= nl2br(esc($msg['body'])) ?>
                                                <div class="mt-1 text-[10px] <?= $mine ? 'text-white/70' : 'text-muted' ?>">
                                                    <?= esc($msg['created_at'] ?? '') ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Enviar -->
                            <form action="<?= base_url('networking/mensajes/' . (int) $active['id']) ?>" method="post"
                                class="flex items-end gap-2 border-t border-line p-3">
                                <?= csrf_field() ?>
                                <textarea name="body" rows="2" required maxlength="2000" class="form-textarea"
                                    placeholder="<?= esc(lang('Net.messagePlaceholder'), 'attr') ?>"></textarea>
                                <button class="btn btn-primary btn-sm"><i class="icon icon-send"></i> <?= lang('Net.send') ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
