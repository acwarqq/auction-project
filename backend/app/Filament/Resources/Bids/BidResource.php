<?php

namespace App\Filament\Resources\Bids;

use App\Filament\Resources\Bids\Pages\CreateBid;
use App\Filament\Resources\Bids\Pages\EditBid;
use App\Filament\Resources\Bids\Pages\ListBids;
use App\Filament\Resources\Bids\Schemas\BidForm;
use App\Filament\Resources\Bids\Tables\BidsTable;
use App\Models\Bid;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BidResource extends Resource
{
    protected static ?string $model = Bid::class;

    protected static ?string $navigationLabel = 'Bids';

    protected static ?string $modelLabel = 'bid';

    protected static ?string $pluralModelLabel = 'bids';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return BidForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BidsTable::configure($table);
    }

    /**
     * auction_id/user_id are never mass assignable: pull them out of the
     * form data and apply them via explicit property assignment.
     *
     * @return array{0: array<string, mixed>, 1: int|null, 2: int|null}
     */
    public static function splitAuctionAndUser(array $data): array
    {
        $auctionId = isset($data['auction_id']) ? (int) $data['auction_id'] : null;
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        unset($data['auction_id'], $data['user_id']);

        return [$data, $auctionId, $userId];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBids::route('/'),
            'create' => CreateBid::route('/create'),
            'edit' => EditBid::route('/{record}/edit'),
        ];
    }
}
