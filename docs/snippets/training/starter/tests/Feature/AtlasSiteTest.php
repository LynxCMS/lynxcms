<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The starter's smoke test: the seeded demo school answers in both languages, and the admin is there.
 */
final class AtlasSiteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_home_page_shows_the_school(): void
    {
        $this->get('/')->assertOk()->assertSee('Learn to teach wellness')->assertSee('Yoga teacher training');
    }

    public function test_the_site_answers_in_portuguese(): void
    {
        $this->get('/pt')->assertOk()->assertSee('Aprenda a ensinar bem-estar');
        $this->get('/pt/cursos/formacao-professores-yoga')->assertOk()->assertSee('Anatomia e alinhamento');
    }

    public function test_the_catalogue_filters_by_level(): void
    {
        $this->get('/courses?level=beginner')->assertOk()->assertSee('Mindful movement')->assertDontSee('Sports nutrition');
    }

    public function test_the_admin_asks_to_sign_in_on_the_site_s_own_screen(): void
    {
        $this->get('/lynx/pages')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Forgot your password?');
    }

    public function test_the_owner_opens_the_admin(): void
    {
        $this->post('/login', ['email' => 'owner@atlas-training.example', 'password' => 'change-me-now'])->assertRedirect('/account');
        $this->get('/lynx/pages')->assertOk();
    }
}
