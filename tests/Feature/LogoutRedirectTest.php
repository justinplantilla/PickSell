<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Where signing out on the website lands. */
class LogoutRedirectTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_buyers_land_on_the_login_page_and_others_on_the_home_page(): void
    {
        $this->actingAs($this->buyer())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->actingAs($this->seller())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
