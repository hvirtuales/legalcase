<?php
/**
 * Equipo interno y partes del asunto, con el acceso al portal POR ASUNTO. Solo cliente y
 * representante pueden recibir acceso; la contraparte nunca (§6.9).
 *
 * @var array $matter @var array $team @var array $participants @var array $contacts @var array $accounts
 * @var array $users @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\ParticipantModel;
use Components\LegalCase\Models\TeamModel;

$id      = (int) $matter['id'];
$canEdit  = ($canWork && $acc->can('lgc.edit'));
$canShare = ($canWork && $acc->can('lgc.share'));
?>
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card">
        <div class="card-header"><i class="icon icon-person-badge"></i><h5 class="font-semibold"><?= lang('Lgc.team') ?></h5></div>
        <div class="card-body">
            <p class="mb-3 text-xs text-muted"><?= lang($matter['visibility'] === 'team' ? 'Lgc.teamHintRestricted' : 'Lgc.teamHint') ?></p>
            <ul class="divide-y divide-line">
                <?php foreach ($team as $t): ?>
                    <li class="flex items-center justify-between gap-2 py-2 text-sm">
                        <span><?= esc($users[(int) $t['user_id']] ?? '#' . $t['user_id']) ?> <span class="badge badge-light"><?= lang('Lgc.trole_' . $t['role']) ?></span></span>
                        <?php if ($canEdit && (int) $t['user_id'] !== (int) $matter['lead_lawyer_id']): ?>
                            <?= view('partials/delete_button', ['action' => base_url('legalcase/asuntos/equipo/quitar/' . $id . '/' . (int) $t['id']), 'confirm' => lang('Lgc.confirmRemove'), 'label' => lang('Lgc.remove'), 'icon' => 'x', 'class' => 'btn btn-ghost btn-sm', 'text' => '']) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canEdit): ?>
                <form method="post" action="<?= base_url('legalcase/asuntos/equipo/' . $id) ?>" class="mt-3 flex flex-wrap items-end gap-2">
                    <?= csrf_field() ?>
                    <select name="user_id" class="form-select" aria-label="<?= esc(lang('Lgc.fUser'), 'attr') ?>">
                        <?php foreach ($users as $uid => $name): ?><option value="<?= (int) $uid ?>"><?= esc($name) ?></option><?php endforeach; ?>
                    </select>
                    <select name="role" class="form-select" aria-label="<?= esc(lang('Lgc.fRole'), 'attr') ?>">
                        <?php foreach (TeamModel::ROLES as $r): ?><option value="<?= $r ?>" <?= $r === 'collaborator' ? 'selected' : '' ?>><?= lang('Lgc.trole_' . $r) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-person-plus"></i> <?= lang('Lgc.add') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-people"></i><h5 class="font-semibold"><?= lang('Lgc.participants') ?></h5></div>
        <div class="card-body">
            <?php if (!$participants): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noParticipants') ?></p>
            <?php endif; ?>
            <ul class="divide-y divide-line">
                <?php foreach ($participants as $p):
                    $account   = $accounts[(int) $p['contact_id']] ?? null;
                    $canPortal = in_array($p['role'], ParticipantModel::PORTAL_ROLES, true); ?>
                    <li class="py-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold"><?= esc($p['contact_name']) ?></span>
                                <span class="badge badge-light"><?= lang('Lgc.prole_' . $p['role']) ?></span>
                                <div class="text-xs text-muted"><?= esc($p['contact_email'] ?: lang('Lgc.noEmail')) ?></div>
                            </div>
                            <?php if ($canEdit): ?>
                                <?= view('partials/delete_button', ['action' => base_url('legalcase/asuntos/parte/quitar/' . $id . '/' . (int) $p['id']), 'confirm' => lang('Lgc.confirmRemoveParticipant'), 'label' => lang('Lgc.remove'), 'icon' => 'x', 'class' => 'btn btn-ghost btn-sm', 'text' => '']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($canPortal): ?>
                            <div class="mt-2 flex flex-wrap items-center gap-2 rounded-md border border-line p-2">
                                <i class="icon icon-shield-lock"></i>
                                <?php if ((int) $p['portal_access'] === 1): ?>
                                    <span class="badge badge-success"><?= lang('Lgc.portalOn') ?></span>
                                    <?php if ($account): ?>
                                        <span class="text-xs text-muted"><?= lang('Lgc.lastLogin') ?>: <?= esc(Meta::date($account['last_login_at'])) ?><?= $account['status'] !== 'active' ? ' · ' . lang('Lgc.accountBlocked') : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($canShare): ?>
                                        <form method="post" action="<?= base_url('legalcase/asuntos/portal/' . $id . '/' . (int) $p['id']) ?>" class="ml-auto flex gap-1"><?= csrf_field() ?>
                                            <button name="grant" value="1" class="btn btn-ghost btn-sm" title="<?= esc(lang('Lgc.resendInvite'), 'attr') ?>"><i class="icon icon-envelope"></i></button>
                                            <button name="grant" value="0" class="btn btn-ghost btn-sm" onclick="return confirm('<?= esc(lang('Lgc.confirmRevokePortal'), 'js') ?>')"><i class="icon icon-lock"></i> <?= lang('Lgc.revokeAccess') ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-muted"><?= lang('Lgc.portalOff') ?></span>
                                    <?php if ($p['role'] === 'representative' && !\Components\LegalCase\Libraries\Portal::authorityValid($p)): ?>
                                        <span class="badge badge-warning"><?= lang('Lgc.authorityMissing') ?></span>
                                    <?php elseif ($canShare): ?>
                                        <form method="post" action="<?= base_url('legalcase/asuntos/portal/' . $id . '/' . (int) $p['id']) ?>" class="ml-auto"
                                            onsubmit="return confirm('<?= esc(lang('Lgc.confirmGrantPortal', [$p['contact_email'] ?: '—']), 'js') ?>')"><?= csrf_field() ?>
                                            <button name="grant" value="1" class="btn btn-primary btn-sm" <?= $p['contact_email'] ? '' : 'disabled' ?>><i class="icon icon-key"></i> <?= lang('Lgc.grantAccess') ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($p['role'] === 'representative'): ?>
                            <details class="mt-2 rounded-md border border-line p-2" <?= \Components\LegalCase\Libraries\Portal::authorityValid($p) ? '' : 'open' ?>>
                                <summary class="cursor-pointer text-xs font-semibold"><i class="icon icon-file-earmark"></i> <?= lang('Lgc.authorityTitle') ?>
                                    <?= $p['authority_until'] ? '· ' . lang('Lgc.authorityUntil', [esc($p['authority_until'])]) : '' ?></summary>
                                <?php if ($canEdit): ?>
                                    <form method="post" action="<?= base_url('legalcase/asuntos/parte/poder/' . $id . '/' . (int) $p['id']) ?>" class="mt-2 grid gap-2 md:grid-cols-3">
                                        <?= csrf_field() ?>
                                        <select name="authority_document_id" class="form-select md:col-span-3" aria-label="<?= esc(lang('Lgc.fAuthorityDoc'), 'attr') ?>">
                                            <option value=""><?= lang('Lgc.chooseAuthorityDoc') ?></option>
                                            <?php foreach ($matterDocs as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $p['authority_document_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?></option><?php endforeach; ?>
                                        </select>
                                        <input type="text" name="authority_scope" class="form-input md:col-span-2" maxlength="255" value="<?= esc($p['authority_scope'] ?? '', 'attr') ?>" placeholder="<?= esc(lang('Lgc.phAuthorityScope'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.fAuthorityScope'), 'attr') ?>">
                                        <input type="date" name="authority_until" class="form-input" value="<?= esc($p['authority_until'] ?? '', 'attr') ?>" aria-label="<?= esc(lang('Lgc.fAuthorityUntil'), 'attr') ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm md:col-span-3"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button>
                                    </form>
                                    <p class="mt-1 text-xs text-muted"><?= lang('Lgc.authorityHint') ?></p>
                                <?php endif; ?>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canEdit): ?>
                <form method="post" action="<?= base_url('legalcase/asuntos/parte/' . $id) ?>" class="mt-3 grid gap-2 md:grid-cols-3">
                    <?= csrf_field() ?>
                    <div class="md:col-span-3"><?= view('Components\LegalCase\Views\_conflicts') ?></div>
                    <select name="contact_id" class="form-select md:col-span-2" required aria-label="<?= esc(lang('Lgc.fContact'), 'attr') ?>">
                        <option value=""><?= lang('Lgc.chooseContact') ?></option>
                        <?php foreach ($contacts as $cid => $name): ?><option value="<?= (int) $cid ?>" <?= (string) old('contact_id') === (string) $cid ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach; ?>
                    </select>
                    <select name="role" class="form-select" aria-label="<?= esc(lang('Lgc.fRole'), 'attr') ?>">
                        <?php foreach (ParticipantModel::ROLES as $r): ?><option value="<?= $r ?>" <?= old('role') === $r ? 'selected' : '' ?>><?= lang('Lgc.prole_' . $r) ?></option><?php endforeach; ?>
                    </select>
                    <input type="text" name="notes" class="form-input md:col-span-2" maxlength="255" placeholder="<?= esc(lang('Lgc.phParticipantNote'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.fNotes'), 'attr') ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-person-plus"></i> <?= lang('Lgc.add') ?></button>
                </form>
                <p class="mt-2 text-xs text-muted"><?= lang('Lgc.participantsHint') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card lg:col-span-2">
        <div class="card-header"><i class="icon icon-shield-check"></i><h5 class="font-semibold"><?= lang('Lgc.conflictHistory') ?></h5></div>
        <div class="card-body">
            <p class="mb-2 text-xs text-muted"><?= lang('Lgc.conflictHistoryHint') ?></p>
            <?php if (!$conflicts): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noConflictChecks') ?></p>
            <?php else: ?>
                <div class="table-card overflow-x-auto">
                    <table class="table">
                        <thead><tr><th><?= lang('Lgc.fDate') ?></th><th><?= lang('Lgc.fRole') ?></th><th><?= lang('Lgc.conflictResult') ?></th><th><?= lang('Lgc.conflictNote') ?></th><th><?= lang('Lgc.fActor') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($conflicts as $c): ?>
                            <tr>
                                <td class="whitespace-nowrap text-xs"><?= esc(Meta::date($c['created_at'])) ?></td>
                                <td class="text-xs"><?= lang('Lgc.prole_' . ($c['role'] ?: 'other')) ?></td>
                                <td><?= $c['result'] === 'clear' ? '<span class="badge badge-success">' . lang('Lgc.conflictClear') . '</span>' : '<span class="badge badge-danger">' . lang('Lgc.conflictPotential', [count((array) json_decode((string) $c['hits'], true))]) . '</span>' ?>
                                    <?= $c['decision'] === 'proceed' ? '<span class="badge badge-warning">' . lang('Lgc.conflictProceeded') . '</span>' : '' ?></td>
                                <td class="max-w-xs text-xs"><?= esc((string) $c['justification']) ?></td>
                                <td class="text-xs"><?= esc($users[(int) $c['decided_by']] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
