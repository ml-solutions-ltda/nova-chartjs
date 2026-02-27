<?php

namespace MlSolutions\ChartJsIntegration\Api;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Http\Requests\NovaRequest;
use MlSolutions\ChartJsIntegration\Api\Concerns\BuildsChartQueries;

class TotalCircleController extends Controller
{
    use BuildsChartQueries;
    use ValidatesRequests;

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

        if ($series === []) {
            throw new ThrowError('You need to have at least 1 series parameter for this type of chart. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }

        $advanceFilterSelected = $options['advanceFilterSelected'] ?? false;
        $dataForLast = $options['latestData'] ?? 3;
        $calculation = $this->normalizeCalculation($options['sum'] ?? 1);
        $request->validate(['model' => ['bail', 'required', 'min:1', 'string']]);
        $model = (string) $request->input('model');
        $this->ensureValidModelClass($model);
        $modelInstance = new $model();
        $tableName = $modelInstance->getConnection()->getTablePrefix().$modelInstance->getTable();
        $xAxisColumn = $this->normalizeIdentifier((string) ($request->input('col_xaxis') ?? $tableName.'.created_at'), 'Invalid x-axis column provided.');

        $cacheKey = $this->buildCacheKey('total-circle', [
            'model' => $model,
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
        $query = $model::query()->selectRaw('SUM('.$calculation.') AS counted'.$seriesSql, $seriesBindings);

        $this->applyJoin($query, $join);
        $this->applyDateFilter($query, $xAxisColumn, $advanceFilterSelected, $dataForLast);
        $this->applyQueryFilters($query, $options['queryFilter'] ?? []);

        $dataSet = $query->first();
        $defaultColor = [
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

        $xAxis = collect($seriesMeta)->pluck('label')->values()->all();
        $yAxis = [[
            'backgroundColor' => [],
            'borderColor' => [],
            'data' => [],
        ]];

        foreach ($seriesMeta as $seriesKey => $seriesData) {
            $yAxis[0]['backgroundColor'][$seriesKey] = $seriesData['backgroundColor'] ?? ($defaultColor[$seriesKey] ?? '#d2d2d2');
            $yAxis[0]['borderColor'][$seriesKey] = $seriesData['borderColor'] ?? '#FFF';
            $yAxis[0]['data'][$seriesKey] = $dataSet?->{$seriesData['alias']} ?? 0;
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
}
