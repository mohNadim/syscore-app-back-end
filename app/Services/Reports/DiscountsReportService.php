<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DiscountsReportService
{
    /**
     * Usage count and discount amount granted, per discount and per user category.
     *
     * Every booking (not cancelled or expired) and subscription (active or expired) created in the range that
     * applied a discount is one usage. A user in several categories is counted in each of them.
     *
     * @return array<string, mixed>
     */
    public function generate(ReportPeriod $period): array
    {
        $bookings = DB::table('bookings as b')
            ->whereNotNull('b.discount_id')
            ->whereNotIn('b.status', ['cancelled', 'expired'])
            ->where('b.created_at', '>=', $period->startUtc)
            ->where('b.created_at', '<', $period->endExclusiveUtc)
            ->selectRaw("b.discount_id, b.user_id, b.discount_amount, 'booking' as source");

        $subscriptions = DB::table('subscriptions as s')
            ->join('enrollments as e', 'e.id', '=', 's.enrollment_id')
            ->join('children as c', 'c.id', '=', 'e.child_id')
            ->whereNotNull('s.discount_id')
            ->whereIn('s.status', ['active', 'expired'])
            ->where('s.created_at', '>=', $period->startUtc)
            ->where('s.created_at', '<', $period->endExclusiveUtc)
            ->selectRaw("s.discount_id, c.user_id, s.discount_amount, 'subscription' as source");

        $usages = $bookings->unionAll($subscriptions);

        $discounts = $this->usageQuery($usages)
            ->join('discounts as d', 'd.id', '=', 'u.discount_id')
            ->selectRaw('d.id as discount_id, d.name_ar, d.name_en')
            ->groupBy('d.id', 'd.name_ar', 'd.name_en')
            ->orderByDesc('total_discount')
            ->orderBy('d.id')
            ->get()
            ->map(fn (object $row): array => $this->row($row, ['discount_id' => (int) $row->discount_id]))
            ->all();

        $categories = $this->usageQuery($usages)
            ->join('user_category_members as m', 'm.user_id', '=', 'u.user_id')
            ->join('user_categories as uc', 'uc.id', '=', 'm.category_id')
            ->selectRaw('uc.id as category_id, uc.name_ar, uc.name_en')
            ->groupBy('uc.id', 'uc.name_ar', 'uc.name_en')
            ->orderByDesc('total_discount')
            ->orderBy('uc.id')
            ->get()
            ->map(fn (object $row): array => $this->row($row, ['category_id' => (int) $row->category_id]))
            ->all();

        return [
            'range' => $period->toArray(),
            'totals' => [
                'usage_count' => array_sum(array_column($discounts, 'usage_count')),
                'bookings_count' => array_sum(array_column($discounts, 'bookings_count')),
                'subscriptions_count' => array_sum(array_column($discounts, 'subscriptions_count')),
                'total_discount' => array_sum(array_column($discounts, 'total_discount')),
            ],
            'discounts' => $discounts,
            'categories' => $categories,
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
        $figures = ['usage_count', 'bookings_count', 'subscriptions_count', 'total_discount'];
        $rows = fn (array $items): array => array_map(fn (array $row): array => [$row['name_ar'], $row['name_en'], ...array_map(fn (string $key) => $row[$key], $figures)], $items);

        return [
            ['name' => 'summary', 'columns' => $figures, 'rows' => [array_map(fn (string $key) => $report['totals'][$key], $figures)]],
            ['name' => 'discounts', 'columns' => ['name_ar', 'name_en', ...$figures], 'rows' => $rows($report['discounts'])],
            ['name' => 'categories', 'columns' => ['name_ar', 'name_en', ...$figures], 'rows' => $rows($report['categories'])],
        ];
    }

    /**
     * Start an aggregate query over the usages.
     */
    protected function usageQuery(Builder $usages): Builder
    {
        return DB::query()
            ->fromSub($usages, 'u')
            ->selectRaw("COUNT(*) as usage_count, SUM(CASE WHEN u.source = 'booking' THEN 1 ELSE 0 END) as bookings_count, SUM(CASE WHEN u.source = 'subscription' THEN 1 ELSE 0 END) as subscriptions_count, SUM(u.discount_amount) as total_discount");
    }

    /**
     * @param  array<string, int>  $keys
     * @return array<string, mixed>
     */
    protected function row(object $row, array $keys): array
    {
        return $keys + [
            'name_ar' => $row->name_ar,
            'name_en' => $row->name_en,
            'usage_count' => (int) $row->usage_count,
            'bookings_count' => (int) $row->bookings_count,
            'subscriptions_count' => (int) $row->subscriptions_count,
            'total_discount' => (int) $row->total_discount,
        ];
    }
}
