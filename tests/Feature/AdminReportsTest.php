<?php

namespace Tests\Feature;

use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\Child;
use App\Models\Closure;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Space;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/admin/reports/';

    private const REPORTS = ['revenue', 'occupancy', 'subscriptions', 'discounts'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        // Monday 2026-10-26 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));

        Sanctum::actingAs($this->staffWithPermissions(['reports.view']));
    }

    public function test_access_control_for_every_report(): void
    {
        $this->app['auth']->forgetGuards();

        foreach (self::REPORTS as $report) {
            $this->getJson($this->url($report))->assertUnauthorized();
        }

        Sanctum::actingAs($this->staffWithPermissions(['payments.view']));

        foreach (self::REPORTS as $report) {
            $this->getJson($this->url($report))->assertForbidden();
        }

        Sanctum::actingAs(User::factory()->create());

        foreach (self::REPORTS as $report) {
            $this->getJson($this->url($report))->assertForbidden();
        }
    }

    public function test_invalid_input_is_rejected_in_arabic_and_english(): void
    {
        foreach (self::REPORTS as $report) {
            $this->getJson(self::BASE.$report)
                ->assertUnprocessable()
                ->assertJsonPath('errors.date_from.0', __('admin_reports.validation.date_required', [], 'ar'))
                ->assertJsonPath('errors.date_to.0', __('admin_reports.validation.date_required', [], 'ar'));

            $this->getJson($this->url($report, ['date_from' => '26/10/2026']))
                ->assertUnprocessable()
                ->assertJsonPath('errors.date_from.0', __('admin_reports.validation.date_invalid', [], 'ar'));

            $this->getJson($this->url($report, ['date_from' => '2026-10-26', 'date_to' => '2026-10-25']))
                ->assertUnprocessable()
                ->assertJsonPath('errors.date_to.0', __('admin_reports.validation.date_to_after_from', [], 'ar'));

            $this->getJson($this->url($report, ['date_from' => '2025-10-01', 'date_to' => '2026-10-02']))
                ->assertUnprocessable()
                ->assertJsonPath('errors.date_to.0', __('admin_reports.validation.range_too_long', ['days' => 366], 'ar'));

            $this->getJson($this->url($report, ['format' => 'csv']))
                ->assertUnprocessable()
                ->assertJsonPath('errors.format.0', __('admin_reports.validation.format_invalid', [], 'ar'));
        }

        $english = $this->withHeader('Accept-Language', 'en');

        $response = $english->getJson($this->url('revenue', ['date_from' => '2025-10-01', 'date_to' => '2026-10-02', 'format' => 'csv', 'group_by' => 'year']))->assertUnprocessable();
        $this->assertSame('The date range cannot exceed 366 days.', $response->json('errors')['date_to'][0]);
        $this->assertSame('The format must be json or xlsx.', $response->json('errors')['format'][0]);
        $this->assertSame('The grouping must be day or month.', $response->json('errors')['group_by'][0]);

        $english->getJson($this->url('revenue', ['date_from' => '2026-10-26', 'date_to' => '2026-10-25']))
            ->assertUnprocessable();

        $english->getJson($this->url('occupancy', ['facility_id' => 'abc', 'sport_id' => 0]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.facility_id.0', 'The facility filter must be a whole number of at least 1.')
            ->assertJsonPath('errors.sport_id.0', 'The sport filter must be a whole number of at least 1.');

        $this->getJson($this->url('revenue', ['date_from' => '2025-10-27', 'date_to' => '2026-10-27']))->assertOk();
    }

    public function test_empty_range_returns_zeros(): void
    {
        $this->getJson($this->url('revenue'))
            ->assertOk()
            ->assertJsonPath('data.totals', ['payments_count' => 0, 'gross' => 0, 'refunded' => 0, 'net' => 0])
            ->assertJsonCount(7, 'data.by_period')
            ->assertJsonPath('data.by_period.0.period', '2026-10-20')
            ->assertJsonPath('data.by_period.0.net', 0)
            ->assertJsonPath('data.by_method.1.method', 'sham_cash')
            ->assertJsonPath('data.by_method.1.gross', 0)
            ->assertJsonPath('data.by_target.1.target', 'subscription')
            ->assertJsonPath('data.by_target.1.gross', 0);

        $this->getJson($this->url('occupancy'))
            ->assertOk()
            ->assertJsonPath('data.totals', ['booked_hours' => 0, 'available_hours' => 0, 'occupancy_percent' => 0])
            ->assertJsonPath('data.units', []);

        $this->getJson($this->url('subscriptions'))
            ->assertOk()
            ->assertJsonPath('data.totals', ['new_in_range' => 0, 'expired_in_range' => 0, 'active_now' => 0])
            ->assertJsonPath('data.by_status', ['pending_payment' => 0, 'active' => 0, 'expired' => 0, 'cancelled' => 0])
            ->assertJsonPath('data.groups', []);

        $this->getJson($this->url('discounts'))
            ->assertOk()
            ->assertJsonPath('data.totals', ['usage_count' => 0, 'bookings_count' => 0, 'subscriptions_count' => 0, 'total_discount' => 0])
            ->assertJsonPath('data.discounts', [])
            ->assertJsonPath('data.categories', []);
    }

    public function test_revenue_report_figures(): void
    {
        $this->seedPayments();

        $data = $this->getJson($this->url('revenue'))->assertOk()->json('data');

        $this->assertSame(['date_from' => '2026-10-20', 'date_to' => '2026-10-26', 'timezone' => 'Asia/Damascus', 'group_by' => 'day'], $data['range']);
        $this->assertSame(['payments_count' => 3, 'gross' => 230000, 'refunded' => 20000, 'net' => 210000], $data['totals']);

        $days = collect($data['by_period'])->keyBy('period');
        $this->assertSame(['period' => '2026-10-20', 'payments_count' => 1, 'gross' => 100000, 'refunded' => 0, 'net' => 100000], $days['2026-10-20']);
        $this->assertSame(['period' => '2026-10-22', 'payments_count' => 2, 'gross' => 130000, 'refunded' => 20000, 'net' => 110000], $days['2026-10-22']);
        $this->assertSame(0, $days['2026-10-21']['gross']);

        $methods = collect($data['by_method'])->keyBy('method');
        $this->assertSame([2, 180000, 0, 180000], [$methods['cash']['payments_count'], $methods['cash']['gross'], $methods['cash']['refunded'], $methods['cash']['net']]);
        $this->assertSame([1, 50000, 20000, 30000], [$methods['sham_cash']['payments_count'], $methods['sham_cash']['gross'], $methods['sham_cash']['refunded'], $methods['sham_cash']['net']]);

        $targets = collect($data['by_target'])->keyBy('target');
        $this->assertSame([2, 150000, 20000, 130000], [$targets['booking']['payments_count'], $targets['booking']['gross'], $targets['booking']['refunded'], $targets['booking']['net']]);
        $this->assertSame([1, 80000, 0, 80000], [$targets['subscription']['payments_count'], $targets['subscription']['gross'], $targets['subscription']['refunded'], $targets['subscription']['net']]);

        $monthly = $this->getJson($this->url('revenue', ['group_by' => 'month']))->assertOk();
        $monthly->assertJsonCount(1, 'data.by_period')
            ->assertJsonPath('data.by_period.0.period', '2026-10')
            ->assertJsonPath('data.by_period.0.net', 210000);
    }

    public function test_occupancy_report_figures(): void
    {
        [$facility, $unitOne, $unitTwo, $sport] = $this->seedOccupancy();

        $data = $this->getJson($this->url('occupancy', ['date_from' => '2026-10-26', 'date_to' => '2026-10-27']))->assertOk()->json('data');

        $this->assertEquals(['booked_hours' => 7.5, 'available_hours' => 50.0, 'occupancy_percent' => 15.0], $data['totals']);
        $this->assertCount(1, $data['facilities']);
        $this->assertSame($facility->id, $data['facilities'][0]['facility_id']);
        $this->assertSame($facility->name_ar, $data['facilities'][0]['name_ar']);
        $this->assertSame($facility->name_en, $data['facilities'][0]['name_en']);
        $this->assertEquals(15.0, $data['facilities'][0]['occupancy_percent']);

        $units = collect($data['units'])->keyBy('unit_id');
        $this->assertEquals([3.5, 24.0, 14.58], [$units[$unitOne->id]['booked_hours'], $units[$unitOne->id]['available_hours'], $units[$unitOne->id]['occupancy_percent']]);
        $this->assertEquals([4.0, 26.0, 15.38], [$units[$unitTwo->id]['booked_hours'], $units[$unitTwo->id]['available_hours'], $units[$unitTwo->id]['occupancy_percent']]);
        $this->assertSame($unitOne->name_ar, $units[$unitOne->id]['name_ar']);
        $this->assertSame($unitOne->name_en, $units[$unitOne->id]['name_en']);
        $this->assertSame($facility->name_en, $units[$unitOne->id]['facility_name_en']);

        $this->getJson($this->url('occupancy', ['date_from' => '2026-10-26', 'date_to' => '2026-10-27', 'sport_id' => $sport->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.units')
            ->assertJsonPath('data.units.0.unit_id', $unitOne->id)
            ->assertJsonPath('data.totals.booked_hours', 3.5);

        $this->getJson($this->url('occupancy', ['date_from' => '2026-10-26', 'date_to' => '2026-10-27', 'facility_id' => $facility->id + 100]))
            ->assertOk()
            ->assertJsonPath('data.units', [])
            ->assertJsonPath('data.totals.available_hours', 0);
    }

    public function test_subscriptions_report_figures(): void
    {
        $groupOne = AcademyGroup::factory()->create();
        $groupTwo = AcademyGroup::factory()->create();

        $this->subscription($groupOne, 'active', '2026-10-05 09:00:00', '2026-10-05', '2026-11-05');
        $this->subscription($groupOne, 'expired', '2026-09-20 09:00:00', '2026-09-10', '2026-10-10');
        $this->subscription($groupOne, 'pending_payment', '2026-10-10 09:00:00');
        $this->subscription($groupOne, 'cancelled', '2026-10-12 09:00:00');
        $this->subscription($groupTwo, 'active', '2026-10-15 09:00:00', '2026-09-20', '2026-10-20');

        $data = $this->getJson($this->url('subscriptions', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk()->json('data');

        $this->assertSame(['new_in_range' => 4, 'expired_in_range' => 1, 'active_now' => 1], $data['totals']);
        $this->assertSame(['pending_payment' => 1, 'active' => 2, 'expired' => 0, 'cancelled' => 1], $data['by_status']);
        $this->assertCount(2, $data['groups']);

        $groups = collect($data['groups'])->keyBy('group_id');
        $this->assertSame([3, 1, 1], [$groups[$groupOne->id]['new_in_range'], $groups[$groupOne->id]['expired_in_range'], $groups[$groupOne->id]['active_now']]);
        $this->assertSame(['pending_payment' => 1, 'active' => 1, 'expired' => 0, 'cancelled' => 1], $groups[$groupOne->id]['by_status']);
        $this->assertSame([1, 0, 0], [$groups[$groupTwo->id]['new_in_range'], $groups[$groupTwo->id]['expired_in_range'], $groups[$groupTwo->id]['active_now']]);
        $this->assertSame($groupOne->name_ar, $groups[$groupOne->id]['group_name_ar']);
        $this->assertSame($groupOne->academy->name_en, $groups[$groupOne->id]['academy_name_en']);
    }

    public function test_discounts_report_figures(): void
    {
        $discountOne = Discount::factory()->create();
        $discountTwo = Discount::factory()->create();
        $category = UserCategory::factory()->create();
        $memberOne = User::factory()->create();
        $memberTwo = User::factory()->create();
        $other = User::factory()->create();
        $category->users()->attach([$memberOne->id, $memberTwo->id]);

        $this->discountedBooking($memberOne, $discountOne, 10000, '2026-10-10 09:00:00');
        $this->discountedBooking($other, $discountOne, 5000, '2026-10-11 09:00:00');
        $this->discountedBooking($memberOne, $discountOne, 9000, '2026-10-12 09:00:00', 'cancelled');
        $this->discountedBooking($memberOne, $discountTwo, 3000, '2026-09-25 09:00:00');

        $enrollment = Enrollment::factory()->create(['child_id' => Child::factory()->create(['user_id' => $memberTwo->id])->id]);
        $subscription = Subscription::factory()->create(['enrollment_id' => $enrollment->id, 'status' => 'active', 'discount_id' => $discountTwo->id, 'discount_amount' => 7000]);
        $subscription->forceFill(['created_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC')])->save();

        $data = $this->getJson($this->url('discounts', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk()->json('data');

        $this->assertSame(['usage_count' => 3, 'bookings_count' => 2, 'subscriptions_count' => 1, 'total_discount' => 22000], $data['totals']);
        $this->assertCount(2, $data['discounts']);
        $this->assertSame($discountOne->id, $data['discounts'][0]['discount_id']);
        $this->assertSame([2, 2, 0, 15000], [$data['discounts'][0]['usage_count'], $data['discounts'][0]['bookings_count'], $data['discounts'][0]['subscriptions_count'], $data['discounts'][0]['total_discount']]);
        $this->assertSame($discountOne->name_ar, $data['discounts'][0]['name_ar']);
        $this->assertSame($discountOne->name_en, $data['discounts'][0]['name_en']);
        $this->assertSame($discountTwo->id, $data['discounts'][1]['discount_id']);
        $this->assertSame([1, 0, 1, 7000], [$data['discounts'][1]['usage_count'], $data['discounts'][1]['bookings_count'], $data['discounts'][1]['subscriptions_count'], $data['discounts'][1]['total_discount']]);

        $this->assertCount(1, $data['categories']);
        $this->assertSame($category->id, $data['categories'][0]['category_id']);
        $this->assertSame($category->name_en, $data['categories'][0]['name_en']);
        $this->assertSame([2, 1, 1, 17000], [$data['categories'][0]['usage_count'], $data['categories'][0]['bookings_count'], $data['categories'][0]['subscriptions_count'], $data['categories'][0]['total_discount']]);
    }

    public function test_every_report_can_be_downloaded_as_a_readable_spreadsheet(): void
    {
        $this->seedPayments();
        $this->seedOccupancy();

        foreach (self::REPORTS as $report) {
            $response = $this->get($this->url($report, ['format' => 'xlsx', 'date_from' => '2026-10-20', 'date_to' => '2026-10-27']));

            $response->assertOk();
            $this->assertStringStartsWith('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
            $this->assertStringContainsString($report.'-report-2026-10-20-2026-10-27.xlsx', $response->headers->get('Content-Disposition'));

            $content = $response->streamedContent();
            $this->assertStringStartsWith('PK', $content);
            $this->assertNotEmpty($this->readSheets($content));
        }
    }

    public function test_revenue_spreadsheet_rows_and_translated_headers(): void
    {
        $this->seedPayments();

        $sheets = $this->readSheets($this->get($this->url('revenue', ['format' => 'xlsx']))->streamedContent());

        $this->assertSame([__('admin_reports.sheets.summary', [], 'ar'), __('admin_reports.sheets.by_period', [], 'ar'), __('admin_reports.sheets.by_method', [], 'ar'), __('admin_reports.sheets.by_target', [], 'ar')], array_keys($sheets));

        $summary = $sheets[__('admin_reports.sheets.summary', [], 'ar')];
        $this->assertSame([__('admin_reports.columns.payments_count', [], 'ar'), __('admin_reports.columns.gross', [], 'ar'), __('admin_reports.columns.refunded', [], 'ar'), __('admin_reports.columns.net', [], 'ar')], $summary[0]);
        $this->assertEquals([3, 230000, 20000, 210000], $summary[1]);

        $byMethod = $sheets[__('admin_reports.sheets.by_method', [], 'ar')];
        $this->assertSame(__('admin_reports.columns.method', [], 'ar'), $byMethod[0][0]);
        $this->assertSame(__('admin_reports.values.cash', [], 'ar'), $byMethod[1][0]);
        $this->assertSame(__('admin_reports.values.sham_cash', [], 'ar'), $byMethod[2][0]);

        $english = $this->withHeader('Accept-Language', 'en');
        $englishSheets = $this->readSheets($english->get($this->url('revenue', ['format' => 'xlsx']))->streamedContent());

        $this->assertSame(['Summary', 'By period', 'By method', 'By target'], array_keys($englishSheets));
        $this->assertSame(['Payments', 'Revenue', 'Refunded', 'Net'], $englishSheets['Summary'][0]);
        $this->assertSame('Cash', $englishSheets['By method'][1][0]);
    }

    public function test_discounts_spreadsheet_includes_both_name_columns(): void
    {
        $discount = Discount::factory()->create();
        $this->discountedBooking(User::factory()->create(), $discount, 4000, '2026-10-22 09:00:00');

        $sheets = $this->readSheets($this->get($this->url('discounts', ['format' => 'xlsx']))->streamedContent());
        $rows = $sheets[__('admin_reports.sheets.discounts', [], 'ar')];

        $this->assertSame(__('admin_reports.columns.name_ar', [], 'ar'), $rows[0][0]);
        $this->assertSame(__('admin_reports.columns.name_en', [], 'ar'), $rows[0][1]);
        $this->assertSame($discount->name_ar, $rows[1][0]);
        $this->assertSame($discount->name_en, $rows[1][1]);
        $this->assertEquals([1, 1, 0, 4000], array_slice($rows[1], 2));
    }

    private function seedPayments(): void
    {
        $this->payment(['amount' => 100000, 'method' => 'cash'], '2026-10-20 10:00:00');

        $partial = $this->payment(['amount' => 50000, 'method' => 'sham_cash', 'status' => 'partially_refunded'], '2026-10-21 21:30:00');
        Refund::factory()->create(['payment_id' => $partial->id, 'amount' => 20000, 'method' => 'sham_cash']);

        $this->payment(['amount' => 80000, 'method' => 'cash'], '2026-10-22 08:00:00', true);

        $this->payment(['amount' => 999, 'status' => 'failed'], '2026-10-22 08:00:00');
        $this->payment(['amount' => 70000, 'status' => 'refunded'], '2026-10-22 08:00:00');
        $this->payment(['amount' => 12345], '2026-10-10 08:00:00');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(array $attributes, string $paidAtUtc, bool $forSubscription = false): Payment
    {
        $factory = $forSubscription ? Payment::factory()->forSubscription() : Payment::factory();

        return $factory->create($attributes + ['paid_at' => CarbonImmutable::parse($paidAtUtc, 'UTC')]);
    }

    /**
     * Two units in one facility open 08:00-22:00 local on Monday and Tuesday; see the test for the expected figures.
     *
     * @return array{0: Facility, 1: BookableUnit, 2: BookableUnit, 3: Sport}
     */
    private function seedOccupancy(): array
    {
        $facility = Facility::factory()->create();
        $sport = Sport::factory()->create();
        $space = Space::factory()->create(['facility_id' => $facility->id]);

        foreach ([1, 2] as $weekday) {
            OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => $weekday, 'open_time' => '08:00:00', 'close_time' => '22:00:00']);
        }

        $unitOne = BookableUnit::factory()->create(['facility_id' => $facility->id]);
        $unitOne->spaces()->attach($space->id);
        $unitOne->sports()->attach($sport->id);
        $unitTwo = BookableUnit::factory()->create(['facility_id' => $facility->id]);

        // Facility-wide closure Monday 08:00-10:00 local; closure of the first unit's space Tuesday 20:00-22:00 local.
        Closure::factory()->create(['facility_id' => $facility->id, 'starts_at' => '2026-10-26 05:00:00', 'ends_at' => '2026-10-26 07:00:00']);
        Closure::factory()->create(['space_id' => $space->id, 'starts_at' => '2026-10-27 17:00:00', 'ends_at' => '2026-10-27 19:00:00']);

        $this->booking($unitOne, '2026-10-26 09:00:00', '2026-10-26 11:00:00');
        $this->booking($unitOne, '2026-10-27 07:00:00', '2026-10-27 08:30:00', 'completed');
        $this->booking($unitOne, '2026-10-27 09:00:00', '2026-10-27 10:00:00', 'cancelled');
        $this->booking($unitOne, '2026-10-27 11:00:00', '2026-10-27 12:00:00', 'expired');
        $this->booking($unitTwo, '2026-10-26 10:00:00', '2026-10-26 14:00:00');

        return [$facility, $unitOne, $unitTwo, $sport];
    }

    private function booking(BookableUnit $unit, string $startsAtUtc, string $endsAtUtc, string $status = 'confirmed'): Booking
    {
        return Booking::factory()->create([
            'unit_id' => $unit->id,
            'starts_at' => CarbonImmutable::parse($startsAtUtc, 'UTC'),
            'ends_at' => CarbonImmutable::parse($endsAtUtc, 'UTC'),
            'status' => $status,
        ]);
    }

    private function subscription(AcademyGroup $group, string $status, string $createdAtUtc, ?string $periodStart = null, ?string $periodEnd = null): Subscription
    {
        $subscription = Subscription::factory()->create([
            'enrollment_id' => Enrollment::factory()->create(['group_id' => $group->id])->id,
            'status' => $status,
            'period_start' => $periodStart === null ? null : CarbonImmutable::parse($periodStart, 'UTC'),
            'period_end' => $periodEnd === null ? null : CarbonImmutable::parse($periodEnd.' 23:59:59', 'UTC'),
        ]);
        $subscription->forceFill(['created_at' => CarbonImmutable::parse($createdAtUtc, 'UTC')])->save();

        return $subscription;
    }

    private function discountedBooking(User $user, Discount $discount, int $amount, string $createdAtUtc, string $status = 'confirmed'): Booking
    {
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'discount_id' => $discount->id,
            'discount_amount' => $amount,
            'status' => $status,
        ]);
        $booking->forceFill(['created_at' => CarbonImmutable::parse($createdAtUtc, 'UTC')])->save();

        return $booking;
    }

    /**
     * Read an Excel file back with the openspout reader as sheet name => rows of cell values.
     *
     * @return array<string, list<list<mixed>>>
     */
    private function readSheets(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'report-test-');
        file_put_contents($path, $content);

        try {
            $reader = new Reader;
            $reader->open($path);
            $sheets = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }

                $sheets[$sheet->getName()] = $rows;
            }

            $reader->close();
        } finally {
            unlink($path);
        }

        return $sheets;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function url(string $report, array $query = []): string
    {
        return self::BASE.$report.'?'.http_build_query($query + ['date_from' => '2026-10-20', 'date_to' => '2026-10-26']);
    }

    /**
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]));
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }
}
