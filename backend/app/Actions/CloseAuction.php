<?php

namespace App\Actions;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use Illuminate\Support\Facades\DB;

/**
 * Closes one auction: assigns the winner (highest bid; on a tie the earliest
 * bid wins) and flips status to "closed". Runs in a transaction with the
 * auction row locked, so it is safe to call concurrently and idempotent
 * (an already closed/cancelled lot is returned untouched).
 *
 * Used by the `auctions:close` command today and by the CloseAuctionJob
 * scheduled for stage 5 - keep all closing logic here, commands stay thin.
 */
class CloseAuction
{
    public function handle(Auction $auction): Auction
    {
        return DB::transaction(function () use ($auction): Auction {
            /** @var Auction $locked */
            $locked = Auction::query()->lockForUpdate()->find($auction->getKey());

            if (! $locked || $locked->status !== AuctionStatus::Active) {
                return $locked ?? $auction;
            }

            // Highest amount wins; on equal amounts the earliest bid wins.
            $topBid = $locked->bids()
                ->orderByDesc('amount')
                ->orderBy('created_at')
                ->first();

            // winner_id is not fillable - assign explicitly.
            $locked->winner_id = $topBid?->user_id;
            $locked->status = AuctionStatus::Closed;
            $locked->save();

            return $locked;
        });
    }
}
