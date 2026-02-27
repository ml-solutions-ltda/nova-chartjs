<?php

namespace MlSolutions\ChartJsIntegration;

use Illuminate\Support\Str;

class StackedChart extends AbstractChart
{
    /**
     * Get the component name for the element.
     */
    public function component()
    {
        return 'stacked-chart';
    }

    public function title(string $title): self
    {
        return $this->withMeta(['title' => $title, 'uriKey' => Str::slug($title)]);
    }
}
