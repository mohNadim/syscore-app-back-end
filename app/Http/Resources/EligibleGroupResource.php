<?php

namespace App\Http\Resources;

use App\Models\AcademyGroup;
use Illuminate\Http\Request;

/**
 * A group a given child may join; the controller sets `seats_left`, `expected_position`, `price` and `child_status` on the model.
 *
 * @mixin AcademyGroup
 */
class EligibleGroupResource extends AcademyGroupResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seatsLeft = (int) $this->getAttribute('seats_left');

        return [
            ...parent::toArray($request),
            'seats_left' => $seatsLeft,
            'is_full' => $seatsLeft <= 0,
            'expected_position' => $this->getAttribute('expected_position'),
            'price' => $this->getAttribute('price'),
            'child_status' => $this->getAttribute('child_status'),
        ];
    }
}
