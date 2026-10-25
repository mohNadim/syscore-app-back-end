<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ApiErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        Route::middleware('api')->group(function () {
            Route::post('api/v1/_test/validate', function (Request $request) {
                $request->validate(['name' => ['required']]);
            });
            Route::get('api/v1/_test/missing-model', fn () => Staff::query()->findOrFail(0));
            Route::get('api/v1/_test/conflict', fn () => throw new ConflictHttpException('Slot taken'));
            Route::get('api/v1/_test/conflict-empty', fn () => throw new ConflictHttpException);
            Route::get('api/v1/_test/throttled', fn () => 'ok')->middleware('throttle:1,1');
            Route::get('api/v1/_test/protected', fn () => 'ok')->middleware('auth:sanctum');
        });
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/_test/protected')
            ->assertStatus(401)
            ->assertExactJson(['message' => trans('api.unauthenticated', [], 'ar')]);
    }

    public function test_unknown_api_route_returns_translated_404(): void
    {
        $this->getJson('/api/v1/nope', ['Accept-Language' => 'en'])
            ->assertStatus(404)
            ->assertExactJson(['message' => trans('api.not_found', [], 'en')]);
    }

    public function test_method_not_allowed_returns_405(): void
    {
        $this->postJson('/api/v1/ping')
            ->assertStatus(405)
            ->assertExactJson(['message' => trans('api.method_not_allowed', [], 'ar')]);
    }

    public function test_validation_error_is_arabic_by_default(): void
    {
        $this->postJson('/api/v1/_test/validate')
            ->assertStatus(422)
            ->assertJsonPath('message', trans('api.validation_failed', [], 'ar'))
            ->assertJsonPath('errors.name.0', trans('validation.required', ['attribute' => 'name'], 'ar'));
    }

    public function test_validation_error_is_english_when_requested(): void
    {
        $this->postJson('/api/v1/_test/validate', [], ['Accept-Language' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('message', trans('api.validation_failed', [], 'en'))
            ->assertJsonPath('errors.name.0', 'The name field is required.');
    }

    public function test_model_not_found_returns_404(): void
    {
        $this->getJson('/api/v1/_test/missing-model')
            ->assertStatus(404)
            ->assertExactJson(['message' => trans('api.not_found', [], 'ar')]);
    }

    public function test_conflict_uses_exception_message_or_default(): void
    {
        $this->getJson('/api/v1/_test/conflict')
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Slot taken']);

        $this->getJson('/api/v1/_test/conflict-empty')
            ->assertStatus(409)
            ->assertExactJson(['message' => trans('api.conflict', [], 'ar')]);
    }

    public function test_throttled_request_returns_429_with_retry_after(): void
    {
        $this->getJson('/api/v1/_test/throttled')->assertOk();

        $this->getJson('/api/v1/_test/throttled')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertExactJson(['message' => trans('api.too_many_requests', [], 'ar')]);
    }
}
