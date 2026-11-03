<?php

namespace Components\Networking\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Networking\Libraries\NetworkService;
use Components\Networking\Libraries\PlanGuard;
use Components\Networking\Models\ProfileModel;

/**
 * Pestaña «Descubrir»: todos los usuarios activos que comparten su perfil en la red
 * (global: sin filtro por empresa).
 */
class Discover extends BaseController
{
    public function index()
    {
        $me  = (int) session()->get('user_id');
        $net = new NetworkService();

        $dt = (new DataTable(new ProfileModel(), $this->request))
            // La empresa no entra en la búsqueda SQL: sale de la relación del usuario
            // (elegida o primera accesible), no de una columna del perfil.
            ->searchable([
                'users.first_name', 'users.last_name', 'users.username',
                'cmp_net_profiles.position', 'cmp_net_profiles.branch',
                'cmp_net_profiles.interests',
            ])
            ->sortable(['users.first_name', 'cmp_net_profiles.position', 'cmp_net_profiles.branch'])
            ->defaultSort('users.first_name', 'asc')
            ->query(static function ($m) use ($me) {
                $m->select('cmp_net_profiles.*, users.first_name, users.last_name, users.username, users.email, users.phone')
                    ->join('users', 'users.id = cmp_net_profiles.user_id')
                    ->where('cmp_net_profiles.share_profile', 1)
                    ->where('users.is_active', 1)
                    ->where('users.deleted_at', null)
                    ->where('cmp_net_profiles.user_id !=', $me);
            })
            ->paginate(12);

        // Estado de la relación y datos derivados (persona + empresa) de cada tarjeta.
        $relations = [];
        $cards     = [];

        foreach ($dt['rows'] as $row) {
            $uid             = (int) $row['user_id'];
            $relations[$uid] = $net->relation($me, $uid);
            $cards[$uid]     = $net->card($uid, $row);
        }

        $guard = new PlanGuard();

        return view('Components\Networking\Views\discover', [
            'title'       => lang('Net.discover'),
            'dt'          => $dt,
            'relations'   => $relations,
            'cards'       => $cards,
            'counts'      => $net->counts($me),
            'me'          => $me,
            'net'         => $net,
            'myProfile'   => $net->profileOf($me),
            'planEnabled' => $guard->serviceEnabled(),
            'quota'       => $guard->connectionsLeft($me),
        ]);
    }
}
