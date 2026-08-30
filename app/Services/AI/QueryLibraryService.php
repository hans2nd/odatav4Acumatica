<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use App\Models\AiQueryLibrary;
use App\Models\Connection;
use App\Models\QueryRepository;

class QueryLibraryService
{
    /**
     * Generate a deterministic canonical array representation of a validated QueryPlan.
     */
    public function canonicalize(QueryPlan $plan, int $connectionId, ?string $domainContext = null): array
    {
        // 1. Tables (alphabetical sort, lowercase)
        $tables = array_values(array_unique(array_map('strtolower', $plan->tables ?? [])));
        sort($tables);

        // 2. Dimensions (alphabetical sort, lowercase)
        $dimensions = array_values(array_unique(array_map('strtolower', $plan->dimensions ?? [])));
        sort($dimensions);

        // 3. Metrics (sorted by normalized alias/name)
        $metrics = [];
        foreach ($plan->metrics ?? [] as $m) {
            $alias = strtolower($m['alias'] ?? preg_replace('/[^a-zA-Z0-9_]/', '_', $m['name'] ?? ''));
            $metrics[] = [
                'name'        => strtolower($m['name'] ?? ''),
                'aggregation' => strtoupper($m['aggregation'] ?? 'SUM'),
                'expression'  => strtolower($m['expression'] ?? ''),
                'alias'       => $alias,
            ];
        }
        usort($metrics, function ($a, $b) {
            return strcmp($a['alias'] . '_' . $a['aggregation'], $b['alias'] . '_' . $b['aggregation']);
        });

        // 4. Filters (sorted by field + operator)
        $filters = [];
        foreach ($plan->filters ?? [] as $f) {
            $val = $f['value'] ?? '';
            if (is_array($val)) {
                $val = array_map(fn($v) => is_string($v) ? strtolower(trim($v)) : $v, $val);
                sort($val);
            } elseif (is_string($val)) {
                $val = strtolower(trim($val));
            }
            $filters[] = [
                'field'    => strtolower(trim($f['field'] ?? '')),
                'operator' => strtoupper(trim($f['operator'] ?? '=')),
                'value'    => $val,
            ];
        }
        usort($filters, function ($a, $b) {
            return strcmp($a['field'] . '_' . $a['operator'], $b['field'] . '_' . $b['operator']);
        });

        // 5. Sorts (preserving semantic order, normalized)
        $sorts = [];
        foreach ($plan->sorts ?? [] as $s) {
            $sorts[] = [
                'field'     => strtolower(trim($s['field'] ?? '')),
                'direction' => strtoupper(trim($s['direction'] ?? 'ASC')),
            ];
        }

        // 6. Limit
        $limit = isset($plan->limit) ? (int)$plan->limit : null;

        // 7. Comparison
        $comparison = null;
        if (!empty($plan->comparison)) {
            $comp = $plan->comparison;
            $outputs = array_values(array_map('strtolower', (array)($comp['output'] ?? ['value'])));
            sort($outputs);
            $comparison = [
                'type'    => strtolower($comp['type'] ?? 'previous_period'),
                'period'  => strtolower($comp['period'] ?? 'month'),
                'output'  => $outputs,
                'periods' => $comp['periods'] ?? null,
            ];
        }

        $canonical = [
            'connection_id' => (int)$connectionId,
            'tables'        => $tables,
            'dimensions'    => $dimensions,
            'metrics'       => $metrics,
            'filters'       => $filters,
            'sorts'         => $sorts,
            'limit'         => $limit,
            'comparison'    => $comparison,
        ];

        if (!empty($domainContext)) {
            $canonical['domain_context'] = strtolower(trim($domainContext));
        }

        return $canonical;
    }

    /**
     * Compute the 64-hex-character SHA-256 hash of the canonical plan.
     */
    public function computeSignature(QueryPlan $plan, int $connectionId, ?string $domainContext = null): array
    {
        $canonical = $this->canonicalize($plan, $connectionId, $domainContext);
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $json);

        return [
            'hash'      => $hash,
            'canonical' => $canonical,
        ];
    }

    /**
     * Look up an existing, valid library entry for the given validated QueryPlan.
     */
    public function lookup(QueryPlan $plan, Connection $connection, ?string $domainContext = null): ?AiQueryLibrary
    {
        $sig = $this->computeSignature($plan, $connection->id, $domainContext);
        $entry = AiQueryLibrary::with('queryRepository')
            ->where('connection_id', $connection->id)
            ->where('signature_hash', $sig['hash'])
            ->first();

        if (!$entry || !$entry->isValid()) {
            return null;
        }

        // Authorization & Safety check: Ensure every table in canonical_plan_json.tables remains chatedp_enabled = true
        $tables = array_unique(array_map('strtolower', $entry->canonical_plan_json['tables'] ?? []));
        if (!empty($tables)) {
            $enabledCount = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')
                ->where('connection_id', $connection->id)
                ->where('chatedp_enabled', true)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(entity_key)'), $tables)
                ->count();

            if ($enabledCount < count($tables)) {
                return null; // Treat as LIBRARY MISS if any table is no longer chatedp_enabled
            }
        }

        return $entry;
    }

    /**
     * Promote/link an existing QueryRepository for a successfully validated and executed QueryPlan.
     */
    public function promote(QueryPlan $plan, Connection $connection, QueryRepository $repository, ?string $domainContext = null): ?AiQueryLibrary
    {
        if (!$repository->is_active) {
            return null;
        }

        $sig = $this->computeSignature($plan, $connection->id, $domainContext);

        return AiQueryLibrary::updateOrCreate(
            [
                'connection_id'  => $connection->id,
                'signature_hash' => $sig['hash'],
            ],
            [
                'canonical_plan_json' => $sig['canonical'],
                'query_repository_id' => $repository->id,
                'repository_version'  => (int)$repository->version,
                'is_active'           => true,
            ]
        );
    }
}
