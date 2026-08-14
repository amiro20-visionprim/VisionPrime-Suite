<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GscMetricsController extends Controller
{
    public function pages(Request $request, CurrentOrganization $org): Response
    {
        $filters = $this->filters($request, $org);

        $query = \DB::table('gsc_page_metrics')
            ->join('gsc_properties', 'gsc_properties.id', '=', 'gsc_page_metrics.gsc_property_id')
            ->join('sites', 'sites.id', '=', 'gsc_properties.site_id')
            ->where('sites.organization_id', $org->id());

        $this->applyFilters($query, $filters, 'gsc_page_metrics.date');

        $rows = $query->latest('gsc_page_metrics.date')->paginate(50)->withQueryString();

        return Inertia::render('App/Gsc/Pages', [
            'metrics' => $rows,
            'sites' => $this->sites($org),
            'filters' => $filters,
        ]);
    }

    public function queries(Request $request, CurrentOrganization $org): Response
    {
        $filters = $this->filters($request, $org);

        $query = \DB::table('gsc_query_metrics')
            ->join('gsc_properties', 'gsc_properties.id', '=', 'gsc_query_metrics.gsc_property_id')
            ->join('sites', 'sites.id', '=', 'gsc_properties.site_id')
            ->where('sites.organization_id', $org->id());

        $this->applyFilters($query, $filters, 'gsc_query_metrics.date');

        $rows = $query->latest('gsc_query_metrics.date')->paginate(50)->withQueryString();

        return Inertia::render('App/Gsc/Queries', [
            'metrics' => $rows,
            'sites' => $this->sites($org),
            'filters' => $filters,
        ]);
    }

    /** @return array<string, string|null> */
    private function filters(Request $request, CurrentOrganization $org): array
    {
        $siteId = $request->query('site_id');
        if ($siteId !== null && ! Site::query()->where('organization_id', $org->id())->where('id', (int) $siteId)->exists()) {
            $siteId = null;
        }

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        return [
            'site_id' => $siteId !== null ? (string) $siteId : null,
            'date_from' => $this->validDate($dateFrom),
            'date_to' => $this->validDate($dateTo),
        ];
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /** @param  Builder  $query
     * @param  array<string, string|null>  $filters
     */
    private function applyFilters($query, array $filters, string $dateColumn): void
    {
        if ($filters['site_id'] !== null) {
            $query->where('sites.id', (int) $filters['site_id']);
        }
        if ($filters['date_from'] !== null) {
            $query->where($dateColumn, '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== null) {
            $query->where($dateColumn, '<=', $filters['date_to']);
        }
    }

    /** @return array<int, array{id: int, name: string}> */
    private function sites(CurrentOrganization $org): array
    {
        return Site::query()
            ->where('organization_id', $org->id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Site $site): array => ['id' => $site->getKey(), 'name' => $site->name])
            ->all();
    }
}
