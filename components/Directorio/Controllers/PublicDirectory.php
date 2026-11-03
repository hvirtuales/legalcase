<?php

namespace Components\Directorio\Controllers;

use App\Controllers\BaseController;
use Components\Directorio\Libraries\BoardService;
use Components\Directorio\Models\BoardModel;

/**
 * Páginas públicas del grupo.
 *
 * `/directorio-publico/{grupo}` es el índice de sus **vistas** publicadas —o un
 * salto directo si solo hay una— y `/directorio-publico/{grupo}/{vista}` es la
 * vista en sí: listado y mapa.
 *
 * Sin `auth` ni contexto de sesión — el grupo lo identifica su **slug**, y solo
 * se sirve si es una fila-tenant **activa**.
 *
 * La vista es **autosuficiente** (contrato base §7.3): layout propio con la
 * hoja de estilos en línea, iconos SVG en línea y tipografía del sistema, para
 * que la carpeta del componente se pueda llevar a otra plataforma sin que la
 * página se rompa.
 */
class PublicDirectory extends BaseController
{
    public function show(string $slug)
    {
        $tenant = (new \App\Models\CompanyModel())
            ->where('slug', $slug)
            ->where('type', 'tenant')
            ->where('is_active', 1)
            ->first();

        if (!$tenant) {
            return $this->response->setStatusCode(404)
                ->setBody(view('Components\Directorio\Views\public\not_found'));
        }

        $boards = (new BoardModel())->publishedFor((int) $tenant['id']);

        // Con una sola vista publicada no tiene sentido un índice de una fila:
        // se va derecho a ella, que es lo que el visitante venía a ver.
        if (count($boards) === 1) {
            return redirect()->to('directorio-publico/' . $slug . '/' . $boards[0]['slug']);
        }

        return view('Components\Directorio\Views\public\directory', [
            'tenant' => $tenant,
            'boards' => $boards,
        ]);
    }

    /**
     * Una **vista** del directorio: `/directorio-publico/{grupo}/{vista}`.
     *
     * Solo responde si la vista está publicada Y es de ese grupo. No se
     * distingue entre «no existe» y «está en borrador»: la diferencia solo
     * serviría para sondear qué tiene montado cada grupo.
     */
    public function board(string $slug, string $boardSlug)
    {
        $tenant = $this->tenant($slug);
        if (!$tenant) {
            return $this->notFound();
        }

        $model = new BoardModel();
        $board = $model->publishedBySlug((int) $tenant['id'], $boardSlug);
        if (!$board) {
            return $this->notFound();
        }

        // Contador de visitas: `set()` + `update()` en vez de leer y sumar,
        // para no perder cuentas con dos visitas a la vez.
        $model->builder()->where('id', (int) $board['id'])->set('views', 'views + 1', false)->update();

        return view('Components\Directorio\Views\public\board', [
            'tenant'    => $tenant,
            'board'     => $board,
            'companies' => (new BoardService())->companiesFor($board),
        ]);
    }

    /** El grupo por su slug, si es una fila-tenant activa. */
    private function tenant(string $slug): ?array
    {
        return (new \App\Models\CompanyModel())
            ->where('slug', $slug)
            ->where('type', 'tenant')
            ->where('is_active', 1)
            ->first();
    }

    private function notFound()
    {
        return $this->response->setStatusCode(404)
            ->setBody(view('Components\Directorio\Views\public\not_found'));
    }
}
