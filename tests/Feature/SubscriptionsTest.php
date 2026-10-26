<?php

namespace Tests\Feature;

use App\Models\AcademyGroup;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        $this->user = Sanctum::actingAs(User::factory()->create());
        $group = AcademyGroup::factory()->create(['monthly_price' => 100000]);
        $this->enrollment = Enrollment::factory()->create([
            'child_id' => Child::factory()->for($this->user)->create()->id,
            'group_id' => $group->id,
        ]);
    }

    private function subscription(string $state = 'active', ?Enrollment $enrollment = null): Subscription
    {
        return Subscription::factory()->{$state}()->create(['enrollment_id' => ($enrollment ?? $this->enrollment)->id, 'base_price' => 100000, 'final_price' => 100000]);
    }

    private function pending(?Enrollment $enrollment = null): Subscription
    {
        return Subscription::factory()->create(['enrollment_id' => ($enrollment ?? $this->enrollment)->id]);
    }

    public function test_index_lists_own_subscriptions_newest_first_with_filters(): void
    {
        $old = $this->subscription();
        $this->travel(1)->minute();
        $new = $this->pending();
        Subscription::factory()->create();

        $this->getJson('/api/v1/subscriptions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonPath('data.1.enrollment.child.id', $this->enrollment->child_id)
            ->assertJsonPath('data.1.enrollment.group.id', $this->enrollment->group_id);

        $this->getJson('/api/v1/subscriptions?status=active')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
        $this->getJson("/api/v1/subscriptions?child_id={$this->enrollment->child_id}&per_page=1")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/subscriptions?child_id=999999')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/subscriptions?status=x&per_page=0')->assertUnprocessable()
            ->assertJsonPath('errors.status.0', __('subscriptions.validation.status_invalid', [], 'ar'));
    }

    public function test_show_returns_own_subscription_and_404_for_others(): void
    {
        $own = $this->subscription();
        $foreign = Subscription::factory()->create();

        $this->getJson("/api/v1/subscriptions/{$own->id}")->assertOk()->assertJsonPath('data.id', $own->id)->assertJsonPath('data.status', 'active');
        $this->getJson("/api/v1/subscriptions/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/subscriptions/{$foreign->id}/renew")->assertNotFound();
        $this->postJson("/api/v1/subscriptions/{$foreign->id}/cancel")->assertNotFound();
        $this->getJson('/api/v1/subscriptions/999999')->assertNotFound();
    }

    public function test_renew_creates_an_unpaid_subscription_priced_now(): void
    {
        $active = $this->subscription();
        $category = UserCategory::factory()->create();
        $this->user->categories()->attach($category);
        Discount::factory()->create(['type' => 'fixed', 'value' => 10000, 'target' => 'subscription'])->categories()->attach($category);

        $this->postJson("/api/v1/subscriptions/{$active->id}/renew")
            ->assertCreated()
            ->assertJsonPath('message', __('subscriptions.renewal_requested', [], 'ar'))
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.renewed_from_id', $active->id)
            ->assertJsonPath('data.final_price', 90000)
            ->assertJsonPath('data.discount_amount', 10000);

        $renewal = Subscription::query()->where('renewed_from_id', $active->id)->sole();
        $this->assertTrue(AuditLog::query()->where('action', 'subscription.renewal_requested')->where('entity_id', $renewal->id)->exists());
    }

    public function test_expired_subscription_can_be_renewed(): void
    {
        $expired = Subscription::factory()->create(['enrollment_id' => $this->enrollment->id, 'status' => 'expired']);

        $this->postJson("/api/v1/subscriptions/{$expired->id}/renew")->assertCreated();
    }

    public function test_renew_is_rejected_when_one_is_already_pending(): void
    {
        $active = $this->subscription();
        $this->pending();

        $this->postJson("/api/v1/subscriptions/{$active->id}/renew")
            ->assertConflict()
            ->assertJsonPath('message', __('subscriptions.renewal_pending', [], 'ar'));
    }

    public function test_renew_is_rejected_when_the_end_is_scheduled(): void
    {
        $active = $this->subscription();
        $this->enrollment->update(['ended_at' => now()->addDays(10)]);

        $this->postJson("/api/v1/subscriptions/{$active->id}/renew")
            ->assertConflict()
            ->assertJsonPath('message', __('subscriptions.cancellation_scheduled_conflict', [], 'ar'));
    }

    public function test_only_the_latest_active_or_expired_subscription_is_renewable(): void
    {
        $older = Subscription::factory()->create(['enrollment_id' => $this->enrollment->id, 'status' => 'expired']);
        $this->subscription();
        $this->postJson("/api/v1/subscriptions/{$older->id}/renew")->assertUnprocessable()
            ->assertJsonPath('message', __('subscriptions.not_renewable', [], 'ar'));

        $other = Enrollment::factory()->create(['child_id' => $this->enrollment->child_id]);
        $cancelled = Subscription::factory()->create(['enrollment_id' => $other->id, 'status' => 'cancelled']);
        $this->postJson("/api/v1/subscriptions/{$cancelled->id}/renew")->assertUnprocessable();

        $ended = Enrollment::factory()->ended()->create(['child_id' => $this->enrollment->child_id]);
        $this->postJson('/api/v1/subscriptions/'.$this->subscription('active', $ended)->id.'/renew')->assertUnprocessable();
    }

    public function test_cancelling_a_never_paid_first_subscription_cancels_the_enrollment(): void
    {
        $pending = $this->pending();

        $this->postJson("/api/v1/subscriptions/{$pending->id}/cancel")
            ->assertOk()
            ->assertJsonPath('message', __('subscriptions.cancelled', [], 'ar'))
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $this->enrollment->fresh()->status);
        $this->assertNotNull($this->enrollment->fresh()->ended_at);
        $this->assertTrue(AuditLog::query()->where('action', 'subscription.cancelled')->where('entity_id', $pending->id)->exists());
    }

    public function test_cancelling_a_pending_renewal_keeps_the_enrollment_when_a_paid_month_is_active(): void
    {
        $this->subscription();
        $renewal = $this->pending();

        $this->postJson("/api/v1/subscriptions/{$renewal->id}/cancel")->assertOk();

        $this->assertSame('cancelled', $renewal->fresh()->status);
        $this->assertSame('active', $this->enrollment->fresh()->status);
        $this->assertNull($this->enrollment->fresh()->ended_at);
    }

    public function test_cancelling_an_active_subscription_schedules_the_end_and_keeps_it_valid(): void
    {
        $active = $this->subscription();
        $renewal = $this->pending();

        $this->postJson("/api/v1/subscriptions/{$active->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('active', $this->enrollment->fresh()->status);
        $this->assertTrue($this->enrollment->fresh()->ended_at->equalTo($active->period_end));
        $this->assertSame('cancelled', $renewal->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'subscription.cancellation_scheduled')->where('entity_id', $active->id)->exists());

        $this->getJson('/api/v1/enrollments')->assertJsonPath('data.0.ends_at_period_end', true);

        $this->postJson("/api/v1/subscriptions/{$active->id}/cancel")
            ->assertConflict()
            ->assertJsonPath('message', __('subscriptions.already_cancelled', [], 'ar'));
    }

    public function test_expired_or_cancelled_subscriptions_are_not_cancellable(): void
    {
        $expired = Subscription::factory()->create(['enrollment_id' => $this->enrollment->id, 'status' => 'expired']);
        $cancelled = Subscription::factory()->create(['enrollment_id' => $this->enrollment->id, 'status' => 'cancelled']);

        $this->postJson("/api/v1/subscriptions/{$expired->id}/cancel")->assertUnprocessable()
            ->assertJsonPath('message', __('subscriptions.not_cancellable', [], 'ar'));
        $this->postJson("/api/v1/subscriptions/{$cancelled->id}/cancel")->assertUnprocessable();
    }

    public function test_messages_are_available_in_english(): void
    {
        $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/subscriptions?status=x')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', __('subscriptions.validation.status_invalid', [], 'en'));
    }

    public function test_access_control(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson('/api/v1/subscriptions')->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());
        $this->getJson('/api/v1/subscriptions')->assertForbidden();
        $this->postJson('/api/v1/subscriptions/1/cancel')->assertForbidden();
    }
}
