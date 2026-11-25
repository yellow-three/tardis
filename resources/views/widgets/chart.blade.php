@php
    $chartSvg = \Tardis\Dashboard\ChartSvg::render($chart);
    $chartSets = $chart->type() === 'doughnut' ? [] : $chart->datasets();
@endphp

@if ($chartSvg === '')
    <p class="text-sm text-base-content/60">{{ __('tardis::dashboard.chart_no_data') }}</p>
@else
    <figure>
        {!! $chartSvg !!}
        @if (count($chartSets) > 1)
            <figcaption class="mt-2 flex flex-wrap gap-3 text-xs">
                @foreach ($chartSets as $index => $set)
                    <span class="flex items-center gap-1">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ \Tardis\Dashboard\ChartSvg::color($index) }}"></span>
                        {{ $set['label'] ?? '' }}
                    </span>
                @endforeach
            </figcaption>
        @endif
    </figure>
@endif
