<?php

namespace MlSolutions\ChartJsIntegration;

class LineChart extends AbstractChart
{
    /**
     * Get the component name for the element.
     */
    public function component()
    {
        return 'stripe-chart';
    }

    public function series(array $series): self
    {
        foreach ($series as $key => $data) {
            $series[$key]['fill'] = false;
        }

        return parent::series($series);
    }
}
