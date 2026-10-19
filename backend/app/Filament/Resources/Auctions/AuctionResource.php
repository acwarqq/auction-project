<?php

namespace App\Filament\Resources\Auctions;

use App\Enums\AuctionStatus;
use App\Filament\Resources\Auctions\Pages\CreateAuction;
use App\Filament\Resources\Auctions\Pages\EditAuction;
use App\Filament\Resources\Auctions\Pages\ListAuctions;
use App\Filament\Resources\Auctions\Schemas\AuctionForm;
use App\Filament\Resources\Auctions\Tables\AuctionsTable;
use App\Models\Auction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuctionResource extends Resource
{
    protected static ?string $model = Auction::class;

    protected static ?string $navigationLabel = 'Auctions';

    protected static ?string $modelLabel = 'auction';

    protected static ?string $pluralModelLabel = 'auctions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public static function form(Schema $schema): Schema
    {
        return AuctionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuctionsTable::configure($table);
    }

    /** The table shows a bid counter, so every lot query carries bids_count. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('bids');
    }

    /**
     * Pull user_id/status out of the incoming form data: they are never part
     * of a mass-assignment call and must be applied explicitly instead.
     *
     * @return array{0: array<string, mixed>, 1: int|null, 2: AuctionStatus|null}
     */
    public static function splitUserAndStatus(array $data): array
    {
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $status = isset($data['status']) ? AuctionStatus::from($data['status']) : null;

        unset($data['user_id'], $data['status']);

        return [$data, $userId, $status];
    }

    /**
     * The API allows DELETE only for lots without bids (422 + "cancel it
     * instead"). The panel's DeleteAction/DeleteBulkAction ask the resource,
     * so mirror the same invariant here.
     */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof Auction && $record->bids()->exists()) {
            return Response::deny('A lot with bids cannot be deleted - cancel it instead.');
        }

        return parent::getDeleteAuthorizationResponse($record);
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
            'index' => ListAuctions::route('/'),
            'create' => CreateAuction::route('/create'),
            'edit' => EditAuction::route('/{record}/edit'),
        ];
    }
}
