<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * role/status are removed from $data (they are not fillable anyway) and
     * applied to the record via explicit property assignment instead.
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$data, $role, $status] = UserResource::splitRoleAndStatus($data);

        $user = User::create($data);

        if ($role !== null) {
            $user->role = $role;
        }

        if ($status !== null) {
            $user->status = $status;
        }

        $user->save();

        return $user;
    }
}
