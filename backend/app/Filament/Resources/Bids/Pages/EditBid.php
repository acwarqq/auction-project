<?php

namespace App\Filament\Resources\Bids\Pages;

use App\Filament\Resources\Bids\BidResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBid extends EditRecord
{
    protected static string $resource = BidResource::class;

    /**
     * auction_id/user_id are assigned explicitly; a changed amount or lot
     * re-syncs the lot's current price via the Bid model's saved event.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$data, $auctionId, $userId] = BidResource::splitAuctionAndUser($data);

        $record->fill($data);

        if ($auctionId !== null) {
            $record->auction_id = $auctionId;
        }

        if ($userId !== null) {
            $record->user_id = $userId;
        }

        $record->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
