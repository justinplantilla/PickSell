<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSplitPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_register_render_the_same_page_with_the_matching_form_active(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('data-mode="login"', false)
            ->assertSee('id="loginForm"', false)
            ->assertSee('id="registerForm"', false)
            ->assertSee('<title>PickSell — Log In</title>', false);

        $this->get('/register')->assertOk()
            ->assertSee('data-mode="register"', false)
            ->assertSee('id="loginForm"', false)
            ->assertSee('id="registerForm"', false)
            ->assertSee('<title>PickSell — Create Account</title>', false);
    }

    public function test_the_auth_page_is_full_screen_without_the_site_navbar_or_footer(): void
    {
        foreach (['/login', '/register'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('class="nav-top"', false)
                ->assertDontSee('class="footer-inner"', false)
                ->assertSee('<a class="auth-back" href="/shop">', false)
                ->assertSee('Back to shop');
        }

        $this->get('/forgot-password')->assertOk()
            ->assertSee('class="nav-top"', false)
            ->assertSee('class="footer-inner"', false);
    }

    public function test_only_the_active_form_shows_validation_errors(): void
    {
        $login = $this->from('/login')->followingRedirects()
            ->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertOk()->getContent();
        $this->assertStringContainsString('Invalid email or password.', $this->between($login, 'id="loginTitle"', 'id="loginForm"'));
        $this->assertStringNotContainsString('field-error', $this->between($login, 'id="registerTitle"', '</form>'));

        $register = $this->from('/register')->followingRedirects()
            ->post('/register', ['role' => 'buyer'])
            ->assertOk()->getContent();
        $this->assertStringContainsString('field-error', $this->between($register, 'id="registerTitle"', '</form>'));
        $this->assertStringNotContainsString('alert-error', $this->between($register, 'id="loginTitle"', 'id="loginForm"'));
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, "Missing {$start}");

        return substr($html, $from, strpos($html, $end, $from) - $from);
    }
}
