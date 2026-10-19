<?php

namespace App\Console\Commands;

use App\Actions\CloseAuction;
use App\Enums\AuctionStatus;
use App\Models\Auction;
use Illuminate\Console\Command;

/** Thin wrapper: picks expired active lots and delegates to App\Actions\CloseAuction. */
class CloseExpiredAuctions extends Command
{
    protected $signature = 'auctions:close';

    protected $description = 'Close active auctions whose end time has passed and assign winners';

    public function handle(CloseAuction $close): int
    {
        $expired = Auction::query()
            ->where('status', AuctionStatus::Active)
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expired as $auction) {
            $close->handle($auction);
        }

        $this->info(sprintf('Closed %d auction(s).', $expired->count()));

        return self::SUCCESS;
    }
}
