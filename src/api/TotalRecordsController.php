<?php

namespace MlSolutions\ChartJsIntegration\Api;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Http\Requests\NovaRequest;
use MlSolutions\ChartJsIntegration\Api\Concerns\BuildsChartQueries;

class TotalRecordsController extends Controller
{
    use BuildsChartQueries;
    use ValidatesRequests;

    private const UNIT_OF_MEASUREMENT = ['day', 'week', 'month', 'hour'];

    /**
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(NovaRequest $request)
    {
        if ($request->input('model')) {
            $request->merge(['model' => urldecode($request->input('model'))]);
        }

        $options = $this->normalizeArrayInput($request->input('options', []));
        $join = $this->normalizeArrayInput($request->input('join', []));
        $series = $this->normalizeArrayInput($request->input('series', []));

        $showTotal = $this->toBoolean($options['showTotal'] ?? true);
        $totalLabel = (string) ($options['totalLabel'] ?? 'Total');
        $chartType = (string) $request->input('type', 'bar');
        $advanceFilterSelected = $options['advanceFilterSelected'] ?? false;
        $dataForLast = $options['latestData'] ?? 3;
        $unitOfMeasurement = (string) ($options['uom'] ?? 'month');
        $startWeekRaw = $options['startWeek'] ?? 1;

        if (! is_numeric($startWeekRaw)) {
            throw new ThrowError('startWeek needs to be between 0 and 7. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }

        $startWeek = (int) $startWeekRaw;

        if (! in_array($unitOfMeasurement, self::UNIT_OF_MEASUREMENT, true)) {
            throw new ThrowError('UOM not defined correctly. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }

        if ($startWeek < 0 || $startWeek > 7) {
            throw new ThrowError('startWeek needs to be between 0 and 7. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }

        $calculation = $this->normalizeCalculation($options['sum'] ?? 1);
        $request->validate(['model' => ['bail', 'required', 'min:1', 'string']]);
        $model = (string) $request->input('model');
        $this->ensureValidModelClass($model);
        $modelInstance = new $model();
        $connectionName = $modelInstance->getConnection()->getDriverName();
        $tableName = $modelInstance->getConnection()->getTablePrefix().$modelInstance->getTable();
        $xAxisColumn = $this->normalizeIdentifier((string) ($request->input('col_xaxis') ?? $tableName.'.created_at'), 'Invalid x-axis column provided.');

        $cacheKey = $this->buildCacheKey('total-records', [
            'model' => $model,
            'type' => $chartType,
            'xAxisColumn' => $xAxisColumn,
            'options' => $options,
            'join' => $join,
            'series' => $series,
        ]);

        $expiresAt = $this->resolveExpiresAt($request->input('expires'));
        if ($expiresAt !== null) {
            $cachedDataSet = Cache::get($cacheKey);

            if (is_array($cachedDataSet) && isset($cachedDataSet['xAxis'], $cachedDataSet['yAxis'])) {
                return response()->json(['dataset' => $cachedDataSet]);
            }
        }

        [$seriesSql, $seriesBindings, $seriesMeta] = $this->buildSeriesSelects($series, $calculation);
        $timeSelect = $this->buildTimeSelect($unitOfMeasurement, $xAxisColumn, $connectionName, $startWeek);
        $query = $model::query()->selectRaw($timeSelect.', SUM('.$calculation.') AS counted'.$seriesSql, $seriesBindings);

        $this->applyJoin($query, $join);
        $this->applyDateFilter($query, $xAxisColumn, $advanceFilterSelected, $dataForLast, $unitOfMeasurement);
        $query->groupBy('catorder', 'cat')->orderBy('catorder', 'asc');
        $this->applyQueryFilters($query, $options['queryFilter'] ?? []);

        $dataSet = $query->get();
        $xAxis = collect($dataSet)->map(function ($item) use ($unitOfMeasurement) {
            $cat = (string) $item->cat;

            if ($unitOfMeasurement === 'week' && preg_match('/^(\d{4})(\d{2})$/', $cat, $matches) === 1) {
                return 'W'.$matches[2].' '.$matches[1];
            }

            return $cat;
        })->values()->all();

        $brandColor = config('nova.brand.colors.500') ?: '14,165,233';
        $defaultColor = [
            "rgba($brandColor, 1)",
            '#ffcc5c',
            '#91e8e1',
            '#ff6f69',
            '#88d8b0',
            '#b088d8',
            '#d8b088',
            '#88b0d8',
            '#6f69ff',
            '#7cb5ec',
            '#434348',
            '#90ed7d',
            '#8085e9',
            '#f7a35c',
            '#f15c80',
            '#e4d354',
            '#2b908f',
            '#f45b5b',
            '#91e8e1',
            '#E27D60',
            '#85DCB',
            '#E8A87C',
            '#C38D9E',
            '#41B3A3',
            '#67c4a7',
            '#992667',
            '#ff4040',
            '#ff7373',
            '#d2d2d2',
        ];

        $yAxis = [];
        if ($seriesMeta !== []) {
            foreach ($seriesMeta as $seriesKey => $seriesData) {
                $yAxis[$seriesKey]['label'] = $seriesData['label'];
                $yAxis[$seriesKey]['data'] = collect($dataSet)->map(
                    fn ($item) => $item->{$seriesData['alias']} ?? 0
                )->values()->all();

                $defaultSeriesColor = $defaultColor[$seriesKey] ?? '#d2d2d2';
                $fillValue = $seriesData['fill'];

                if ($fillValue !== null && ! $this->toBoolean($fillValue)) {
                    $yAxis[$seriesKey]['borderColor'] = $seriesData['backgroundColor'] ?? $defaultSeriesColor;
                    $yAxis[$seriesKey]['fill'] = false;
                } else {
                    $yAxis[$seriesKey]['backgroundColor'] = $seriesData['backgroundColor'] ?? $defaultSeriesColor;
                }
            }

            if ($showTotal) {
                $totalSeriesKey = count($yAxis);
                $yAxis[$totalSeriesKey] = $this->counted(
                    $dataSet,
                    $defaultColor[$totalSeriesKey] ?? '#111',
                    'line',
                    $totalLabel
                );
            }
        } else {
            $yAxis[0] = $this->counted($dataSet, $defaultColor[0], $chartType, $totalLabel);
        }

        $dataset = [
            'xAxis' => $xAxis,
            'yAxis' => $yAxis,
        ];

        if ($expiresAt !== null) {
            Cache::put($cacheKey, $dataset, $expiresAt);
        }

        return response()->json(['dataset' => $dataset]);
    }

    private function buildTimeSelect(
        string $unitOfMeasurement,
        string $xAxisColumn,
        string $connectionName,
        int $startWeek
    ): string {
        if ($unitOfMeasurement === 'day') {
            return 'DATE('.$xAxisColumn.') AS cat, DATE('.$xAxisColumn.') AS catorder';
        }

        if ($unitOfMeasurement === 'week') {
            if ($connectionName === 'pgsql') {
                return "to_char(DATE_TRUNC('week', ".$xAxisColumn."), 'IYYYIW') AS cat, to_char(DATE_TRUNC('week', ".$xAxisColumn."), 'IYYYIW') AS catorder";
            }

            return 'YEARWEEK('.$xAxisColumn.', '.$startWeek.') AS cat, YEARWEEK('.$xAxisColumn.', '.$startWeek.') AS catorder';
        }

        if ($unitOfMeasurement === 'hour') {
            if ($connectionName === 'pgsql') {
                return 'CAST(EXTRACT(HOUR FROM '.$xAxisColumn.') AS INTEGER) AS cat, CAST(EXTRACT(HOUR FROM '.$xAxisColumn.') AS INTEGER) AS catorder';
            }

            return 'HOUR('.$xAxisColumn.') AS cat, HOUR('.$xAxisColumn.') AS catorder';
        }

        if ($connectionName === 'pgsql') {
            return "to_char(".$xAxisColumn.", 'Mon YYYY') AS cat, to_char(".$xAxisColumn.", 'YYYY-MM') AS catorder";
        }

        return 'DATE_FORMAT('.$xAxisColumn.', "%b %Y") AS cat, DATE_FORMAT('.$xAxisColumn.', "%Y-%m") AS catorder';
    }

    private function counted(
        iterable $dataSet,
        string $bgColor = '#111',
        string $type = 'bar',
        string $label = 'Total'
    ): array {
        $yAxis = [
            'type' => $type,
            'label' => $label,
            'data' => collect($dataSet)->map(fn ($item) => $item->counted ?? 0)->values()->all(),
        ];

        if ($type === 'line') {
            $yAxis['fill'] = false;
            $yAxis['borderColor'] = $bgColor;
        } else {
            $yAxis['backgroundColor'] = $bgColor;
        }

        return $yAxis;
    }
}
