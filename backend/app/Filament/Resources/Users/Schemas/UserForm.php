<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    // Never dehydrate an empty password: leaving the field blank
                    // on edit must keep the current password untouched.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Select::make('role')
                    ->options(UserRole::class)
                    ->default(UserRole::User->value)
                    ->required(),
                Select::make('status')
                    ->options(UserStatus::class)
                    ->default(UserStatus::Pending->value)
                    ->required(),
            ]);
    }
}
