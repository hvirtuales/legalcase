<?php

namespace Components\Networking\Controllers;

use App\Controllers\BaseController;
use Components\Networking\Libraries\NetworkService;

/** Pestaña «Mis conexiones»: lista de contactos aceptados. */
class Connections extends BaseController
{
    private NetworkService $net;

    public function __construct()
    {
        $this->net = new NetworkService();
    }

    public function index()
    {
        $me = (int) session()->get('user_id');

        return view('Components\Networking\Views\connections', [
            'title'  => lang('Net.connections'),
            'rows'   => $this->net->connections($me),
            'counts' => $this->net->counts($me),
            'net'    => $this->net,
            'me'     => $me,
            'active' => null,
        ]);
    }

    /** POST networking/conexiones/eliminar/{requestId} */
    public function remove($requestId = null)
    {
        $res = $this->net->removeConnection((int) $requestId, (int) session()->get('user_id'));

        return redirect()->to('networking/conexiones')->with($res['ok'] ? 'success' : 'error', $res['message']);
    }
}
