<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Server-side pagination helper. Used by index controllers for high-volume tables
 * (sales_invoices, purchase_invoices, payments, ledgers, cash-flow events, etc.) where
 * client-side DataTables would force loading every row.
 *
 * Usage:
 *   $p = Paginator::fromQuery();
 *   $repo->paginate($filters, $p->page, $p->perPage)  →  ['rows'=>..., 'total'=>...]
 *   ...then in the view:
 *   $p->buildLinks($result['total'], '/sales-invoices', $extraQuery);
 *
 * The view partial `partials/pagination.php` consumes the link array.
 */
final class Paginator
{
    public const DEFAULT_PER_PAGE = 50;
    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    public int    $page;
    public int    $perPage;
    public string $search;       // free-text query (q= param)

    public function __construct(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE, string $search = '')
    {
        $this->page    = \max(1, $page);
        $this->perPage = \in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
        $this->search  = \trim($search);
    }

    public static function fromQuery(): self
    {
        return new self(
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? self::DEFAULT_PER_PAGE),
            (string) ($_GET['q'] ?? '')
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function totalPages(int $total): int
    {
        return (int) \ceil(\max(1, $total) / $this->perPage);
    }

    /**
     * Build a SQL LIKE clause + bound params for a free-text search across N columns.
     * Returns ['', []] when search is empty (caller falls through with no WHERE addition).
     *
     *   [$where, $params] = $p->searchClause(['si.invoice_number', 'c.name']);
     *   $sql = "... WHERE 1 {$where} ORDER BY ...";
     *
     * @param string[] $columns SQL column expressions to LIKE-match
     * @return array{0:string,1:array<int,string>}
     */
    public function searchClause(array $columns): array
    {
        if ($this->search === '' || $columns === []) {
            return ['', []];
        }
        $like   = '%' . $this->search . '%';
        $parts  = [];
        $params = [];
        foreach ($columns as $col) {
            $parts[]  = "{$col} LIKE ?";
            $params[] = $like;
        }
        return [' AND (' . \implode(' OR ', $parts) . ')', $params];
    }

    /**
     * Build a context object for the view partial. Carries forward any extra query
     * params (filters etc.) so paging/search preserves them.
     *
     * @param array<string,scalar|null> $extraQuery
     */
    public function buildContext(int $total, string $baseUrl, array $extraQuery = []): array
    {
        $totalPages = $this->totalPages($total);
        $page       = \min($this->page, \max(1, $totalPages));

        // Strip empty/null values so the URL stays compact.
        $base = \array_filter(
            $extraQuery,
            static fn ($v) => $v !== null && $v !== '' && $v !== 0
        );

        $linkFor = static function (int $p, ?int $pp = null, ?string $q = null) use ($base, $baseUrl): string {
            $qs            = $base;
            $qs['page']    = $p;
            if ($pp !== null) { $qs['per_page'] = $pp; }
            if ($q !== null)  { $qs['q']        = $q; }
            return $baseUrl . (\str_contains($baseUrl, '?') ? '&' : '?') . \http_build_query($qs);
        };

        return [
            'page'        => $page,
            'per_page'    => $this->perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
            'search'      => $this->search,
            'base_url'    => $baseUrl,
            'first_link'  => $linkFor(1),
            'prev_link'   => $linkFor(\max(1, $page - 1)),
            'next_link'   => $linkFor(\min($totalPages, $page + 1)),
            'last_link'   => $linkFor($totalPages),
            'page_link'   => $linkFor,                 // closure: pass a page number
            'per_page_options' => self::PER_PAGE_OPTIONS,
            'extra_query' => $base,
            'from'        => $total === 0 ? 0 : ($page - 1) * $this->perPage + 1,
            'to'          => \min($page * $this->perPage, $total),
        ];
    }
}
