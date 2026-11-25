<?php

declare(strict_types=1);

namespace Tardis\Dashboard;

/**
 * The contract for a dashboard chart. Extend it, return the data, and add it
 * like any widget (a plugin's provideWidgets()). The panel draws it as an
 * accessible SVG in the theme's colours; no JavaScript chart library needed.
 *
 *     class SignupsChart extends ChartWidget
 *     {
 *         public function type(): string { return 'line'; }
 *         public function labels(): array { return ['Mon', 'Tue', 'Wed']; }
 *         public function datasets(): array { return [['label' => 'Sign-ups', 'data' => [3, 7, 4]]]; }
 *     }
 */
abstract class ChartWidget extends Widget
{
    public const TYPES = ['line', 'bar', 'doughnut'];

    public function __construct(string $title)
    {
        parent::__construct('tardis::widgets.chart', $title);

        $this->parameters = ['chart' => $this];
    }

    /** line, bar or doughnut */
    abstract public function type(): string;

    /**
     * One label per data point (per slice for a doughnut).
     *
     * @return list<string>
     */
    abstract public function labels(): array;

    /**
     * Each series: a label and one number per label. A doughnut uses the first.
     *
     * @return list<array{label: string, data: list<int|float>}>
     */
    abstract public function datasets(): array;

    /** Height of the drawing in pixels. */
    public function height(): int
    {
        return 220;
    }
}
