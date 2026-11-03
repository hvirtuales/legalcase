<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\DocumentTypeModel;
use Components\LeadFlow\Models\MatterStageModel;
use Components\LeadFlow\Models\ScoringRuleModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SettingsModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\StageModel;

/**
 * Los datos con los que arranca una firma: las 15 etapas del §9, las fuentes, los
 * servicios de ejemplo del §14, los tipos de documento, las reglas del §15 y las
 * etapas procesales del expediente.
 *
 * **Siembra perezosa**: se llama al entrar a las pantallas y al formulario
 * público, y cada bloque solo se siembra si está vacío. Los eventos de ciclo de
 * vida del contrato no se disparan en el núcleo, así que no se puede esperar a
 * «al instalar»; y sembrar por firma al entrar es lo que funciona igual para una
 * firma creada antes o después de instalar el componente.
 */
class Catalog
{
    private static array $done = [];

    public static function ensure(int $companyId): void
    {
        if ($companyId <= 0 || isset(self::$done[$companyId])) {
            return;
        }
        self::$done[$companyId] = true;

        (new SettingsModel())->forCompany($companyId);

        $stages = new StageModel();
        if ($stages->withDeleted()->where('company_id', $companyId)->countAllResults() === 0) {
            $sort = 10;
            foreach (StageModel::SYSTEM as $code => [$kind, $prob, $color]) {
                $stages->insert([
                    'company_id'  => $companyId,
                    'name'        => lang('Lfl.' . Meta::STAGE_CODES[$code]),
                    'code'        => $code,
                    'kind'        => $kind,
                    'sort'        => $sort,
                    'probability' => $prob,
                    'color'       => $color,
                    'is_active'   => 1,
                ]);
                $sort += 10;
            }
        }

        $sources = new SourceModel();
        if ($sources->withDeleted()->where('company_id', $companyId)->countAllResults() === 0) {
            foreach ([['web', 'web'], ['meta', 'facebook'], ['google', 'google'], ['whatsapp', 'whatsapp'],
                ['referral', 'referral'], ['other', null]] as [$kind, $utm]) {
                $sources->insert([
                    'company_id' => $companyId,
                    'name'       => lang('Lfl.' . Meta::SOURCE_KINDS[$kind]),
                    'kind'       => $kind,
                    'utm_source' => $utm,
                    'is_active'  => 1,
                ]);
            }
        }

        $services = new ServiceModel();
        if ($services->withDeleted()->where('company_id', $companyId)->countAllResults() === 0) {
            $sort = 10;
            // Asilo y defensa de deportación nacen como prioritarios: son los que no
            // pueden esperar. La firma lo cambia en Configuración.
            foreach (['svcFamilyVisa' => 0, 'svcWorkVisa' => 0, 'svcAsylum' => 1, 'svcCitizenship' => 0,
                'svcResidence' => 0, 'svcAdjustment' => 0, 'svcDeportation' => 1, 'svcConsultation' => 0] as $key => $prio) {
                $services->insert([
                    'company_id'  => $companyId,
                    'name'        => lang('Lfl.' . $key),
                    'is_priority' => $prio,
                    'sort'        => $sort,
                    'is_active'   => 1,
                ]);
                $sort += 10;
            }
        }

        $types = new DocumentTypeModel();
        if ($types->withDeleted()->where('company_id', $companyId)->countAllResults() === 0) {
            $sort = 10;
            foreach (['dtPassport', 'dtId', 'dtBirth', 'dtVisa', 'dtAddress', 'dtOther'] as $key) {
                $types->insert(['company_id' => $companyId, 'name' => lang('Lfl.' . $key), 'sort' => $sort, 'is_active' => 1]);
                $sort += 10;
            }
        }

        /*
         * Etapas procesales del expediente.
         *
         * Mismo criterio «solo si está vacío» que las comerciales, y aquí es seguro
         * para TODAS las firmas porque la tabla es nueva: nadie tiene etapas propias
         * todavía. Ojo para el futuro: añadir un código a `SYSTEM` más adelante no
         * llegaría a las firmas que ya pasaron por aquí, y recorrer código por código
         * resucitaría etapas que alguien borró a propósito. Si algún día hace falta,
         * va en una migración, no aquí.
         */
        $mstages = new MatterStageModel();
        if ($mstages->withDeleted()->where('company_id', $companyId)->countAllResults() === 0) {
            $sort = 10;
            foreach (MatterStageModel::SYSTEM as $code => [$kind, $color, $sla]) {
                $mstages->insert([
                    'company_id' => $companyId,
                    'name'       => lang('Lfl.' . Meta::MATTER_STAGE_CODES[$code]),
                    'code'       => $code,
                    'kind'       => $kind,
                    'sort'       => $sort,
                    'color'      => $color,
                    'sla_days'   => $sla,
                    'is_active'  => 1,
                ]);
                $sort += 10;
            }
        }

        $rules    = new ScoringRuleModel();
        $existing = $rules->mapFor($companyId);
        foreach (ScoringService::CRITERIA as $criterion => $points) {
            if (! isset($existing[$criterion])) {
                $rules->insert(['company_id' => $companyId, 'criterion' => $criterion, 'points' => $points, 'is_active' => 1]);
            }
        }
    }

    public static function flush(): void
    {
        self::$done = [];
    }
}
