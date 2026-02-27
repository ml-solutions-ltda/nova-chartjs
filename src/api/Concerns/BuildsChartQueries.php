<?php

namespace MlSolutions\ChartJsIntegration\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use MlSolutions\ChartJsIntegration\Api\ThrowError;

trait BuildsChartQueries
{
    private const BASIC_OPERATORS = [
        '=',
        '!=',
        '<>',
        '>',
        '>=',
        '<',
        '<=',
        'LIKE',
        'NOT LIKE',
        'ILIKE',
        'NOT ILIKE',
    ];

    private const NULL_OPERATORS = ['IS NULL', 'IS NOT NULL'];

    private const LIST_OPERATORS = ['IN', 'NOT IN'];

    private const RANGE_OPERATORS = ['BETWEEN', 'NOT BETWEEN'];

    private const JOIN_OPERATORS = ['=', '!=', '<>', '>', '>=', '<', '<='];

    private function normalizeArrayInput(mixed $input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($input)) {
            $input = json_decode(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
        }

        if (! is_array($input)) {
            return [];
        }

        $normalized = json_decode(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);

        return is_array($normalized) ? $normalized : [];
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    private function ensureValidModelClass(string $modelClass): void
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new ThrowError('Invalid model provided. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }
    }

    private function normalizeIdentifier(string $identifier, string $errorMessage): string
    {
        $identifier = trim($identifier);

        if ($identifier === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $identifier) !== 1) {
            throw new ThrowError($errorMessage.' <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }

        return $identifier;
    }

    private function normalizeCalculation(mixed $calculation): string
    {
        if (is_int($calculation) || is_float($calculation)) {
            return (string) $calculation;
        }

        if (is_string($calculation)) {
            $calculation = trim($calculation);

            if ($calculation === '') {
                throw new ThrowError('Sum option cannot be empty. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
            }

            if (is_numeric($calculation)) {
                return (string) (0 + $calculation);
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $calculation) === 1) {
                return $calculation;
            }
        }

        throw new ThrowError('Invalid sum option provided. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
    }

    private function buildCacheKey(string $scope, array $payload): string
    {
        $payload = $this->sortRecursively($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $json = serialize($payload);
        }

        return 'nova-chartjs:'.$scope.':'.hash('sha256', $json);
    }

    private function resolveExpiresAt(mixed $expires): ?Carbon
    {
        if ($expires === null || $expires === '' || $expires === false) {
            return null;
        }

        if ($expires === true) {
            return Carbon::now()->addMinute();
        }

        if (is_numeric($expires)) {
            $minutes = (int) $expires;

            if ($minutes <= 0) {
                return null;
            }

            return Carbon::now()->addMinutes($minutes);
        }

        if (is_string($expires) && in_array(strtolower(trim($expires)), ['false', 'null', 'off'], true)) {
            return null;
        }

        try {
            return Carbon::parse((string) $expires);
        } catch (\Throwable) {
            throw new ThrowError('Invalid expires option provided. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }
    }

    private function applyJoin(Builder $query, array $join): void
    {
        if ($join === []) {
            return;
        }

        $joinTable = $this->normalizeIdentifier((string) ($join['joinTable'] ?? ''), 'Invalid join table.');
        $joinColumnFirst = $this->normalizeIdentifier((string) ($join['joinColumnFirst'] ?? ''), 'Invalid join first column.');
        $joinColumnSecond = $this->normalizeIdentifier((string) ($join['joinColumnSecond'] ?? ''), 'Invalid join second column.');
        $joinOperator = $this->normalizeJoinOperator((string) ($join['joinEqual'] ?? '='));

        $query->join($joinTable, $joinColumnFirst, $joinOperator, $joinColumnSecond);
    }

    private function applyDateFilter(
        Builder $query,
        string $xAxisColumn,
        mixed $advanceFilterSelected,
        mixed $dataForLast,
        string $unitOfMeasurement = 'month'
    ): void {
        if (is_numeric($advanceFilterSelected)) {
            $query->where($xAxisColumn, '>=', Carbon::now()->subDays((int) $advanceFilterSelected));

            return;
        }

        if ($advanceFilterSelected === 'YTD') {
            $query->whereBetween($xAxisColumn, [Carbon::now()->firstOfYear()->startOfDay(), Carbon::now()]);

            return;
        }

        if ($advanceFilterSelected === 'QTD') {
            $query->whereBetween($xAxisColumn, [Carbon::now()->firstOfQuarter()->startOfDay(), Carbon::now()]);

            return;
        }

        if ($advanceFilterSelected === 'MTD') {
            $query->whereBetween($xAxisColumn, [Carbon::now()->firstOfMonth()->startOfDay(), Carbon::now()]);

            return;
        }

        if ($dataForLast === '*') {
            return;
        }

        $period = max(1, (int) $dataForLast);

        if ($unitOfMeasurement === 'day') {
            $query->where($xAxisColumn, '>=', Carbon::now()->subDays($period + 1));

            return;
        }

        if ($unitOfMeasurement === 'week') {
            $query->where($xAxisColumn, '>=', Carbon::now()->startOfWeek()->subWeeks($period));

            return;
        }

        if ($unitOfMeasurement === 'hour') {
            $query->where($xAxisColumn, '>=', Carbon::now()->startOfDay());

            return;
        }

        $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonths($period - 1));
    }

    private function applyQueryFilters(Builder $query, mixed $queryFilters): void
    {
        $filters = $this->normalizeArrayInput($queryFilters);

        foreach ($filters as $queryFilter) {
            if (! is_array($queryFilter) || ! isset($queryFilter['key'])) {
                continue;
            }

            $column = $this->normalizeIdentifier((string) $queryFilter['key'], 'Invalid query filter column.');
            $operator = $this->normalizeFilterOperator((string) ($queryFilter['operator'] ?? '='));
            $value = $queryFilter['value'] ?? null;

            if ($operator === 'IS NULL') {
                $query->whereNull($column);

                continue;
            }

            if ($operator === 'IS NOT NULL') {
                $query->whereNotNull($column);

                continue;
            }

            if ($operator === 'IN') {
                if (! is_array($value) || $value === []) {
                    throw new ThrowError('IN operator requires an array value.');
                }

                $query->whereIn($column, $value);

                continue;
            }

            if ($operator === 'NOT IN') {
                if (! is_array($value) || $value === []) {
                    throw new ThrowError('NOT IN operator requires an array value.');
                }

                $query->whereNotIn($column, $value);

                continue;
            }

            if ($operator === 'BETWEEN') {
                if (! is_array($value) || count($value) !== 2) {
                    throw new ThrowError('BETWEEN operator requires a two-value array.');
                }

                $range = array_values($value);
                $query->whereBetween($column, [$range[0], $range[1]]);

                continue;
            }

            if ($operator === 'NOT BETWEEN') {
                if (! is_array($value) || count($value) !== 2) {
                    throw new ThrowError('NOT BETWEEN operator requires a two-value array.');
                }

                $range = array_values($value);
                $query->whereNotBetween($column, [$range[0], $range[1]]);

                continue;
            }

            if (is_array($value)) {
                throw new ThrowError('Operator '.$operator.' does not accept array values.');
            }

            $query->where($column, $operator, $value);
        }
    }

    /**
     * @return array{0: string, 1: array<int, mixed>, 2: array<int, array<string, mixed>>}
     */
    private function buildSeriesSelects(array $series, string $calculation): array
    {
        if ($series === []) {
            return ['', [], []];
        }

        $seriesSqlParts = [];
        $seriesBindings = [];
        $seriesMeta = [];
        $seriesIndex = 0;

        foreach ($series as $seriesData) {
            if (! is_array($seriesData)) {
                throw new ThrowError('Series format is invalid. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
            }

            $label = trim((string) ($seriesData['label'] ?? 'Series '.($seriesIndex + 1)));
            $label = $label === '' ? 'Series '.($seriesIndex + 1) : $label;
            $filter = $this->normalizeArrayInput($seriesData['filter'] ?? []);

            if ($filter === []) {
                throw new ThrowError('Each series needs a filter definition. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
            }

            [$conditionSql, $conditionBindings] = $this->buildSeriesCondition($filter);
            $alias = 'series_'.$seriesIndex;
            $seriesSqlParts[] = "SUM(CASE WHEN {$conditionSql} THEN {$calculation} ELSE 0 END) AS {$alias}";
            $seriesBindings = [...$seriesBindings, ...$conditionBindings];
            $seriesMeta[] = [
                'alias' => $alias,
                'label' => $label,
                'backgroundColor' => $seriesData['backgroundColor'] ?? null,
                'borderColor' => $seriesData['borderColor'] ?? null,
                'fill' => $seriesData['fill'] ?? null,
            ];
            $seriesIndex++;
        }

        if ($seriesSqlParts === []) {
            return ['', [], []];
        }

        return [', '.implode(', ', $seriesSqlParts), $seriesBindings, $seriesMeta];
    }

    /**
     * @param array<int|string, mixed> $filter
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildSeriesCondition(array $filter): array
    {
        if (array_key_exists('key', $filter)) {
            return $this->buildSingleCondition($filter);
        }

        $conditions = array_is_list($filter) ? $filter : array_values($filter);
        $conditionSqlParts = [];
        $conditionBindings = [];

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                throw new ThrowError('Series filter format is invalid.');
            }

            [$sql, $bindings] = $this->buildSingleCondition($condition);
            $conditionSqlParts[] = "({$sql})";
            $conditionBindings = [...$conditionBindings, ...$bindings];
        }

        if ($conditionSqlParts === []) {
            throw new ThrowError('Series filter format is invalid.');
        }

        return [implode(' AND ', $conditionSqlParts), $conditionBindings];
    }

    /**
     * @param array<int|string, mixed> $condition
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildSingleCondition(array $condition): array
    {
        $column = $this->normalizeIdentifier((string) ($condition['key'] ?? ''), 'Invalid series filter column.');
        $operator = $this->normalizeFilterOperator((string) ($condition['operator'] ?? '='));
        $value = $condition['value'] ?? null;

        if (in_array($operator, self::NULL_OPERATORS, true)) {
            return ["{$column} {$operator}", []];
        }

        if (in_array($operator, self::LIST_OPERATORS, true)) {
            if (! is_array($value) || $value === []) {
                throw new ThrowError($operator.' operator requires an array value.');
            }

            $placeholders = implode(', ', array_fill(0, count($value), '?'));

            return ["{$column} {$operator} ({$placeholders})", array_values($value)];
        }

        if (in_array($operator, self::RANGE_OPERATORS, true)) {
            if (! is_array($value) || count($value) !== 2) {
                throw new ThrowError($operator.' operator requires a two-value array.');
            }

            $range = array_values($value);

            return ["{$column} {$operator} ? AND ?", [$range[0], $range[1]]];
        }

        if (is_array($value)) {
            throw new ThrowError('Operator '.$operator.' does not accept array values.');
        }

        return ["{$column} {$operator} ?", [$value]];
    }

    private function normalizeFilterOperator(string $operator): string
    {
        $normalized = strtoupper(trim($operator));
        $normalized = $normalized === '' ? '=' : $normalized;

        if (
            in_array($normalized, self::BASIC_OPERATORS, true) ||
            in_array($normalized, self::NULL_OPERATORS, true) ||
            in_array($normalized, self::LIST_OPERATORS, true) ||
            in_array($normalized, self::RANGE_OPERATORS, true)
        ) {
            return $normalized;
        }

        throw new ThrowError('Unsupported operator '.$operator.'. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
    }

    private function normalizeJoinOperator(string $operator): string
    {
        $normalized = strtoupper(trim($operator));
        $normalized = $normalized === '' ? '=' : $normalized;

        if (! in_array($normalized, self::JOIN_OPERATORS, true)) {
            throw new ThrowError('Unsupported join operator '.$operator.'. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }

        return $normalized;
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
