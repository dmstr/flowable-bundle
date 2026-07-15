<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared concerns for the Flowable read providers: per-request client
 * resolution, translation of API Platform pagination/filter parameters to the
 * Flowable start/size query, and loss-free mapping of the Flowable list
 * envelope onto a TraversablePaginator (design D9).
 */
abstract class AbstractFlowableProvider
{
    public function __construct(
        protected readonly FlowableClientLocator $locator,
        protected readonly RequestStack $requestStack,
    ) {
    }

    protected function client(): FlowableClientInterface
    {
        return $this->locator->resolve($this->queryParam('apiConfiguration'));
    }

    protected function queryParam(string $key): ?string
    {
        $value = $this->requestStack->getCurrentRequest()?->query->get($key);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * @param list<string> $whitelist filter keys passed through to Flowable
     * @param ?string $defaultSort Flowable sort field applied when the client
     *   requests no explicit `sort` — e.g. the resource's timestamp column so
     *   collections default to newest first. Flowable has no `updated_at`; each
     *   resource maps this to its own time field (createTime, deployTime, …).
     * @param 'asc'|'desc' $defaultOrder direction paired with $defaultSort
     * @return array<string,scalar>
     */
    protected function listQuery(array $whitelist, ?string $defaultSort = null, string $defaultOrder = 'desc'): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $size = (int) ($request?->query->get('itemsPerPage') ?? 30);
        $size = max(1, min(200, $size));

        $query = ['start' => ($page - 1) * $size, 'size' => $size];

        // Sorting: an explicit client `sort` wins; otherwise fall back to the
        // resource default. Flowable expects the pair sort=<field>&order=<dir>.
        $sort = $this->queryParam('sort') ?? $defaultSort;
        if ($sort !== null) {
            $order = strtolower((string) ($this->queryParam('order') ?? $defaultOrder));
            $query['sort'] = $sort;
            $query['order'] = $order === 'asc' ? 'asc' : 'desc';
        }

        foreach ($whitelist as $key) {
            $value = $this->queryParam($key);
            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * Map admin/Hydra relation filters (the IRI carried on a *_reference
     * property) to the Flowable id query field, e.g.
     * ?processInstance=/api/flowable/process_instances/{id} →
     * processInstanceId={id}. A bare id is accepted too. Relations absent from
     * the request are skipped.
     *
     * The API Platform Admin filters a related collection by the relation
     * property name (processInstance), not the flat Flowable id field
     * (processInstanceId); without this translation the filter is dropped and
     * the relation tab lists every task/instance instead of the owned ones.
     *
     * @param array<string,string> $map relationProperty => flowableIdField
     * @return array<string,string>
     */
    protected function relationFilters(array $map): array
    {
        $out = [];
        foreach ($map as $relation => $idField) {
            $value = $this->queryParam($relation);
            if ($value !== null) {
                $out[$idField] = $this->idFromIri($value);
            }
        }

        return $out;
    }

    /** Extract the last path segment of an IRI; passes a bare id through. */
    protected function idFromIri(string $value): string
    {
        $path = rtrim((string) (parse_url(trim($value), PHP_URL_PATH) ?: $value), '/');
        $pos = strrpos($path, '/');

        return rawurldecode($pos === false ? $path : substr($path, $pos + 1));
    }

    /**
     * @param array<string,mixed> $envelope Flowable list envelope
     * @param callable(array<string,mixed>):object $map
     */
    protected function paginate(array $envelope, callable $map): TraversablePaginator
    {
        $rows = \is_array($envelope['data'] ?? null) ? $envelope['data'] : [];
        $items = array_map($map, $rows);

        $total = (int) ($envelope['total'] ?? \count($items));
        $size = (int) ($envelope['size'] ?? \count($items));
        $size = $size > 0 ? $size : max(1, \count($items));
        $start = (int) ($envelope['start'] ?? 0);
        $currentPage = (int) floor($start / $size) + 1;

        return new TraversablePaginator(new \ArrayIterator($items), $currentPage, $size, $total);
    }
}
