<?php

namespace App\Filament\Widgets;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveAuctionsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active auctions', Auction::where('status', AuctionStatus::Active)->count())
                ->description('Lots currently open for bidding')
                ->color('success'),
        ];
    }
}
