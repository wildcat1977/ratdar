<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_uses_custom_canonical_url(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee('<link rel="canonical" href="https://ratdar.taipei/">', false);
    }

    public function test_reports_page_uses_custom_canonical_url(): void
    {
        $this->get('/reports')
            ->assertStatus(200)
            ->assertSee('<link rel="canonical" href="https://ratdar.taipei/reports">', false);
    }

    public function test_contact_page_uses_custom_canonical_url(): void
    {
        $this->get('/contact')
            ->assertStatus(200)
            ->assertSee('<link rel="canonical" href="https://ratdar.taipei/contact">', false);
    }

    public function test_page_without_override_falls_back_to_current_url(): void
    {
        $this->get('/leaderboard')
            ->assertStatus(200)
            ->assertSee('<link rel="canonical" href="'.url('/leaderboard').'">', false);
    }
}
