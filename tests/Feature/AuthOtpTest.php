<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+963912345678';

    /**
     * Codes captured by the fake sender, keyed by phone number.
     *
     * @var array<string, string>
     */
    private array $sentCodes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        $this->app->instance(OtpSender::class, new class($this->sentCodes) implements OtpSender
        {
            /**
             * @param  array<string, string>  $sentCodes
             */
            public function __construct(private array &$sentCodes) {}

            public function send(string $phone, string $code, string $channel): void
            {
                $this->sentCodes[$phone] = $code;
            }
        });
    }

    /**
     * Store an unconsumed code for the phone with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function storeCode(string $code, array $attributes = []): OtpCode
    {
        return OtpCode::factory()->create([
            'phone' => self::PHONE,
            'code_hash' => Hash::make($code),
            'created_at' => now()->subMinutes(2),
            ...$attributes,
        ]);
    }

    public function test_request_sends_code_and_stores_only_its_hash(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])
            ->assertAccepted()
            ->assertJsonPath('message', __('otp.sent', [], 'ar'))
            ->assertJsonPath('data.expires_in', 300)
            ->assertJsonPath('data.resend_after', 60);

        $code = $this->sentCodes[self::PHONE];
        $otp = OtpCode::query()->where('phone', self::PHONE)->sole();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $this->assertSame('sms', $otp->channel);
    }

    public function test_request_uses_channel_and_ttl_from_settings(): void
    {
        Setting::create(['key' => 'otp.channel', 'value' => 'whatsapp']);
        Setting::create(['key' => 'otp.ttl_minutes', 'value' => 10]);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])
            ->assertAccepted()
            ->assertJsonPath('data.expires_in', 600);

        $this->assertSame('whatsapp', OtpCode::query()->sole()->channel);
    }

    public function test_request_normalizes_local_phone_format(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '0912 345 678'])->assertAccepted();

        $this->assertArrayHasKey(self::PHONE, $this->sentCodes);
    }

    public function test_request_rejects_missing_phone_in_arabic_and_english(): void
    {
        $this->postJson('/api/v1/auth/otp/request', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', __('otp.validation.phone_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/otp/request', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.phone.0', 'The phone number is required.');
    }

    public function test_request_rejects_invalid_phone_formats(): void
    {
        foreach (['12345', '+963812345678', '09123456789', 'abc', 912345678] as $phone) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone])
                ->assertUnprocessable()
                ->assertJsonPath('errors.phone.0', __('otp.validation.phone_invalid', [], 'ar'));
        }

        $this->assertSame(0, OtpCode::query()->count());
    }

    public function test_request_within_cooldown_is_rejected_with_retry_after(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])->assertAccepted();

        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])
            ->assertTooManyRequests()
            ->assertJsonPath('message', __('api.too_many_requests', [], 'ar'));

        $this->assertGreaterThan(0, $response->json('retry_after'));
        $this->assertTrue($response->headers->has('Retry-After'));
        $this->assertSame(1, OtpCode::query()->count());
    }

    public function test_request_is_allowed_again_after_cooldown(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])->assertAccepted();

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])->assertAccepted();
        $this->assertSame(2, OtpCode::query()->count());
    }

    public function test_request_is_throttled_per_phone(): void
    {
        Setting::create(['key' => 'otp.resend_cooldown_seconds', 'value' => 0]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '0912345678'])->assertTooManyRequests();
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+963998877665'])->assertAccepted();
    }

    public function test_verify_registers_new_user_and_returns_token(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])->assertAccepted();

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $this->sentCodes[self::PHONE],
            'device_name' => 'Pixel 8',
        ])
            ->assertOk()
            ->assertJsonPath('message', __('otp.logged_in', [], 'ar'))
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.phone', self::PHONE)
            ->assertJsonPath('data.user.preferred_lang', 'ar');

        $user = User::query()->where('phone', self::PHONE)->sole();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull(OtpCode::query()->sole()->consumed_at);
        $this->assertSame('Pixel 8', $user->tokens()->sole()->name);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_verify_logs_in_existing_user(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE]);
        $this->storeCode('123456');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0912345678', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame(1, User::query()->count());
    }

    public function test_verify_rejects_wrong_code_and_counts_attempt(): void
    {
        $otp = $this->storeCode('123456');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '654321'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('otp.invalid', [], 'ar'));

        $this->assertSame(1, $otp->fresh()->attempts);
        $this->assertNull($otp->fresh()->consumed_at);
        $this->assertSame(0, User::query()->count());
    }

    public function test_verify_blocks_code_after_max_attempts(): void
    {
        $this->storeCode('123456', ['attempts' => 4]);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('otp.too_many_attempts', [], 'ar'));

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('otp.too_many_attempts', [], 'ar'));

        $this->assertSame(0, User::query()->count());
    }

    public function test_verify_rejects_expired_code(): void
    {
        $this->storeCode('123456', ['expires_at' => now()->subSecond()]);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('otp.expired', [], 'ar'));
    }

    public function test_verify_rejects_already_used_code(): void
    {
        $this->storeCode('123456');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '123456'])->assertOk();

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The verification code has already been used. Please request a new one.');
    }

    public function test_verify_only_accepts_the_latest_code(): void
    {
        $this->storeCode('111111', ['created_at' => now()->subMinutes(3)]);
        $this->storeCode('222222');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '111111'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('otp.invalid', [], 'ar'));
    }

    public function test_verify_without_any_code_is_invalid(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('otp.invalid', [], 'ar'));
    }

    public function test_verify_validates_input(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', __('otp.validation.phone_required', [], 'ar'))
            ->assertJsonPath('errors.code.0', __('otp.validation.code_required', [], 'ar'));

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '12ab', 'device_name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('otp.validation.code_digits', [], 'ar'))
            ->assertJsonPath('errors.device_name.0', __('otp.validation.device_name_invalid', [], 'ar'));
    }

    public function test_verify_rejects_disabled_user(): void
    {
        User::factory()->inactive()->create(['phone' => self::PHONE]);
        $this->storeCode('123456');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => '123456'])
            ->assertForbidden()
            ->assertJsonPath('message', __('otp.account_disabled', [], 'ar'));
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', __('otp.logged_out', [], 'ar'));

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_requires_token(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_logout_rejects_staff_token(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $this->postJson('/api/v1/auth/logout')->assertForbidden();
    }
}
