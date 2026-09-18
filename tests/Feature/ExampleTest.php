<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_route_is_not_found(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }
}
