<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\ActiveAuctionsWidget;
use App\Filament\Widgets\TotalBidsWidget;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_to_panel_login(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_approved_non_admin_user_gets_403_on_admin(): void
    {
        $user = User::factory()->approved()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_pending_user_gets_403_on_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_user_can_open_admin_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_horizon_returns_403_for_regular_user_even_in_local_env(): void
    {
        // Horizon's own gate is bypassed in the "local" environment; the
        // "auth" + "can:viewHorizon" middleware on the routes must still
        // deny access regardless of the environment.
        $this->app['env'] = 'local';

        $user = User::factory()->approved()->create();

        $this->actingAs($user)->get('/horizon')->assertForbidden();
    }

    public function test_horizon_returns_200_for_admin_even_in_local_env(): void
    {
        $this->app['env'] = 'local';

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/horizon')->assertOk();
    }

    public function test_dashboard_shows_auction_widgets(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();

        // Widgets are lazy-loaded by Livewire, so assert their content by
        // rendering the components directly (stat labels + values).
        Livewire::test(ActiveAuctionsWidget::class)
            ->assertSee('Active auctions');

        Livewire::test(TotalBidsWidget::class)
            ->assertSee('Total bids');
    }

    public function test_admin_can_open_auction_and_bid_resource_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $lot = Auction::factory()->create(['user_id' => $admin->id]);
        $bid = Bid::factory()->create([
            'auction_id' => $lot->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/admin/auctions')->assertOk();
        $this->actingAs($admin)->get('/admin/auctions/create')->assertOk();
        $this->actingAs($admin)->get("/admin/auctions/{$lot->id}/edit")->assertOk();

        $this->actingAs($admin)->get('/admin/bids')->assertOk();
        $this->actingAs($admin)->get('/admin/bids/create')->assertOk();
        $this->actingAs($admin)->get("/admin/bids/{$bid->id}/edit")->assertOk();
    }

    public function test_non_admin_cannot_open_auction_resources(): void
    {
        $user = User::factory()->approved()->create();

        $this->actingAs($user)->get('/admin/auctions')->assertForbidden();
        $this->actingAs($user)->get('/admin/bids')->assertForbidden();
    }
}
