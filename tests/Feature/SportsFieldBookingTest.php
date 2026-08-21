<?php

namespace Tests\Feature;

use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportsFieldBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_customer_can_view_fields_list(): void
    {
        $response = $this->get('/fields');
        $response->assertStatus(200);
    }

    public function test_customer_can_access_login_page(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_customer_can_access_register_page(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }
}
