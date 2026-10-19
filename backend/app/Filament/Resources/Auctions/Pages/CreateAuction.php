<?php

namespace App\Filament\Resources\Auctions\Pages;

use App\Enums\AuctionStatus;
use App\Filament\Resources\Auctions\AuctionResource;
use App\Models\Auction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAuction extends CreateRecord
{
    protected static string $resource = AuctionResource::class;

    /**
     * user_id/status are removed from $data (not fillable anyway); the rest
     * goes through mass assignment, lifecycle fields are assigned explicitly.
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$data, $userId, $status] = AuctionResource::splitUserAndStatus($data);

        // Only fillable fields (title, description, image_path,
        // starting_price, min_increment, ends_at) - never status/winner/price.
        $auction = new Auction($data);

        $auction->user_id = $userId;
        $auction->status = $status ?? AuctionStatus::Active;
        $auction->current_price = $auction->starting_price;
        $auction->save();

        return $auction;
    }
}
