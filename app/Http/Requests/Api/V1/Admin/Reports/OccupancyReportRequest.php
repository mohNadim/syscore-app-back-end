<?php

namespace App\Http\Requests\Api\V1\Admin\Reports;

class OccupancyReportRequest extends ReportRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'facility_id' => ['bail', 'sometimes', 'integer', 'min:1'],
            'sport_id' => ['bail', 'sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filterMessages(): array
    {
        return [
            'facility_id.integer' => __('admin_reports.validation.facility_id_invalid'),
            'facility_id.min' => __('admin_reports.validation.facility_id_invalid'),
            'sport_id.integer' => __('admin_reports.validation.sport_id_invalid'),
            'sport_id.min' => __('admin_reports.validation.sport_id_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filterAttributes(): array
    {
        return [
            'facility_id' => __('admin_reports.attributes.facility_id'),
            'sport_id' => __('admin_reports.attributes.sport_id'),
        ];
    }
}
