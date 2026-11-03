<?php

namespace Components\Networking\Controllers;

use App\Controllers\BaseController;
use Components\Networking\Libraries\NetworkService;

/** Hilo de mensajes de una conexión. */
class Messages extends BaseController
{
    private NetworkService $net;

    public function __construct()
    {
        $this->net = new NetworkService();
    }

    /** GET networking/mensajes/{requestId} */
    public function thread($requestId = null)
    {
        $me   = (int) session()->get('user_id');
        $conn = $this->net->connection((int) $requestId, $me);
        if (!$conn) {
            return redirect()->to('networking/conexiones')->with('error', lang('Net.conversationNotFound'));
        }

        $otherId = $this->net->otherUserId($conn, $me);

        return view('Components\Networking\Views\connections', [
            'title'    => lang('Net.connections'),
            'rows'     => $this->net->connections($me),
            'counts'   => $this->net->counts($me),
            'net'      => $this->net,
            'me'       => $me,
            'active'   => $conn,
            'messages' => $this->net->thread((int) $requestId, $me),
            'other'    => $this->net->userOf($otherId),
            'profile'  => $this->net->profileOf($otherId),
            'card'     => $this->net->card($otherId),
        ]);
    }

    /** POST networking/mensajes/{requestId} */
    public function send($requestId = null)
    {
        $res = $this->net->postMessage(
            (int) $requestId,
            (int) session()->get('user_id'),
            trim((string) $this->request->getPost('body'))
        );

        return redirect()->to('networking/mensajes/' . (int) $requestId)
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }
}
