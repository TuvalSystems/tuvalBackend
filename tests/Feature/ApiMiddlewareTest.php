<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiMiddlewareTest extends TestCase
{
    public function test_protected_api_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_api_preflight_uses_framework_cors_middleware(): void
    {
        $this->call('OPTIONS', '/api/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
