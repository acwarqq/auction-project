<?php

namespace App\Filament\Resources\Bids\Schemas;

use App\Models\Auction;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BidForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('auction_id')
                    ->label('Lot')
                    ->options(fn (): array => Auction::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('user_id')
                    ->label('Bidder')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                // Integer kopecks; amounts are a panel-only data-fix tool -
                // the business rules live in the API bid transaction.
                TextInput::make('amount')
                    ->label('Amount (kopecks)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
            ]);
    }
}
