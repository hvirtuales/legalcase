<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\Access;
use Components\LeadFlow\Libraries\Directory\ComplaintService;
use Components\LeadFlow\Models\ComplaintModel;

/**
 * La bandeja de denuncias. **Solo Super Admin.**
 *
 * La firma denunciada NO ve las denuncias sobre ella, y es deliberado: son avisos
 * sobre su propia conducta, y entregárselas directamente convierte el canal en un
 * camino hacia quien lo motivó. La plataforma decide qué traslada y cuándo —igual que
 * decide qué opinión se publica—, que es justo lo que hace creíble al canal.
 *
 * Y por eso tampoco cuelga de una empresa: se ven todas, de todas las firmas, como la
 * moderación de opiniones.
 */
class Complaints extends BaseLfl
{
    /** La bandeja: lo pendiente arriba. */
    public function index()
    {
        if (! Access::isSuperAdmin((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow'));
        }

        $model = new ComplaintModel();
        $rows  = $model->tray();

        /*
         * Los nombres de la firma y del abogado señalado, en dos consultas y no una
         * por fila. Se leen de `companies` —tabla del núcleo— solo para mostrarlas.
         */
        $companies = $profiles = [];
        $cids      = array_values(array_unique(array_column($rows, 'company_id')));
        $pids      = array_values(array_filter(array_unique(array_column($rows, 'profile_id'))));

        if ($cids) {
            foreach (db_connect()->table('companies')->select('id, name, slug')
                ->whereIn('id', $cids)->get()->getResultArray() as $c) {
                $companies[(int) $c['id']] = $c;
            }
        }
        if ($pids) {
            foreach ((new \Components\LeadFlow\Models\ProfileModel())
                ->select('id, display_name, slug')->whereIn('id', $pids)->findAll() as $p) {
                $profiles[(int) $p['id']] = $p;
            }
        }

        return $this->render('complaints/index', [
            'title'      => lang('Lfl.modTitle'),
            'rows'       => $rows,
            'companies'  => $companies,
            'profiles'   => $profiles,
            'pending'    => $model->countPending(),
        ]);
    }

    /**
     * Cierra una denuncia: atendida o descartada.
     *
     * El estado va en el POST y no en dos rutas distintas porque es la misma decisión
     * con dos salidas, y el servicio ya exige la nota cuando se descarta.
     */
    public function close($id = null)
    {
        if (! Access::isSuperAdmin((int) $this->userId())) {
            $this->flash('error', lang('Lfl.errSuperOnly'));

            return redirect()->to(base_url('leadflow'));
        }

        $row = (new ComplaintModel())->find((int) $id);
        if (! $row) {
            return $this->notFound();
        }

        $res = (new ComplaintService())->handle(
            $row,
            $this->request->getPost('decision') === 'handled',
            $this->userId(),
            (string) $this->request->getPost('note')
        );
        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);

        return redirect()->to(base_url('leadflow/moderacion/denuncias'));
    }
}
