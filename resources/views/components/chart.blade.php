{{--
    A chart (see the "chart" Alpine component). The colour of the series is the text colour of the
    element, so pass a token class (e.g. "text-brand-600 dark:text-brand-400"). The label is the
    sentence assistive technology reads instead of the drawing: write the conclusion, not "chart".

    :series     ApexCharts series, e.g. [['name' => 'Epics', 'data' => [3, 5, 2]]]
    :labels     category of each point, shown on the axis and in the tooltip
    :label      what the chart says, for screen readers
    :height     in pixels
    :sparkline  a bare line with no axes, grid or tooltip
--}}
@props(['series', 'label', 'labels' => [], 'type' => 'area', 'height' => 160, 'sparkline' => false])

<div role="img" aria-label="{{ $label }}"
    x-data="chart(@js(['type' => $type, 'series' => $series, 'labels' => $labels, 'height' => $height, 'sparkline' => $sparkline]))"
    {{ $attributes->merge(['style' => 'min-height: '.$height.'px']) }}>
    <div x-ref="canvas" aria-hidden="true"></div>
</div>
