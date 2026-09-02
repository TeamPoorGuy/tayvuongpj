<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiEntrypointTest extends TestCase
{
    public function test_backend_root_describes_the_api(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('name', 'SportHub API')
            ->assertJsonPath('version', 'v1');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
