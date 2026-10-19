<?php

namespace App\Filament\Resources\Auctions\Tables;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuctionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label('Seller')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('starting_price')
                    ->label('Start price')
                    ->formatStateUsing(fn (int|string $state): string => Auction::formatPrice((int) $state))
                    ->sortable(),
                TextColumn::make('current_price')
                    ->label('Current price')
                    ->formatStateUsing(fn (int|string $state): string => Auction::formatPrice((int) $state))
                    ->sortable(),
                TextColumn::make('bids_count')
                    ->label('Bids')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AuctionStatus $state): string => ucfirst($state->value))
                    ->color(fn (AuctionStatus $state): string => match ($state) {
                        AuctionStatus::Active => 'success',
                        AuctionStatus::Closed => 'gray',
                        AuctionStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(fn (): array => collect(AuctionStatus::cases())
                        ->mapWithKeys(fn (AuctionStatus $status): array => [$status->value => ucfirst($status->value)])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
