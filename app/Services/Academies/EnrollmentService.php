<?php

namespace App\Services\Academies;

use App\Models\AcademyGroup;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Models\WaitlistEntry;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnrollmentService
{
    public function __construct(
        protected PricingService $pricing,
        protected DisplayTimezone $timezone,
        protected AuditLogger $audit,
    ) {}

    /**
     * Seats still free in a group: capacity minus active enrollments minus seats currently offered from the waitlist.
     *
     * An active enrollment whose subscription is still unpaid holds its seat. Pre-counted `active_enrollments_count` and
     * `offered_waitlist_entries_count` attributes (from `withCount`) are used when present to avoid extra queries.
     */
    public function seatsLeft(AcademyGroup $group): int
    {
        $enrolled = $group->getAttribute('active_enrollments_count')
            ?? $group->enrollments()->where('status', 'active')->count();
        $offered = $group->getAttribute('offered_waitlist_entries_count')
            ?? $group->waitlistEntries()->where('status', 'offered')->count();

        return $group->capacity - (int) $enrolled - (int) $offered;
    }

    /**
     * Add the seat counters that `seatsLeft()` reads to a groups query.
     *
     * @param  Builder<AcademyGroup>  $query
     * @return Builder<AcademyGroup>
     */
    public function withSeatCounts(Builder $query): Builder
    {
        return $query->withCount([
            'enrollments as active_enrollments_count' => fn ($enrollments) => $enrollments->where('status', 'active'),
            'waitlistEntries as offered_waitlist_entries_count' => fn ($entries) => $entries->where('status', 'offered'),
        ]);
    }

    /**
     * The 1-based place of a waiting entry in its group's queue; null when the entry is not waiting.
     */
    public function waitlistPosition(WaitlistEntry $entry): ?int
    {
        if ($entry->status !== 'waiting') {
            return null;
        }

        return WaitlistEntry::query()
            ->where('group_id', $entry->group_id)
            ->where('status', 'waiting')
            ->where(fn ($query) => $query
                ->where('created_at', '<', $entry->created_at)
                ->orWhere(fn ($tie) => $tie->where('created_at', $entry->created_at)->where('id', '<', $entry->id)))
            ->count() + 1;
    }

    /**
     * Whether the child may join the group, as the reason code of the first failed rule or null when eligible.
     *
     * @return 'group_inactive'|'child_inactive'|'age'|'gender'|null
     */
    public function ineligibilityReason(Child $child, AcademyGroup $group): ?string
    {
        $group->loadMissing('academy');

        return match (true) {
            ! $group->is_active || ! $group->academy->is_active => 'group_inactive',
            ! $child->is_active => 'child_inactive',
            ! $this->ageFits($child, $group) => 'age',
            $group->gender !== 'mixed' && $group->gender !== $child->gender => 'gender',
            default => null,
        };
    }

    /**
     * Enroll a child in a group, or put it on the waitlist when the group is full.
     *
     * The group row is locked for the whole transaction so concurrent requests are counted one at a time.
     * A child holding an `offered` waitlist entry is enrolled regardless of the seat count.
     *
     * @throws ValidationException when the child or group is not eligible
     * @throws ConflictHttpException when the child is already enrolled or already waiting
     */
    public function enroll(Child $child, AcademyGroup $group, ?Staff $staff = null, bool $overrideCapacity = false): EnrollResult
    {
        return DB::transaction(function () use ($child, $group, $staff, $overrideCapacity): EnrollResult {
            $locked = AcademyGroup::query()->lockForUpdate()->findOrFail($group->id);

            $reason = $this->ineligibilityReason($child, $locked);

            if ($reason !== null) {
                throw ValidationException::withMessages([
                    'group_id' => [__('enrollments.not_eligible', ['reason' => __('enrollments.reasons.'.$reason)])],
                ]);
            }

            if ($child->enrollments()->where('group_id', $locked->id)->where('status', 'active')->exists()) {
                throw new ConflictHttpException(__('enrollments.already_enrolled'));
            }

            $entry = WaitlistEntry::query()->where('group_id', $locked->id)->where('child_id', $child->id)->first();
            $isOffered = $entry?->status === 'offered';

            if ($entry?->status === 'waiting' && ! $overrideCapacity) {
                throw new ConflictHttpException(__('enrollments.already_waitlisted'));
            }

            if (! $isOffered && ! $overrideCapacity && $this->seatsLeft($locked) <= 0) {
                return new EnrollResult(waitlistEntry: $this->joinWaitlist($child, $locked, $entry));
            }

            if ($entry !== null && in_array($entry->status, ['offered', 'waiting'], true)) {
                $entry->update(['status' => 'enrolled']);
            }

            return new EnrollResult(enrollment: $this->createEnrollment($child, $locked, $staff));
        });
    }

    /**
     * Put the child on the group's waitlist, reusing a finished entry because (group, child) is unique.
     */
    protected function joinWaitlist(Child $child, AcademyGroup $group, ?WaitlistEntry $existing): WaitlistEntry
    {
        if ($existing === null) {
            $entry = WaitlistEntry::create(['group_id' => $group->id, 'child_id' => $child->id, 'status' => 'waiting']);
        } else {
            $entry = $existing;
            $entry->forceFill(['status' => 'waiting', 'offered_at' => null, 'created_at' => now()])->save();
        }

        $this->audit->log('waitlist.joined', $entry, null, $entry->only(['group_id', 'child_id', 'status']));

        return $entry->refresh();
    }

    /**
     * Create the active enrollment with its first, unpaid subscription priced now.
     */
    protected function createEnrollment(Child $child, AcademyGroup $group, ?Staff $staff): Enrollment
    {
        $enrollment = Enrollment::create([
            'child_id' => $child->id,
            'group_id' => $group->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'created_by_staff_id' => $staff?->id,
        ]);

        $quote = $this->pricing->quoteSubscription($group, $child->user);

        $subscription = $enrollment->subscriptions()->create([
            'status' => 'pending_payment',
            'base_price' => $quote->basePrice,
            'discount_id' => $quote->discount?->id,
            'discount_amount' => $quote->discountAmount,
            'final_price' => $quote->finalPrice,
        ]);

        $this->audit->log('enrollment.created', $enrollment, null, [
            ...$enrollment->only(['child_id', 'group_id', 'status']),
            'subscription_id' => $subscription->id,
        ]);

        return $enrollment->refresh();
    }

    /**
     * Whether the child's full years at the local today fall inside the group's age range.
     */
    protected function ageFits(Child $child, AcademyGroup $group): bool
    {
        $age = $this->ageOf($child);

        return $age >= $group->age_min && $age <= $group->age_max;
    }

    /**
     * The child's age in full years at the local today.
     */
    public function ageOf(Child $child): int
    {
        $timezone = $this->timezone->name();

        return (int) floor(CarbonImmutable::parse($child->birth_date->toDateString(), $timezone)
            ->diffInYears(CarbonImmutable::now($timezone)->startOfDay(), true));
    }
}
