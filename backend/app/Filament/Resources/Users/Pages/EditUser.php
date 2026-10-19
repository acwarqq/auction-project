<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * role/status are removed from $data (they are not fillable anyway) and
     * applied to the record via explicit property assignment instead.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$data, $role, $status] = UserResource::splitRoleAndStatus($data);

        $record->update($data);

        if ($role !== null) {
            $record->role = $role;
        }

        if ($status !== null) {
            $record->status = $status;
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
