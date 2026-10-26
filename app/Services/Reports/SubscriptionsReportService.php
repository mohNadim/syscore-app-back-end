<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SubscriptionsReportService
{
    public const STATUSES = ['pending_payment', 'active', 'expired', 'cancelled'];

    /**
     * Subscription counts overall and per academy group.
     *
     * `by_status` and `new_in_range` count subscriptions created in the range; `expired_in_range` counts expired
     * subscriptions whose period ended in the range; `active_now` counts active subscriptions whose period has not
     * ended yet, regardless of the range.
     *
     * @return array<string, mixed>
     */
    public function generate(ReportPeriod $period): array
    {
        $now = CarbonImmutable::now();
        $created = 's.created_at >= ? AND s.created_at < ?';
        $createdBindings = [$period->startUtc, $period->endExclusiveUtc];

        $selects = ["SUM(CASE WHEN {$created} THEN 1 ELSE 0 END) as new_in_range"];
        $bindings = $createdBindings;

        foreach (self::STATUSES as $status) {
            $selects[] = "SUM(CASE WHEN s.status = ? AND {$created} THEN 1 ELSE 0 END) as status_{$status}";
            array_push($bindings, $status, ...$createdBindings);
        }

        $selects[] = "SUM(CASE WHEN s.status = 'expired' AND s.period_end >= ? AND s.period_end < ? THEN 1 ELSE 0 END) as expired_in_range";
        array_push($bindings, ...$createdBindings);
        $selects[] = "SUM(CASE WHEN s.status = 'active' AND s.period_end >= ? THEN 1 ELSE 0 END) as active_now";
        $bindings[] = $now;

        $rows = DB::table('academy_groups as g')
            ->join('academies as a', 'a.id', '=', 'g.academy_id')
            ->leftJoin('enrollments as e', 'e.group_id', '=', 'g.id')
            ->leftJoin('subscriptions as s', 's.enrollment_id', '=', 'e.id')
            ->selectRaw('g.id as group_id, g.name_ar as group_name_ar, g.name_en as group_name_en, a.id as academy_id, a.name_ar as academy_name_ar, a.name_en as academy_name_en, '.implode(', ', $selects), $bindings)
            ->groupBy('g.id', 'g.name_ar', 'g.name_en', 'a.id', 'a.name_ar', 'a.name_en')
            ->orderBy('a.id')
            ->orderBy('g.id')
            ->get();

        $totals = ['new_in_range' => 0, 'expired_in_range' => 0, 'active_now' => 0];
        $byStatus = array_fill_keys(self::STATUSES, 0);
        $groups = [];

        foreach ($rows as $row) {
            $group = [
                'group_id' => (int) $row->group_id,
                'group_name_ar' => $row->group_name_ar,
                'group_name_en' => $row->group_name_en,
                'academy_id' => (int) $row->academy_id,
                'academy_name_ar' => $row->academy_name_ar,
                'academy_name_en' => $row->academy_name_en,
                'new_in_range' => (int) $row->new_in_range,
                'expired_in_range' => (int) $row->expired_in_range,
                'active_now' => (int) $row->active_now,
                'by_status' => [],
            ];

            foreach (self::STATUSES as $status) {
                $count = (int) $row->{'status_'.$status};
                $group['by_status'][$status] = $count;
                $byStatus[$status] += $count;
            }

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $group[$key];
            }

            $groups[] = $group;
        }

        return [
            'range' => $period->toArray(),
            'totals' => $totals,
            'by_status' => $byStatus,
            'groups' => $groups,
        ];
    }

    /**
     * The sheets of the Excel export.
     *
     * @param  array<string, mixed>  $report
     * @return list<array{name: string, columns: list<string>, rows: list<list<int|float|string|null>>}>
     */
    public function sheets(array $report): array
    {
        $statusColumns = ['new_in_range', 'expired_in_range', 'active_now'];

        return [
            ['name' => 'summary', 'columns' => $statusColumns, 'rows' => [array_map(fn (string $key) => $report['totals'][$key], $statusColumns)]],
            ['name' => 'by_status', 'columns' => ['status', 'count'], 'rows' => array_map(fn (string $status, int $count): array => [$status, $count], array_keys($report['by_status']), array_values($report['by_status']))],
            ['name' => 'groups', 'columns' => ['academy_name_ar', 'academy_name_en', 'group_name_ar', 'group_name_en', ...$statusColumns], 'rows' => array_map(fn (array $row): array => [$row['academy_name_ar'], $row['academy_name_en'], $row['group_name_ar'], $row['group_name_en'], ...array_map(fn (string $key) => $row[$key], $statusColumns)], $report['groups'])],
        ];
    }
}
