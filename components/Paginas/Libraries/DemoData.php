<?php

namespace Components\Paginas\Libraries;

use Components\Paginas\Models\PageModel;
use Components\Paginas\Models\SubmissionModel;

/**
 * Datos de ejemplo para ver el componente funcionando sin tener que armar una
 * página a mano.
 *
 * Solo se ofrece si la empresa **no tiene ninguna página** (`isEmpty()`): nunca
 * pisa datos reales. Reutiliza las plantillas de arranque en vez de repetir aquí
 * los árboles de bloques —si mañana mejora una plantilla, mejora la demo.
 */
class DemoData
{
    /** Cuántas páginas crea. El controlador comprueba que quepan en el cupo. */
    public const PAGES = 3;

    public function isEmpty(int $companyId): bool
    {
        return (new PageModel())->where('company_id', $companyId)->countAllResults() === 0;
    }

    /**
     * @return array{pages:int,messages:int}
     */
    public function generate(int $companyId, int $userId): array
    {
        $lib = new TemplateLibrary();
        $lib->seed(); // idempotente: si ya estaban, no hace nada

        $svc      = new PageService();
        $pages    = new PageModel();
        $created  = [];

        $plan = [
            ['code' => 'landing', 'title' => lang('Pag.demoHome'), 'status' => 'published'],
            ['code' => 'about', 'title' => lang('Pag.demoAbout'), 'status' => 'published'],
            ['code' => 'contact', 'title' => lang('Pag.demoContact'), 'status' => 'draft'],
        ];

        $templates = new \Components\Paginas\Models\TemplateModel();

        foreach ($plan as $item) {
            $tpl = $templates->where('code', $item['code'])->where('company_id', null)->first();
            if (!$tpl) {
                continue;
            }

            $pages->insert([
                'company_id'       => $companyId,
                'user_id'          => $userId ?: null,
                'title'            => $item['title'],
                'slug'             => $svc->uniqueSlug($companyId, $item['title']),
                'status'           => $item['status'],
                'blocks'           => $svc->reindex(is_array($tpl['blocks'] ?? null) ? $tpl['blocks'] : []),
                'settings'         => ['accent' => '', 'width' => 'boxed', 'header' => '1', 'footer' => '1'],
                'meta_title'       => $item['title'],
                'meta_description' => lang('Pag.demoMeta'),
                'published_at'     => $item['status'] === 'published' ? date('Y-m-d H:i:s') : null,
            ]);

            $created[] = ['id' => (int) $pages->getInsertID(), 'code' => $item['code']];
        }

        return ['pages' => count($created), 'messages' => $this->seedMessages($companyId, $created)];
    }

    /**
     * Dos mensajes de ejemplo en la página que tiene formulario, para que la
     * bandeja no aparezca vacía.
     */
    private function seedMessages(int $companyId, array $created): int
    {
        $pages = new PageModel();
        $svc   = new PageService();
        $subs  = new SubmissionModel();
        $n     = 0;

        foreach ($created as $item) {
            if ($item['code'] !== 'landing') {
                continue;
            }
            $page = $pages->find($item['id']);
            if (!$page) {
                continue;
            }
            $form = $this->firstForm(is_array($page['blocks'] ?? null) ? $page['blocks'] : [], $svc);
            if (!$form) {
                continue;
            }

            $samples = [
                [lang('Pag.tplFieldName') => 'Laura Restrepo', lang('Pag.tplFieldEmail') => 'laura@ejemplo.co', lang('Pag.tplFieldPhone') => '3001234567', lang('Pag.tplFieldMessage') => lang('Pag.demoMsg1')],
                [lang('Pag.tplFieldName') => 'Andrés Gómez', lang('Pag.tplFieldEmail') => 'andres@ejemplo.co', lang('Pag.tplFieldPhone') => '', lang('Pag.tplFieldMessage') => lang('Pag.demoMsg2')],
            ];

            foreach ($samples as $data) {
                $subs->insert([
                    'company_id' => $companyId,
                    'page_id'    => (int) $page['id'],
                    'block_uid'  => (string) ($form['id'] ?? ''),
                    'form_title' => (string) ($form['props']['title'] ?? ''),
                    'data'       => $data,
                    'ip'         => '127.0.0.1',
                    'is_read'    => 0,
                ]);
                $n++;
            }
        }

        return $n;
    }

    /** Primer bloque de tipo formulario del árbol. */
    private function firstForm(array $tree, PageService $svc): ?array
    {
        foreach ($tree as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (($node['type'] ?? '') === 'form') {
                return $node;
            }
            if (!empty($node['children']) && is_array($node['children'])) {
                $found = $this->firstForm($node['children'], $svc);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
