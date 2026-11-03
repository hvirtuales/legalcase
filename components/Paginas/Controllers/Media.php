<?php

namespace Components\Paginas\Controllers;

use App\Controllers\BaseController;
use App\Libraries\UploadService;
use Components\Paginas\Libraries\ImageOptimizer;
use Components\Paginas\Models\MediaModel;

/**
 * Biblioteca de medios de la empresa: subir, listar, elegir y borrar.
 *
 * Se apoya en el `UploadService` del núcleo, que es quien decide la política:
 * tipos admitidos, tamaño máximo (3 MB), **conversión a WebP y acotado del
 * ancho** (`ImageOptimizer`) y, si el almacenamiento configurado es remoto
 * (S3/R2), subida al bucket devolviendo su URL pública. Aquí no se duplica
 * ninguna de esas reglas.
 *
 * Lo que sí vive aquí es el **registro**: antes las imágenes se subían y no
 * quedaba anotado en ninguna parte que existieran, así que en cuanto un bloque
 * dejaba de apuntar a una, el archivo se perdía de vista y no había forma de
 * reutilizarla en otra página.
 *
 * Las imágenes de una página son **públicas por definición** —se ven en la web
 * publicada—, así que van a `public/uploads/paginas/` y no al almacén privado de
 * `writable/` que el contrato §11 reserva para los adjuntos.
 */
class Media extends BaseController
{
    /** Empresa activa. null = contexto Global (sin empresa). */
    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** La biblioteca, como pantalla. */
    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Paginas\Views\media', ['title' => lang('Pag.media'), 'items' => null]);
        }

        $q = trim((string) $this->request->getGet('q'));

        return view('Components\Paginas\Views\media', [
            'title'    => lang('Pag.media'),
            'items'    => (new MediaModel())->forCompany($companyId, $q),
            'q'        => $q,
            'maxWidth' => ImageOptimizer::MAX_WIDTH,
            'usedIn'   => $this->usage($companyId),
        ]);
    }

    /**
     * En cuántas páginas se usa cada referencia.
     *
     * Se resuelve con **una** consulta y contando en PHP, no con un LIKE por
     * imagen: con cien imágenes serían cien consultas para pintar una rejilla.
     *
     * @return array<string,int> ruta => nº de páginas
     */
    private function usage(int $companyId): array
    {
        $uso   = [];
        $pages = (new \Components\Paginas\Models\PageModel())
            ->select('blocks')->where('company_id', $companyId)->findAll();

        $rutas = array_column((new MediaModel())->forCompany($companyId), 'path');

        foreach ($pages as $p) {
            $json = is_array($p['blocks'] ?? null) ? json_encode($p['blocks']) : (string) ($p['blocks'] ?? '');
            foreach ($rutas as $ruta) {
                if ($ruta !== '' && str_contains($json, $ruta)) {
                    $uso[$ruta] = ($uso[$ruta] ?? 0) + 1;
                }
            }
        }

        return $uso;
    }

    /**
     * La biblioteca en JSON, para el selector del editor.
     *
     * Se sirve por su propia ruta y no incrustada en la pantalla del editor:
     * una empresa puede acumular cientos de imágenes y no tiene sentido
     * cargarlas todas cada vez que se abre una página para tocar un título.
     */
    public function library()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return $this->response->setJSON(['ok' => true, 'items' => []]);
        }

        helper('media');
        $q = trim((string) $this->request->getGet('q'));

        $items = array_map(static fn ($m) => [
            'id'      => (int) $m['id'],
            'url'     => $m['path'],
            'display' => media_url($m['path']),
            'name'    => (string) ($m['name'] ?? ''),
            'width'   => (int) ($m['width'] ?? 0),
            'height'  => (int) ($m['height'] ?? 0),
        ], (new MediaModel())->forCompany($companyId, $q, 120));

        return $this->response->setJSON(['ok' => true, 'items' => $items]);
    }

    /**
     * Sube una imagen y la anota en la biblioteca.
     *
     * `maxWidth` permite que un espacio con medida propia —una miniatura, un
     * logo— no guarde píxeles que nadie va a ver. Llega del navegador, así que
     * se **acota** entre 0 y el máximo del sistema en vez de creérselo.
     */
    public function upload()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => lang('Pag.errNoCompany')]);
        }

        $pedido   = (int) $this->request->getPost('maxWidth');
        $maxWidth = $pedido > 0 ? min($pedido, ImageOptimizer::MAX_WIDTH) : ImageOptimizer::MAX_WIDTH;

        $file = $this->request->getFile('file');
        $name = $file ? $file->getClientName() : '';
        // TRES argumentos a propósito: es la firma que tiene cualquier núcleo
        // de la plataforma. El cuarto (`maxWidth`) lo añadió esta versión, y
        // usarlo aquí ataría el componente a ella (contrato §0.1). Lo que sí
        // hace el componente es optimizar DESPUÉS con su propia copia; si el
        // núcleo ya lo hizo, esa segunda pasada no toca nada.
        $url  = (new UploadService())->storeImage($file, 'paginas', 'pag');
        $url  = $url ? $this->optimizeLocal($url, $maxWidth) : $url;

        if (!$url) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'    => false,
                'error' => lang('Pag.errUpload'),
                'csrf'  => csrf_hash(),
            ]);
        }

        helper('media');

        // Las medidas se leen del archivo ya optimizado; con almacenamiento
        // remoto no hay copia local que medir y se dejan en blanco.
        $local  = FCPATH . ltrim($url, '/');
        $medida = is_file($local) ? (new ImageOptimizer())->size($local) : ['width' => 0, 'height' => 0];

        (new MediaModel())->remember($companyId, $url, [
            'name'   => mb_substr($name, 0, 255),
            'mime'   => 'image/webp',
            'width'  => $medida['width'] ?: null,
            'height' => $medida['height'] ?: null,
            'bytes'  => is_file($local) ? filesize($local) : null,
        ]);

        return $this->response->setJSON([
            'ok' => true,
            // `url` es lo que se guarda en el bloque (relativa o absoluta) y
            // `display` lo que el editor pinta ya resuelto.
            'url'     => $url,
            'display' => media_url($url),
            'csrf'    => csrf_hash(),
        ]);
    }

    /**
     * Deja la copia local en WebP y acotada, y devuelve la referencia nueva.
     *
     * Solo actúa sobre lo **local**: con almacenamiento remoto (S3/R2) el
     * archivo ya no está en disco cuando volvemos aquí —lo subió y lo borró
     * `UploadService`—, así que ahí la optimización la tiene que haber hecho el
     * núcleo. Es la única parte que depende de la versión del núcleo, y
     * degrada sola: sin ella la imagen se sirve tal cual se subió.
     */
    private function optimizeLocal(string $url, int $maxWidth): string
    {
        if (!str_starts_with($url, '/uploads/')) {
            return $url;
        }

        $local = FCPATH . ltrim($url, '/');
        if (!is_file($local)) {
            return $url;
        }

        $nuevo = (new ImageOptimizer())->optimize($local, $maxWidth);

        return $nuevo === $local ? $url : dirname($url) . '/' . basename($nuevo);
    }

    /**
     * Quita una imagen de la biblioteca.
     *
     * **El archivo se borra también**, y esa es la parte delicada: si alguna
     * página lo sigue usando se quedaría con un hueco. Por eso se avisa de en
     * cuántas está antes de borrar (`usedIn()`), y la confirmación lo dice.
     */
    public function delete($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('paginas/medios')->with('error', lang('Pag.errNoCompany'));
        }

        $model = new MediaModel();
        $item  = $model->findForCompany((int) $id, $companyId);
        if (!$item) {
            return redirect()->to('paginas/medios')->with('error', lang('Pag.errNotFound'));
        }

        $model->delete((int) $item['id']);

        // Solo se borra del disco lo local: una URL remota la gobierna su
        // bucket, y borrarla aquí exigiría credenciales de escritura que esta
        // pantalla no tiene por qué usar.
        $local = FCPATH . ltrim((string) $item['path'], '/');
        if (str_starts_with((string) $item['path'], '/uploads/') && is_file($local)) {
            @unlink($local);
        }

        return redirect()->to('paginas/medios')->with('success', lang('Pag.okMediaDeleted'));
    }
}
