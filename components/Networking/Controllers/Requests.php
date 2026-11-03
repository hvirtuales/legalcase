<?php

namespace Components\Networking\Controllers;

use App\Controllers\BaseController;
use Components\Networking\Libraries\NetworkService;

/** Pestañas «Recibidas» y «Enviadas» + acciones sobre las solicitudes. */
class Requests extends BaseController
{
    private NetworkService $net;

    public function __construct()
    {
        $this->net = new NetworkService();
    }

    private function me(): int
    {
        return (int) session()->get('user_id');
    }

    public function received()
    {
        $me = $this->me();

        return view('Components\Networking\Views\received', [
            'title'   => lang('Net.receivedTitle'),
            'rows'    => $this->net->received($me),
            'counts'  => $this->net->counts($me),
            'net'     => $this->net,
            'me'      => $me,
        ]);
    }

    public function sent()
    {
        $me = $this->me();

        return view('Components\Networking\Views\sent', [
            'title'  => lang('Net.sentTitle'),
            'rows'   => $this->net->sent($me),
            'counts' => $this->net->counts($me),
            'net'    => $this->net,
            'me'     => $me,
        ]);
    }

    /** POST networking/solicitar/{userId} */
    public function send($userId = null)
    {
        $res = $this->net->send(
            $this->me(),
            (int) $userId,
            trim((string) $this->request->getPost('message'))
        );

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** POST networking/responder/{requestId} con decision=accepted|rejected */
    public function respond($requestId = null)
    {
        $res = $this->net->respond(
            (int) $requestId,
            $this->me(),
            (string) $this->request->getPost('decision')
        );

        return redirect()->to('networking/recibidas')->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** POST networking/cancelar/{requestId} */
    public function cancel($requestId = null)
    {
        $res = $this->net->cancel((int) $requestId, $this->me());

        return redirect()->to('networking/enviadas')->with($res['ok'] ? 'success' : 'error', $res['message']);
    }
}
