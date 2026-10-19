<?php

namespace App\Support\Admin;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The server side of a React list (resources/js/components/data-table.tsx):
 * ?q= searches, ?sort=&dir= orders by an allowed column, ?filter[key]=
 * narrows, and the result is paged. Only the sorts and filters a page
 * names are honoured.
 */
class AdminTable
{
    /** @var array<string, string|Closure> */
    private array $sorts = [];

    /** @var array<string, Closure> */
    private array $filters = [];

    private ?Closure $search = null;

    private ?string $defaultSort = null;

    private string $defaultDir = 'desc';

    public function __construct(private Builder $query, private Request $request) {}

    public static function for(Builder $query, ?Request $request = null): self
    {
        return new self($query, $request ?? request());
    }

    /** @param  Closure(Builder, string): void  $search */
    public function search(Closure $search): self
    {
        $this->search = $search;

        return $this;
    }

    /** @param  array<string, string|Closure>  $sorts  key => column, or a closure given the query and direction */
    public function sorts(array $sorts, ?string $default = null, string $dir = 'desc'): self
    {
        $this->sorts = $sorts;
        $this->defaultSort = $default;
        $this->defaultDir = $dir;

        return $this;
    }

    /** @param  array<string, Closure(Builder, string): void>  $filters */
    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /** What the list is showing, for the page (TableState). @return array<string, mixed> */
    public function state(): array
    {
        $sort = $this->request->query('sort');
        $sort = is_string($sort) && array_key_exists($sort, $this->sorts) ? $sort : $this->defaultSort;
        $dir = $this->request->query('dir') === 'asc' ? 'asc' : ($this->request->has('dir') ? 'desc' : $this->defaultDir);

        $filters = [];
        foreach ((array) $this->request->query('filter', []) as $key => $value) {
            if (array_key_exists($key, $this->filters) && is_string($value) && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return ['q' => trim((string) $this->request->query('q')), 'sort' => $sort, 'dir' => $dir, 'filters' => (object) $filters];
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        $state = $this->state();
        $query = $this->query;

        if ($state['q'] !== '' && $this->search) {
            $query->where(fn (Builder $q) => ($this->search)($q, $state['q']));
        }

        foreach ((array) $state['filters'] as $key => $value) {
            ($this->filters[$key])($query, $value);
        }

        if ($state['sort']) {
            $sort = $this->sorts[$state['sort']];
            $sort instanceof Closure ? $sort($query, $state['dir']) : $query->orderBy($sort, $state['dir']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /** A LIKE pattern for a search term, with % and _ taken literally. */
    public static function like(string $term): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term) . '%';
    }
}
