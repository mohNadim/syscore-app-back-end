<?php

namespace App\Http\Requests\Api\V1\Admin\Reports;

class RevenueReportRequest extends ReportRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return ['group_by' => ['bail', 'sometimes', 'in:day,month']];
    }

    /**
     * @return array<string, string>
     */
    protected function filterMessages(): array
    {
        return ['group_by.in' => __('admin_reports.validation.group_by_invalid')];
    }

    /**
     * @return array<string, string>
     */
    protected function filterAttributes(): array
    {
        return ['group_by' => __('admin_reports.attributes.group_by')];
    }
}
