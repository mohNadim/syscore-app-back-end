<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiLocaleTest extends TestCase
{
    public function test_defaults_to_arabic_when_header_is_missing(): void
    {
        $this->getJson('/api/v1/ping', ['Accept-Language' => ''])
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertJson(['message' => trans('api.pong', [], 'ar')]);
    }

    public function test_english_is_used_when_requested(): void
    {
        $this->getJson('/api/v1/ping', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJson(['message' => trans('api.pong', [], 'en')]);
    }

    public function test_complex_accept_language_header_is_parsed(): void
    {
        $this->getJson('/api/v1/ping', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJson(['message' => trans('api.pong', [], 'en')]);
    }

    public function test_unsupported_locale_falls_back_to_arabic(): void
    {
        $this->getJson('/api/v1/ping', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertJson(['message' => trans('api.pong', [], 'ar')]);
    }
}
