<?php

namespace App\Filament\Widgets;

use App\Models\Bid;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalBidsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total bids', Bid::count())
                ->description('All bids ever placed')
                ->color('info'),
        ];
    }
}
