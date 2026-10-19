<?php

namespace App\Filament\Resources\Auctions\Pages;

use App\Filament\Resources\Auctions\AuctionResource;
use App\Models\Auction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EditAuction extends EditRecord
{
    protected static string $resource = AuctionResource::class;

    /**
     * user_id/status are removed from $data and assigned explicitly;
     * price/end time become immutable once the lot has its first bid.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Auction $record */
        [$data, $userId, $status] = AuctionResource::splitUserAndStatus($data);

        // Same helper the API's UpdateAuctionRequest uses, so the panel and
        // the API enforce one and the same lifecycle rule.
        if ($record->bids()->exists()) {
            $errors = [];

            foreach ($record->changedLifecycleFields($data) as $field) {
                $errors['data.'.$field] = 'Price and end time cannot be changed after the first bid. '
                    .'Edit other fields or cancel the lot instead.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $oldImage = $record->image_path;

        // Fillable only; lifecycle fields were validated above.
        $record->fill($data);

        if ($userId !== null) {
            $record->user_id = $userId;
        }

        if ($status !== null) {
            $record->status = $status;
        }

        $record->save();

        // A replaced or cleared photo drops the previous file from disk,
        // but only after the record itself was saved successfully.
        if ($oldImage !== null && $oldImage !== $record->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
