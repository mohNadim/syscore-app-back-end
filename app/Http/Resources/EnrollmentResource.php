<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(DisplayTimezone::class);

        return [
            'id' => $this->id,
            'child_id' => $this->child_id,
            'child' => new ChildResource($this->whenLoaded('child')),
            'group_id' => $this->group_id,
            'group' => new AcademyGroupResource($this->whenLoaded('group')),
            'status' => $this->status,
            'enrolled_at' => $this->enrolled_at ? $timezone->utcToLocalIso($this->enrolled_at) : null,
            'ended_at' => $this->ended_at ? $timezone->utcToLocalIso($this->ended_at) : null,
            'ends_at_period_end' => $this->status === 'active' && $this->ended_at !== null && $this->ended_at->isFuture(),
            'latest_subscription' => new SubscriptionResource($this->whenLoaded('latestSubscription')),
            'created_by_staff_id' => $this->created_by_staff_id,
        ];
    }
}
