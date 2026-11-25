<?php

declare(strict_types=1);

namespace Tardis\Dashboard;

use Illuminate\Support\Facades\DB;
use Tardis\Models\ActivityLog;

/**
 * Built-in example of the chart contract: activity log entries per day over
 * the last two weeks.
 */
class ActivityChart extends ChartWidget
{
    protected const DAYS = 14;

    /** @var array<string, int>|null */
    private ?array $counts = null;

    public function type(): string
    {
        return 'bar';
    }

    public function labels(): array
    {
        return array_map(fn (string $day) => now()->parse($day)->isoFormat('D MMM'), array_keys($this->counts()));
    }

    public function datasets(): array
    {
        return [['label' => __('tardis::dashboard.activities'), 'data' => array_values($this->counts())]];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        if ($this->counts !== null) {
            return $this->counts;
        }

        $days = [];

        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $days[now()->subDays($i)->toDateString()] = 0;
        }

        try {
            DB::table(ActivityLog::TABLE)
                ->where('created_at', '>=', now()->subDays(self::DAYS - 1)->startOfDay())
                ->get(['created_at'])
                ->each(function ($row) use (&$days) {
                    $day = substr((string) $row->created_at, 0, 10);

                    if (isset($days[$day])) {
                        $days[$day]++;
                    }
                });
        } catch (\Throwable) {
            // No table yet: an empty chart.
        }

        return $this->counts = $days;
    }
}
