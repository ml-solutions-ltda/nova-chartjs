<?php

namespace MlSolutions\ChartJsIntegration;

class AreaChart extends AbstractChart
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
            if (! isset($data['backgroundColor'])) {
                continue;
            }

            if (empty($data['borderColor'])) {
                $series[$key]['borderColor'] = $this->adjustBrightness($data['backgroundColor'], -40);
            }
        }

        return parent::series($series);
    }

    private function adjustBrightness(string $hex, int $steps): string
    {
        $steps = max(-255, min(255, $steps));
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2)
                .str_repeat(substr($hex, 1, 1), 2)
                .str_repeat(substr($hex, 2, 1), 2);
        }

        $colorParts = str_split($hex, 2);
        $result = '#';

        foreach ($colorParts as $color) {
            $decimal = hexdec($color);
            $decimal = max(0, min(255, $decimal + $steps));
            $result .= str_pad(dechex($decimal), 2, '0', STR_PAD_LEFT);
        }

        return $result;
    }
}
