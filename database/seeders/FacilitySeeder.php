<?php

namespace Database\Seeders;

use App\Models\BookableUnit;
use App\Models\Facility;
use App\Models\Space;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Seed the pitch and hall facilities, their spaces and bookable units.
     */
    public function run(): void
    {
        $pitch = Facility::updateOrCreate(
            ['type' => 'pitch', 'name_en' => 'Pitch'],
            ['name_ar' => 'الملعب', 'sort_order' => 1, 'is_active' => true],
        );
        $hall = Facility::updateOrCreate(
            ['type' => 'hall', 'name_en' => 'Hall'],
            ['name_ar' => 'الصالة', 'sort_order' => 2, 'is_active' => true],
        );

        $sectionA = $this->space($pitch, 'A', 'الجزء A', 'Section A');
        $sectionB = $this->space($pitch, 'B', 'الجزء B', 'Section B');
        $hallSpace = $this->space($hall, 'HALL', 'الصالة', 'Hall');

        $this->unit($pitch, 'PITCH_FULL', 'ملعب كامل', 'Full pitch', 1, [$sectionA, $sectionB]);
        $this->unit($pitch, 'PITCH_A', 'نصف الملعب A', 'Half pitch A', 2, [$sectionA]);
        $this->unit($pitch, 'PITCH_B', 'نصف الملعب B', 'Half pitch B', 3, [$sectionB]);
        $this->unit($hall, 'HALL', 'الصالة', 'Hall', 4, [$hallSpace]);
    }

    /**
     * Create or update a space of a facility.
     */
    private function space(Facility $facility, string $code, string $nameAr, string $nameEn): Space
    {
        return Space::updateOrCreate(
            ['facility_id' => $facility->id, 'code' => $code],
            ['name_ar' => $nameAr, 'name_en' => $nameEn],
        );
    }

    /**
     * Create or update a bookable unit and sync the spaces it occupies.
     *
     * @param  list<Space>  $spaces
     */
    private function unit(Facility $facility, string $code, string $nameAr, string $nameEn, int $sortOrder, array $spaces): void
    {
        $unit = BookableUnit::updateOrCreate(
            ['code' => $code],
            [
                'facility_id' => $facility->id,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ],
        );

        $unit->spaces()->sync(array_map(fn (Space $space): int => $space->id, $spaces));
    }
}
