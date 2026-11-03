<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Model;

/**
 * Ayudante de listados server-side: búsqueda, orden y paginación sobre un
 * Model de CI4. Lee q/sort/dir/page del query string.
 *
 * Uso:
 *   $dt = (new DataTable($this->userModel, $this->request))
 *       ->searchable(['username', 'email'])
 *       ->sortable(['id', 'username', 'created_at'])
 *       ->defaultSort('id', 'desc')
 *       ->paginate(15);
 *   // $dt = ['rows', 'pager', 'q', 'sort', 'dir', 'perPage']
 */
class DataTable
{
    private Model $model;
    private RequestInterface $request;
    private array $searchable = [];
    private array $sortable    = [];
    private string $defaultCol = 'id';
    private string $defaultDir = 'asc';
    /** @var callable|null */
    private $queryFn;

    public function __construct(Model $model, RequestInterface $request)
    {
        $this->model   = $model;
        $this->request = $request;
    }

    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    public function sortable(array $columns): self
    {
        $this->sortable = $columns;

        return $this;
    }

    public function defaultSort(string $column, string $dir = 'asc'): self
    {
        $this->defaultCol = $column;
        $this->defaultDir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /** Aplica joins/selects extra antes de paginar: fn(Model $m). */
    public function query(callable $fn): self
    {
        $this->queryFn = $fn;

        return $this;
    }

    public function paginate(int $perPage = 15): array
    {
        $model = $this->model;
        $q     = trim((string) $this->request->getGet('q'));
        $sort  = (string) $this->request->getGet('sort');
        $dir   = strtolower((string) $this->request->getGet('dir')) === 'desc' ? 'desc' : 'asc';

        if ($this->queryFn) {
            ($this->queryFn)($model);
        }

        // Búsqueda: OR entre columnas, agrupada (para no romper el filtro soft-delete)
        if ($q !== '' && $this->searchable) {
            $model->groupStart();
            foreach (array_values($this->searchable) as $i => $col) {
                $i === 0 ? $model->like($col, $q) : $model->orLike($col, $q);
            }
            $model->groupEnd();
        }

        // Orden (solo columnas permitidas)
        if ($sort !== '' && in_array($sort, $this->sortable, true)) {
            $sortCol = $sort;
            $sortDir = $dir;
        } else {
            $sortCol = $this->defaultCol;
            $sortDir = $this->defaultDir;
            $sort    = '';
        }
        if ($sortCol !== '') {
            $model->orderBy($sortCol, $sortDir);
        }

        $rows = $model->paginate(max(1, $perPage));

        return [
            'rows'    => $rows,
            'pager'   => $model->pager,
            'q'       => $q,
            'sort'    => $sort !== '' ? $sortCol : '',
            'dir'     => $sortDir,
            'perPage' => $perPage,
        ];
    }
}
