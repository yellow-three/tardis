<?php

declare(strict_types=1);

namespace Tardis\Dashboard;

/**
 * Draws a ChartWidget as SVG on the server. Every label is escaped; colours
 * come from the theme (DaisyUI variables), so charts follow light and dark.
 */
final class ChartSvg
{
    private const COLORS = ['var(--color-primary)', 'var(--color-secondary)', 'var(--color-accent)', 'var(--color-info)', 'var(--color-success)', 'var(--color-warning)', 'var(--color-error)'];

    private const WIDTH = 600;

    public static function color(int $index): string
    {
        return self::COLORS[$index % count(self::COLORS)];
    }

    public static function render(ChartWidget $chart): string
    {
        $type = in_array($chart->type(), ChartWidget::TYPES, true) ? $chart->type() : 'bar';
        $labels = array_map('strval', array_values($chart->labels()));
        $datasets = array_values(array_filter($chart->datasets(), fn ($set) => is_array($set) && is_array($set['data'] ?? null)));
        $height = max(120, $chart->height());

        if ($labels === [] || $datasets === []) {
            return '';
        }

        $body = $type === 'doughnut'
            ? self::doughnut($labels, $datasets[0], $height)
            : self::axes($type, $labels, $datasets, $height);

        return sprintf('<svg viewBox="0 0 %d %d" class="w-full h-auto" role="img" aria-label="%s" xmlns="http://www.w3.org/2000/svg">%s</svg>',
            self::WIDTH, $height, e($chart->title), $body);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, data: list<int|float>}>  $datasets
     */
    private static function axes(string $type, array $labels, array $datasets, int $height): string
    {
        $left = 40;
        $bottom = $height - 24;
        $top = 8;
        $plotWidth = self::WIDTH - $left - 8;
        $max = 0.0;

        foreach ($datasets as $set) {
            foreach ($set['data'] as $value) {
                $max = max($max, (float) $value);
            }
        }

        $max = $max <= 0 ? 1.0 : $max;
        $count = count($labels);
        $step = $plotWidth / max(1, $count);
        $y = fn (float $value) => $bottom - ($bottom - $top) * max(0.0, $value) / $max;
        $svg = '';

        foreach ([0, 0.5, 1] as $fraction) {
            $lineY = round($y($max * $fraction), 1);
            $svg .= sprintf('<line x1="%d" y1="%s" x2="%d" y2="%s" stroke="currentColor" stroke-opacity="0.12"/>', $left, $lineY, self::WIDTH - 8, $lineY);
            $svg .= sprintf('<text x="%d" y="%s" font-size="11" text-anchor="end" fill="currentColor" fill-opacity="0.6">%s</text>', $left - 6, $lineY + 4, e(self::number($max * $fraction)));
        }

        foreach ($labels as $index => $label) {
            $svg .= sprintf('<text x="%s" y="%d" font-size="11" text-anchor="middle" fill="currentColor" fill-opacity="0.6">%s</text>', round($left + $step * ($index + 0.5), 1), $height - 6, e(mb_strimwidth($label, 0, 14, '…')));
        }

        $series = count($datasets);

        foreach ($datasets as $setIndex => $set) {
            $color = self::color($setIndex);
            $points = [];

            foreach (array_slice(array_values($set['data']), 0, $count) as $index => $value) {
                $value = (float) $value;
                $title = '<title>'.e(($set['label'] ?? '').': '.$labels[$index].' = '.self::number($value)).'</title>';

                if ($type === 'bar') {
                    $barWidth = $step * 0.7 / $series;
                    $x = $left + $step * $index + $step * 0.15 + $barWidth * $setIndex;
                    $svg .= sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="2" fill="%s">%s</rect>', round($x, 1), round($y($value), 1), round($barWidth, 1), round($bottom - $y($value), 1), $color, $title);
                } else {
                    $points[] = [round($left + $step * ($index + 0.5), 1), round($y($value), 1), $title];
                }
            }

            if ($points !== []) {
                $svg .= sprintf('<polyline fill="none" stroke="%s" stroke-width="2.5" points="%s"/>', $color, implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $points)));

                foreach ($points as [$px, $py, $title]) {
                    $svg .= sprintf('<circle cx="%s" cy="%s" r="3.5" fill="%s">%s</circle>', $px, $py, $color, $title);
                }
            }
        }

        return $svg;
    }

    /**
     * @param  list<string>  $labels
     * @param  array{label: string, data: list<int|float>}  $set
     */
    private static function doughnut(array $labels, array $set, int $height): string
    {
        $values = array_map(fn ($value) => max(0.0, (float) $value), array_slice(array_values($set['data']), 0, count($labels)));
        $total = array_sum($values);

        if ($total <= 0) {
            return '';
        }

        $radius = ($height - 20) / 2;
        $cx = $radius + 10;
        $cy = $height / 2;
        $circumference = 2 * M_PI * $radius * 0.8;
        $offset = 0.0;
        $svg = '';

        foreach ($values as $index => $value) {
            $length = $circumference * $value / $total;
            $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="%s" stroke-width="%s" stroke-dasharray="%s %s" stroke-dashoffset="%s" transform="rotate(-90 %s %s)"><title>%s</title></circle>',
                round($cx, 1), round($cy, 1), round($radius * 0.8, 1), self::color($index), round($radius * 0.4, 1),
                round($length, 2), round($circumference - $length, 2), round(-$offset, 2), round($cx, 1), round($cy, 1),
                e($labels[$index].': '.self::number($value)));
            $offset += $length;
            $svg .= sprintf('<rect x="%s" y="%s" width="10" height="10" rx="2" fill="%s"/><text x="%s" y="%s" font-size="12" fill="currentColor">%s</text>',
                round($cx * 2 + 20, 1), 14 + $index * 18, self::color($index), round($cx * 2 + 36, 1), 23 + $index * 18,
                e(mb_strimwidth($labels[$index], 0, 28, '…').' ('.self::number($value).')'));
        }

        return $svg;
    }

    private static function number(float $value): string
    {
        return $value == floor($value) ? number_format($value, 0) : number_format($value, 1);
    }
}
