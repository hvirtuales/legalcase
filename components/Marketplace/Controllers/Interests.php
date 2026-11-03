<?php

namespace Components\Marketplace\Controllers;

use App\Controllers\BaseController;
use Components\Marketplace\Libraries\MarketService;

/** El cruce: manifestar interés en una publicación y la bandeja del publicador. */
class Interests extends BaseController
{
    private MarketService $market;

    public function __construct()
    {
        $this->market = new MarketService();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** POST marketplace/publicacion/{id}/interes */
    public function send($listingId = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->back()->with('error', lang('Mkt.errNoCompany'));
        }

        $res = $this->market->expressInterest(
            (int) $listingId,
            $companyId,
            (int) session()->get('user_id'),
            trim((string) $this->request->getPost('message'))
        );

        return redirect()->to('marketplace/publicacion/' . (int) $listingId)
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** GET marketplace/interesados */
    public function inbox()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Marketplace\Views\interests', ['title' => lang('Mkt.interested'), 'rows' => null]);
        }

        $status = (string) $this->request->getGet('status');

        return view('Components\Marketplace\Views\interests', [
            'title'  => lang('Mkt.interested'),
            'rows'   => $this->market->inboxFor($companyId, $status),
            'status' => $status,
        ]);
    }

    /** POST marketplace/interesados/{id}/estado */
    public function changeStatus($interestId = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->back()->with('error', lang('Mkt.errNoCompany'));
        }

        $res = $this->market->changeInterestStatus(
            (int) $interestId,
            $companyId,
            (string) $this->request->getPost('status')
        );

        return redirect()->to('marketplace/interesados')->with($res['ok'] ? 'success' : 'error', $res['message']);
    }
}
