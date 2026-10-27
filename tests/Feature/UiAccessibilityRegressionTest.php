<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UiAccessibilityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_links_target_focusable_main_regions(): void
    {
        $login = $this->get(route('login'))->assertOk();
        $login->assertSee('href="#main-content"', false);
        $login->assertSee('<main id="main-content" class="login-shell" tabindex="-1">', false);

        $it = $this->account('it');
        $dashboard = $this->actingAs($it)->get(route('dashboard'))->assertOk();
        $dashboard->assertSee('href="#main-content"', false);
        $dashboard->assertSee('<main id="main-content" class="content" tabindex="-1">', false);
    }

    public function test_current_ticket_tab_and_analytics_range_are_announced(): void
    {
        $it = $this->account('it');

        $tickets = $this->actingAs($it)
            ->get(route('dashboard', ['view' => 'tickets', 'mine' => '1']))
            ->assertOk();
        $tickets->assertSee('href="index.php?view=tickets&amp;mine=1" aria-current="page"', false);

        $analytics = $this->get(route('dashboard', ['view' => 'analytics', 'range' => 30]))
            ->assertOk();
        $analytics->assertSee('href="index.php?view=analytics&amp;range=30" aria-current="page"', false);
    }

    public function test_mobile_target_overrides_cover_controls_limited_by_specificity(): void
    {
        $css = file_get_contents(public_path('assets/styles.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 700px\)\s*\{\s*\.btn\.compact,\s*\.user-actions \.btn,\s*\.responsive-ticket-table tbody td > \.row-arrow\s*\{\s*min-height: 44px;/',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.filters > \.smart-select \.smart-select-trigger,\s*\.filters > \.custom-date,\s*\.filters > input\[type="date"\],\s*\.filters > \.btn-reset-filter,\s*\.filters > \.btn\.light\s*\{\s*min-height: 44px !important;/',
            $css,
        );
        $this->assertStringContainsString('#main-content:focus', $css);
    }

    private function account(string $role): User
    {
        return User::query()->create([
            'name' => 'QA '.ucfirst($role),
            'email' => $role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('not-a-real-account-password'),
            'role' => $role,
            'department' => 'QA',
            'job_title' => 'Tester',
            'active' => true,
        ]);
    }
}
