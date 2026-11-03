<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use Components\Contabilidad\Models\VoucherModel;
use Components\Contabilidad\Models\VoucherTypeModel;

/**
 * Verificación PÚBLICA de comprobantes (sin login). El QR del comprobante
 * apunta aquí. Muestra empresa, tipo, fecha, beneficiario, totales y estado.
 */
class Verify extends BaseController
{
    public function show($id = null)
    {
        $v = $id ? (new VoucherModel())->find($id) : null;

        $data = ['title' => 'Verificación de comprobante', 'v' => null];
        if ($v) {
            $data['v']       = $v;
            $data['type']    = (new VoucherTypeModel())->find($v['type_id']);
            $data['company'] = (new \App\Models\CompanyModel())->find($v['company_id']);
        }

        return view('Components\Contabilidad\Views\verify\show', $data);
    }
}
