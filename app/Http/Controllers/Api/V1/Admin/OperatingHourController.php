<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Hours\UpdateOperatingHoursRequest;
use App\Http\Resources\OperatingHourResource;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OperatingHourController extends Controller
{
    /**
     * List the weekly operating hours of a facility ordered by weekday.
     */
    public function index(Facility $facility): AnonymousResourceCollection
    {
        return OperatingHourResource::collection($this->weekFor($facility));
    }

    /**
     * Replace the whole weekly schedule of a facility; a weekday that is not sent is closed.
     */
    public function update(UpdateOperatingHoursRequest $request, Facility $facility, AuditLogger $audit): JsonResponse
    {
        $before = $this->snapshot($facility);

        DB::transaction(function () use ($request, $facility): void {
            $facility->operatingHours()->delete();

            foreach ($request->validated('hours') as $day) {
                $facility->operatingHours()->create($day);
            }
        });

        $audit->log('operating_hours.updated', $facility, ['hours' => $before], ['hours' => $this->snapshot($facility)]);

        return response()->json([
            'message' => __('admin_hours.hours_updated'),
            'data' => OperatingHourResource::collection($this->weekFor($facility)),
        ]);
    }

    /**
     * Get the operating hours of a facility ordered by weekday.
     *
     * @return Collection<int, OperatingHour>
     */
    protected function weekFor(Facility $facility): Collection
    {
        return $facility->operatingHours()->orderBy('weekday')->get();
    }

    /**
     * Describe the weekly schedule as plain arrays for the audit log.
     *
     * @return list<array{weekday: int, open_time: string, close_time: string}>
     */
    protected function snapshot(Facility $facility): array
    {
        return $this->weekFor($facility)
            ->map(fn (OperatingHour $hour): array => [
                'weekday' => $hour->weekday,
                'open_time' => substr((string) $hour->open_time, 0, 5),
                'close_time' => substr((string) $hour->close_time, 0, 5),
            ])
            ->all();
    }
}
