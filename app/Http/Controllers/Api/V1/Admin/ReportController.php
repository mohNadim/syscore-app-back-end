<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Reports\DiscountsReportRequest;
use App\Http\Requests\Api\V1\Admin\Reports\OccupancyReportRequest;
use App\Http\Requests\Api\V1\Admin\Reports\ReportRequest;
use App\Http\Requests\Api\V1\Admin\Reports\RevenueReportRequest;
use App\Http\Requests\Api\V1\Admin\Reports\SubscriptionsReportRequest;
use App\Services\DisplayTimezone;
use App\Services\Reports\DiscountsReportService;
use App\Services\Reports\OccupancyReportService;
use App\Services\Reports\ReportSpreadsheet;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\SubscriptionsReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected DisplayTimezone $timezone, protected ReportSpreadsheet $spreadsheet) {}

    /**
     * Revenue of succeeded and partially refunded payments minus refunds, by period, method and target.
     */
    public function revenue(RevenueReportRequest $request, RevenueReportService $service): JsonResponse|StreamedResponse
    {
        $report = $service->generate($request->period($this->timezone), $request->string('group_by', 'day')->toString());

        return $this->respond($request, 'revenue', $report, $service->sheets($report));
    }

    /**
     * Booked hours against available hours per facility and bookable unit.
     */
    public function occupancy(OccupancyReportRequest $request, OccupancyReportService $service): JsonResponse|StreamedResponse
    {
        $report = $service->generate(
            $request->period($this->timezone),
            $request->filled('facility_id') ? $request->integer('facility_id') : null,
            $request->filled('sport_id') ? $request->integer('sport_id') : null,
        );

        return $this->respond($request, 'occupancy', $report, $service->sheets($report));
    }

    /**
     * Subscription counts by status and per academy group.
     */
    public function subscriptions(SubscriptionsReportRequest $request, SubscriptionsReportService $service): JsonResponse|StreamedResponse
    {
        $report = $service->generate($request->period($this->timezone));

        return $this->respond($request, 'subscriptions', $report, $service->sheets($report));
    }

    /**
     * Discount usage and granted amounts per discount and per user category.
     */
    public function discounts(DiscountsReportRequest $request, DiscountsReportService $service): JsonResponse|StreamedResponse
    {
        $report = $service->generate($request->period($this->timezone));

        return $this->respond($request, 'discounts', $report, $service->sheets($report));
    }

    /**
     * Return the report as JSON, or as an Excel download when `format=xlsx`.
     *
     * @param  array<string, mixed>  $report
     * @param  list<array{name: string, columns: list<string>, rows: list<list<int|float|string|null>>}>  $sheets
     */
    protected function respond(ReportRequest $request, string $name, array $report, array $sheets): JsonResponse|StreamedResponse
    {
        if ($request->wantsSpreadsheet()) {
            return $this->spreadsheet->download(sprintf('%s-report-%s-%s.xlsx', $name, $report['range']['date_from'], $report['range']['date_to']), $sheets);
        }

        return response()->json(['data' => $report]);
    }
}
