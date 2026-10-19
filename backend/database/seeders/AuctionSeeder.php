<?php

namespace Database\Seeders;

use App\Actions\CloseAuction;
use App\Enums\AuctionStatus;
use App\Enums\UserStatus;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo lots and bids. Idempotent: the lot title is the natural key,
 * bids are deduplicated by (bidder, amount), derived state (current price,
 * winner) is recomputed on every run - so `migrate --seed` may run twice.
 */
class AuctionSeeder extends Seeder
{
    public function run(): void
    {
        $pool = User::where('status', UserStatus::Approved)->orderBy('id')->get();
        $seller1 = $pool->firstWhere('email', 'approved@example.com') ?? $pool->first();

        if ($seller1 === null || $pool->count() < 2) {
            $this->command?->warn('Not enough approved users - auction demo lots skipped.');

            return;
        }

        // Second seller; guarantees every bidder differs from the lot's seller.
        $seller2 = $pool->first(fn (User $user): bool => $user->id !== $seller1->id);

        foreach ($this->lots($seller1, $seller2) as $lot) {
            $auction = $this->firstOrCreateAuction($lot);

            $bidders = $pool
                ->where('id', '!=', $auction->user_id)
                ->take($lot['bids']);

            $this->seedBids($auction, $bidders);

            if ($lot['close'] ?? false) {
                // Reuses the real closing path: assigns the winner and
                // flips status to "closed" (no-op on subsequent runs).
                app(CloseAuction::class)->handle($auction);
            }
        }

        $this->command?->info('Auction demo lots seeded.');
    }

    /**
     * @return list<array{seller: User, title: string, description: string, starting_price: int, min_increment: int, ends_at: Carbon, bids: int, status?: AuctionStatus, close?: bool}>
     */
    private function lots(User $seller1, User $seller2): array
    {
        return [
            [
                'seller' => $seller1,
                'title' => 'Vintage film camera Rolleiflex 35',
                'description' => 'Fully working twin-lens reflex camera from the 1960s, original leather case included.',
                'starting_price' => 250000,
                'min_increment' => 5000,
                'ends_at' => now()->addDays(5),
                'bids' => 2,
            ],
            [
                'seller' => $seller2,
                'title' => 'Handmade walnut writing desk',
                'description' => 'Solid walnut desk handcrafted by a local workshop, three drawers, minor scratches on top.',
                'starting_price' => 150000,
                'min_increment' => 3000,
                'ends_at' => now()->addDays(7),
                'bids' => 0,
            ],
            [
                'seller' => $seller1,
                'title' => 'Retro wall clock, 1970s',
                'description' => 'Sunburst wall clock in working order, new quartz movement installed.',
                'starting_price' => 80000,
                'min_increment' => 2000,
                'ends_at' => now()->addHours(2),
                'bids' => 1,
            ],
            [
                'seller' => $seller2,
                'title' => 'Sealed collectible coin set',
                'description' => 'Mint-sealed commemorative coin set with certificate of authenticity.',
                'starting_price' => 120000,
                'min_increment' => 5000,
                'ends_at' => now()->subDay(),
                'bids' => 2,
                // Ended in the past: closed with a winner by CloseAuction.
                'close' => true,
            ],
            [
                'seller' => $seller1,
                'title' => 'Antique chess set, hand-carved',
                'description' => 'Ivory-look hand-carved chess set, one pawn has a small chip. Lot cancelled by the seller.',
                'starting_price' => 45000,
                'min_increment' => 1000,
                'ends_at' => now()->subDays(3),
                'bids' => 0,
                'status' => AuctionStatus::Cancelled,
            ],
        ];
    }

    /**
     * @param  array{seller: User, title: string, description: string, starting_price: int, min_increment: int, ends_at: Carbon, bids: int, status?: AuctionStatus, close?: bool}  $lot
     */
    private function firstOrCreateAuction(array $lot): Auction
    {
        $auction = Auction::where('title', $lot['title'])->first();

        if ($auction !== null) {
            return $auction;
        }

        // Only fillable fields go through mass assignment.
        $auction = new Auction([
            'title' => $lot['title'],
            'description' => $lot['description'],
            'starting_price' => $lot['starting_price'],
            'min_increment' => $lot['min_increment'],
            'ends_at' => $lot['ends_at'],
        ]);

        // user_id, status and current_price are never fillable - explicit.
        $auction->user_id = $lot['seller']->id;
        $auction->status = $lot['status'] ?? AuctionStatus::Active;
        $auction->current_price = $auction->starting_price;
        $auction->save();

        return $auction;
    }

    /**
     * Sequential valid bids: the first equals the starting price, each next
     * one adds exactly min_increment. Recomputes current_price every run.
     *
     * @param  Collection<int, User>  $bidders
     */
    private function seedBids(Auction $auction, Collection $bidders): void
    {
        $amount = $auction->starting_price;

        foreach ($bidders as $index => $bidder) {
            if ($index > 0) {
                $amount += $auction->min_increment;
            }

            $exists = $auction->bids()
                ->where('user_id', $bidder->id)
                ->where('amount', $amount)
                ->exists();

            if (! $exists) {
                // amount is fillable; user_id is not - assign explicitly.
                $bid = $auction->bids()->make(['amount' => $amount]);
                $bid->user_id = $bidder->id;
                $bid->save();
            }
        }

        // Idempotent: derives current_price from the actual bid history
        // (also done by Bid model events when they are not muted).
        $auction->syncCurrentPrice();
    }
}
