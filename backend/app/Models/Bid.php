<?php

namespace App\Models;

use Database\Factories\BidFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    /** @use HasFactory<BidFactory> */
    use HasFactory;

    /** Amount is an integer in kopecks; auction_id and user_id are set explicitly. */
    protected $fillable = [
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Whatever created or changed a bid (API transaction, admin panel,
        // tests), the lot's current_price must keep matching the real bid
        // history - see Auction::syncCurrentPrice().
        $syncAuctionPrice = function (Bid $bid): void {
            // In the "saved" event the original still holds pre-save values,
            // so a lot reassignment resyncs both the old and the new lot.
            $auctionIds = array_unique(array_filter([
                $bid->getOriginal('auction_id'),
                $bid->auction_id,
            ]));

            foreach ($auctionIds as $auctionId) {
                Auction::find($auctionId)?->syncCurrentPrice();
            }
        };

        static::saved($syncAuctionPrice);
        static::deleted($syncAuctionPrice);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
