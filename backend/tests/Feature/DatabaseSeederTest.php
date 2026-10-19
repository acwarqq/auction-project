<?php

namespace Tests\Feature;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_are_idempotent(): void
    {
        $this->seed();

        $counts = [
            'users' => User::count(),
            'auctions' => Auction::count(),
            'bids' => Bid::count(),
        ];

        // Demo data must exist after `migrate --seed` (the exact number of
        // users depends on whether ADMIN_EMAIL is configured).
        $this->assertGreaterThanOrEqual(5, $counts['users']);
        $this->assertSame(5, $counts['auctions']);
        $this->assertGreaterThan(0, $counts['bids']);

        // The closed demo lot carries a winner and a synced current price.
        $closed = Auction::where('title', 'Sealed collectible coin set')->firstOrFail();
        $this->assertSame(AuctionStatus::Closed, $closed->status);
        $this->assertNotNull($closed->winner_id);
        $this->assertSame(
            (int) $closed->bids()->max('amount'),
            $closed->current_price,
        );

        // The cancelled demo lot exists too.
        $this->assertSame(
            1,
            Auction::where('status', AuctionStatus::Cancelled)->count(),
        );

        // A second run must not duplicate anything or change derived state.
        $this->seed();

        $this->assertSame($counts['users'], User::count());
        $this->assertSame($counts['auctions'], Auction::count());
        $this->assertSame($counts['bids'], Bid::count());

        $closed->refresh();
        $this->assertSame(AuctionStatus::Closed, $closed->status);
        $this->assertNotNull($closed->winner_id);
        $this->assertSame(
            (int) $closed->bids()->max('amount'),
            $closed->current_price,
        );
    }
}
