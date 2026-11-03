<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Constancia de firma electrónica (imprimible). Reúne lo que sostiene su valor probatorio y
 * recalcula el hash de evidencia para mostrar si el registro sigue íntegro.
 *
 * @var array $sig @var array $matter @var ?array $doc @var ?array $account @var bool $intact
 */
use Components\LegalCase\Libraries\Meta;
?>
<div class="mx-auto max-w-3xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <a href="<?= base_url('legalcase/asuntos/ficha/' . (int) $matter['id'] . '?tab=documentos') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i></a>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><i class="icon icon-printer"></i> <?= lang('Lgc.print') ?></button>
    </div>
    <div class="card">
        <div class="card-header flex-wrap justify-between gap-2">
            <div class="flex items-center gap-2"><i class="icon icon-shield-check"></i><h5 class="font-semibold"><?= lang('Lgc.signEvidence') ?></h5></div>
            <?= $intact ? '<span class="badge badge-success">' . lang('Lgc.evidenceIntact') . '</span>' : '<span class="badge badge-danger">' . lang('Lgc.evidenceBroken') . '</span>' ?>
        </div>
        <div class="card-body">
            <dl class="grid gap-3 text-sm md:grid-cols-2">
                <div><dt class="text-xs text-muted"><?= lang('Lgc.matter') ?></dt><dd class="font-mono"><?= esc($matter['number']) ?></dd></div>
                <div><dt class="text-xs text-muted"><?= lang('Lgc.fSignPurpose') ?></dt><dd><?= esc($sig['purpose']) ?></dd></div>
                <div><dt class="text-xs text-muted"><?= lang('Lgc.fName') ?></dt><dd><?= esc($doc['name'] ?? '—') ?> (v<?= (int) ($doc['version'] ?? 1) ?>)</dd></div>
                <div class="md:col-span-2"><dt class="text-xs text-muted"><?= lang('Lgc.docHash') ?></dt><dd class="break-all font-mono text-xs"><?= esc($sig['doc_sha256']) ?></dd></div>
                <div><dt class="text-xs text-muted"><?= lang('Lgc.signerName') ?></dt><dd><?= esc($sig['signer_name']) ?></dd></div>
                <div><dt class="text-xs text-muted"><?= lang('Lgc.fDocNumber') ?></dt><dd><?= esc($sig['signer_doc'] ?: '—') ?></dd></div>
                <div><dt class="text-xs text-muted"><?= lang('Lgc.signerAccount') ?></dt><dd><?= esc($account['email'] ?? '—') ?></dd></div>
                <div><dt class="text-xs text-muted"><?= lang('Lgc.signedAt') ?></dt><dd><?= esc(Meta::date($sig['signed_at'])) ?> (<?= esc(date_default_timezone_get()) ?>)</dd></div>
                <div><dt class="text-xs text-muted">IP</dt><dd><?= esc($sig['ip']) ?></dd></div>
                <div class="md:col-span-2"><dt class="text-xs text-muted"><?= lang('Lgc.userAgent') ?></dt><dd class="break-all text-xs"><?= esc($sig['user_agent']) ?></dd></div>
                <div class="md:col-span-2"><dt class="text-xs text-muted"><?= lang('Lgc.evidenceHash') ?></dt><dd class="break-all font-mono text-xs"><?= esc($sig['evidence_hash']) ?></dd></div>
            </dl>
            <p class="mt-4 text-xs text-muted"><?= lang('Lgc.evidenceLegalNote') ?></p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
