<?php

namespace App\Models;

use App\Enums\AuctionStatus;
use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory;

    /**
     * Money fields are integers in kopecks. status, winner_id and current_price
     * are lifecycle fields and are never mass assignable (see AGENTS.md).
     */
    protected $fillable = [
        'title',
        'description',
        'image_path',
        'starting_price',
        'min_increment',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starting_price' => 'integer',
            'min_increment' => 'integer',
            'current_price' => 'integer',
            'ends_at' => 'datetime',
            'status' => AuctionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Auction $auction): void {
            if ($auction->image_path !== null) {
                Storage::disk('public')->delete($auction->image_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Lifecycle fields that would actually change if the given data were applied.
     * Used by UpdateAuctionRequest and the Filament resource so both enforce
     * exactly the same rule: price and end time are immutable after the first bid.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function changedLifecycleFields(array $data): array
    {
        $changed = [];

        foreach (['starting_price', 'min_increment'] as $field) {
            if (array_key_exists($field, $data)
                && (int) $data[$field] !== (int) $this->{$field}) {
                $changed[] = $field;
            }
        }

        if (array_key_exists('ends_at', $data)
            && $data['ends_at'] !== null
            && $this->ends_at !== null
            && Carbon::parse($data['ends_at'])->startOfMinute()->getTimestamp()
                !== $this->ends_at->startOfMinute()->getTimestamp()) {
            $changed[] = 'ends_at';
        }

        return $changed;
    }

    /**
     * Recompute current_price from the actual bids: the highest bid wins,
     * no bids means the starting price. Idempotent - called by Bid model
     * events (panel edits, deletes, reassignments) and by the seeder, so
     * the stored price can never drift from the bid history.
     */
    public function syncCurrentPrice(): void
    {
        $topBid = $this->bids()->orderByDesc('amount')->orderBy('created_at')->first();

        $price = $topBid !== null
            ? max($this->starting_price, $topBid->amount)
            : $this->starting_price;

        if ($this->current_price !== $price) {
            // current_price is not fillable - assign and save explicitly.
            $this->current_price = $price;
            $this->save();
        }
    }

    /**
     * Display helper: kopecks -> rubles with a currency sign (UI only,
     * storage and API always stay integers in kopecks).
     */
    public static function formatPrice(int $kopecks): string
    {
        return number_format($kopecks / 100, 2, ',', ' ').' ₽';
    }
}
