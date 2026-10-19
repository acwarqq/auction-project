<?php

namespace App\Filament\Resources\Bids\Pages;

use App\Filament\Resources\Bids\BidResource;
use App\Models\Bid;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBid extends CreateRecord
{
    protected static string $resource = BidResource::class;

    /**
     * auction_id/user_id are removed from $data (not fillable) and set
     * explicitly; saving the bid also refreshes the lot's current price
     * via the Bid model's saved event.
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$data, $auctionId, $userId] = BidResource::splitAuctionAndUser($data);

        // Only the amount (integer kopecks) goes through mass assignment.
        $bid = new Bid($data);

        $bid->auction_id = $auctionId;
        $bid->user_id = $userId;
        $bid->save();

        return $bid;
    }
}
