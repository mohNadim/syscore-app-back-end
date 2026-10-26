<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    public const METHODS = ['cash', 'sham_cash'];

    public const TARGETS = ['booking', 'subscription'];

    /**
     * Revenue of succeeded and partially refunded payments in the range, minus their refunds.
     *
     * Payments are placed in the range by `paid_at`; a payment's refunds count against the day it was paid.
     *
     * @return array<string, mixed>
     */
    public function generate(ReportPeriod $period, string $groupBy = 'day'): array
    {
        $format = $groupBy === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $periods = $groupBy === 'month'
            ? array_values(array_unique(array_map(fn (string $day): string => substr($day, 0, 7), $period->days())))
            : $period->days();

        $byPeriod = $this->aggregate($period, "DATE_FORMAT(DATE_ADD(p.paid_at, INTERVAL {$period->offsetSeconds()} SECOND), '{$format}')");
        $byMethod = $this->aggregate($period, 'p.method');
        $byTarget = $this->aggregate($period, "CASE WHEN p.booking_id IS NOT NULL THEN 'booking' ELSE 'subscription' END");

        $totals = ['payments_count' => 0, 'gross' => 0, 'refunded' => 0, 'net' => 0];

        foreach ($byMethod as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $row[$key];
            }
        }

        return [
            'range' => $period->toArray() + ['group_by' => $groupBy],
            'totals' => $totals,
            'by_period' => array_map(fn (string $bucket): array => ['period' => $bucket] + ($byPeriod[$bucket] ?? $this->zero()), $periods),
            'by_method' => array_map(fn (string $bucket): array => ['method' => $bucket] + ($byMethod[$bucket] ?? $this->zero()), self::METHODS),
            'by_target' => array_map(fn (string $bucket): array => ['target' => $bucket] + ($byTarget[$bucket] ?? $this->zero()), self::TARGETS),
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
        $columns = ['payments_count', 'gross', 'refunded', 'net'];
        $values = fn (array $row): array => array_map(fn (string $column): int => $row[$column], $columns);

        return [
            ['name' => 'summary', 'columns' => $columns, 'rows' => [$values($report['totals'])]],
            ['name' => 'by_period', 'columns' => ['period', ...$columns], 'rows' => array_map(fn (array $row): array => [$row['period'], ...$values($row)], $report['by_period'])],
            ['name' => 'by_method', 'columns' => ['method', ...$columns], 'rows' => array_map(fn (array $row): array => [$row['method'], ...$values($row)], $report['by_method'])],
            ['name' => 'by_target', 'columns' => ['target', ...$columns], 'rows' => array_map(fn (array $row): array => [$row['target'], ...$values($row)], $report['by_target'])],
        ];
    }

    /**
     * @return array{payments_count: int, gross: int, refunded: int, net: int}
     */
    protected function zero(): array
    {
        return ['payments_count' => 0, 'gross' => 0, 'refunded' => 0, 'net' => 0];
    }

    /**
     * Aggregate the payments of the range by a SQL expression.
     *
     * @return array<string, array{payments_count: int, gross: int, refunded: int, net: int}>
     */
    protected function aggregate(ReportPeriod $period, string $bucketExpression): array
    {
        $refunds = DB::table('refunds')->select('payment_id', DB::raw('SUM(amount) as refunded_amount'))->groupBy('payment_id');

        return DB::table('payments as p')
            ->leftJoinSub($refunds, 'r', 'r.payment_id', '=', 'p.id')
            ->whereIn('p.status', ['succeeded', 'partially_refunded'])
            ->where('p.paid_at', '>=', $period->startUtc)
            ->where('p.paid_at', '<', $period->endExclusiveUtc)
            ->selectRaw("{$bucketExpression} as bucket, COUNT(*) as payments_count, SUM(p.amount) as gross, SUM(COALESCE(r.refunded_amount, 0)) as refunded")
            ->groupBy('bucket')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->bucket => [
                'payments_count' => (int) $row->payments_count,
                'gross' => (int) $row->gross,
                'refunded' => (int) $row->refunded,
                'net' => (int) $row->gross - (int) $row->refunded,
            ]])
            ->all();
    }
}
