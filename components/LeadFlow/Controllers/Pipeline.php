<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\LeadService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\StageModel;

/**
 * El Kanban (§10): columnas por etapa, arrastrar y soltar y los filtros del
 * documento (asesor, abogado, servicio, fuente, campaña, prioridad, score, fecha,
 * sin actividad y actividades vencidas).
 *
 * Los estados alternativos (perdido, no calificado…) van ocultos por defecto:
 * quince columnas no caben en una pantalla y la mayor parte del día se trabaja
 * con el flujo principal.
 */
class Pipeline extends BaseLfl
{
    private const PER_COLUMN = 60;

    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('pipeline/index', ['columns' => null]);
        }
        $a = $this->access();
        $g = fn (string $k) => (string) $this->request->getGet($k);

        $f = [
            'asesor'        => (int) $g('asesor') ?: '',
            'abogado'       => (int) $g('abogado') ?: '',
            'servicio'      => (int) $g('servicio') ?: '',
            'fuente'        => (int) $g('fuente') ?: '',
            'campana'       => (int) $g('campana') ?: '',
            'prioridad'     => in_array($g('prioridad'), LeadModel::PRIORITIES, true) ? $g('prioridad') : '',
            'score_min'     => $g('score_min') !== '' ? max(0, min(100, (int) $g('score_min'))) : '',
            'desde'         => preg_match('/^\d{4}-\d{2}-\d{2}$/', $g('desde')) ? $g('desde') : '',
            'hasta'         => preg_match('/^\d{4}-\d{2}-\d{2}$/', $g('hasta')) ? $g('hasta') : '',
            'sin_actividad' => $g('sin_actividad') === '1' ? '1' : '',
            'vencidas'      => $g('vencidas') === '1' ? '1' : '',
            'alt'           => $g('alt') === '1' ? '1' : '',
        ];

        $stages  = (new StageModel())->ordered($cid, true);
        $columns = [];
        $now     = date('Y-m-d H:i:s');

        foreach ($stages as $s) {
            if ($s['kind'] === 'lost' && ! $f['alt']) {
                continue;
            }
            $build = function () use ($a, $f, $s, $now) {
                $m = new LeadModel();
                $a->scope($m);
                $m->where('cmp_lfl_leads.stage_id', (int) $s['id']);
                foreach (['asesor' => 'assigned_user_id', 'abogado' => 'lawyer_user_id', 'servicio' => 'service_id',
                    'fuente' => 'source_id', 'campana' => 'campaign_id'] as $k => $col) {
                    if ($f[$k] !== '') {
                        $m->where('cmp_lfl_leads.' . $col, (int) $f[$k]);
                    }
                }
                if ($f['prioridad'] !== '') {
                    $m->where('cmp_lfl_leads.priority', $f['prioridad']);
                }
                if ($f['score_min'] !== '') {
                    $m->where('cmp_lfl_leads.score >=', (int) $f['score_min']);
                }
                if ($f['desde'] !== '') {
                    $m->where('cmp_lfl_leads.created_at >=', $f['desde'] . ' 00:00:00');
                }
                if ($f['hasta'] !== '') {
                    $m->where('cmp_lfl_leads.created_at <=', $f['hasta'] . ' 23:59:59');
                }
                if ($f['sin_actividad']) {
                    $m->where('cmp_lfl_leads.next_activity_at', null);
                }
                if ($f['vencidas']) {
                    $m->where('cmp_lfl_leads.next_activity_at <', $now);
                }

                return $m;
            };

            $columns[] = [
                'stage' => $s,
                'total' => $build()->countAllResults(),
                'leads' => $build()->orderBy('cmp_lfl_leads.score', 'desc')->orderBy('cmp_lfl_leads.created_at', 'asc')->findAll(self::PER_COLUMN),
            ];
        }

        return $this->render('pipeline/index', [
            'columns'   => $columns,
            'filters'   => $f,
            'team'      => Team::users($cid),
            'services'  => (new ServiceModel())->options($cid, false),
            'sources'   => (new SourceModel())->options($cid, false),
            'campaigns' => (new CampaignModel())->options($cid, false),
            'canMove'   => $a->canWrite && $this->can('lfl.edit'),
            'perColumn' => self::PER_COLUMN,
        ]);
    }

    /**
     * Mueve una tarjeta. Responde JSON **siempre con el token CSRF nuevo**: el token
     * rota en cada POST, y el tablero hace varios seguidos sin recargar.
     */
    public function move()
    {
        $lead = $this->lead((int) $this->request->getPost('lead_id'), true);
        if (! $lead) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => lang('Lfl.errNotFound'), 'csrf' => csrf_hash()]);
        }

        $res = (new LeadService())->moveStage($lead, (int) $this->request->getPost('stage_id'), [
            'reason' => (string) $this->request->getPost('reason'),
            'note'   => (string) $this->request->getPost('note'),
        ], $this->userId());

        return $this->response->setJSON(['ok' => $res['ok'], 'message' => $res['message'], 'csrf' => csrf_hash()]);
    }
}
