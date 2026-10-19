<?php

namespace Tests\Feature\Commands;

use App\Actions\CloseAuction;
use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CloseExpiredAuctionsTest extends TestCase
{
    use RefreshDatabase;

    private function expiredLot(): Auction
    {
        // Active, but ends_at is already in the past. The starting price is
        // pinned low so test bids always exceed it.
        return Auction::factory()->ended()->create([
            'starting_price' => 50000,
            'current_price' => 50000,
        ]);
    }

    private function bidOn(Auction $auction, User $bidder, int $amount, DateTimeInterface $createdAt): void
    {
        // created_at is set explicitly to control the tie-break order.
        Bid::factory()->create([
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => $amount,
            'created_at' => $createdAt,
        ]);
    }

    public function test_closes_expired_active_lot_and_awards_the_highest_bidder(): void
    {
        $early = User::factory()->approved()->create();
        $late = User::factory()->approved()->create();

        $lot = $this->expiredLot();
        $this->bidOn($lot, $early, 100000, now()->subMinutes(10));
        $this->bidOn($lot, $late, 105000, now()->subMinutes(5));

        $future = Auction::factory()->create(); // active, ends in the future
        $cancelled = Auction::factory()->cancelled()->create();

        Artisan::call('auctions:close');

        $lot->refresh();
        $this->assertSame(AuctionStatus::Closed, $lot->status);
        $this->assertSame($late->id, $lot->winner_id);
        $this->assertSame(105000, $lot->current_price);

        $future->refresh();
        $this->assertSame(AuctionStatus::Active, $future->status);
        $this->assertNull($future->winner_id);

        $cancelled->refresh();
        $this->assertSame(AuctionStatus::Cancelled, $cancelled->status);
        $this->assertNull($cancelled->winner_id);
    }

    public function test_tie_is_won_by_the_earliest_bid(): void
    {
        $first = User::factory()->approved()->create();
        $second = User::factory()->approved()->create();

        $lot = $this->expiredLot();
        $this->bidOn($lot, $first, 100000, now()->subMinutes(10));
        $this->bidOn($lot, $second, 100000, now()->subMinutes(5));

        Artisan::call('auctions:close');

        $lot->refresh();
        $this->assertSame(AuctionStatus::Closed, $lot->status);
        $this->assertSame($first->id, $lot->winner_id);
    }

    public function test_close_is_idempotent(): void
    {
        $bidder = User::factory()->approved()->create();

        $lot = $this->expiredLot();
        $this->bidOn($lot, $bidder, 77700, now()->subHour());

        Artisan::call('auctions:close');
        Artisan::call('auctions:close');

        $lot->refresh();
        $this->assertSame(AuctionStatus::Closed, $lot->status);
        $this->assertSame($bidder->id, $lot->winner_id);
        $this->assertSame(1, Auction::where('status', AuctionStatus::Closed)->count());
    }

    public function test_lots_without_bids_close_without_winner(): void
    {
        $lot = $this->expiredLot();

        Artisan::call('auctions:close');

        $lot->refresh();
        $this->assertSame(AuctionStatus::Closed, $lot->status);
        $this->assertNull($lot->winner_id);
    }

    public function test_close_action_does_not_touch_already_closed_lots(): void
    {
        $closed = Auction::factory()->closed()->create(); // winner is null

        $result = app(CloseAuction::class)->handle($closed);

        $this->assertSame(AuctionStatus::Closed, $result->status);
        $this->assertNull($result->winner_id);
    }
}
