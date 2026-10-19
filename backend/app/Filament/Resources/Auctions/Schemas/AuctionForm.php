<?php

namespace App\Filament\Resources\Auctions\Schemas;

use App\Enums\AuctionStatus;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Seller')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Description')
                    ->required()
                    ->rows(3)
                    ->maxLength(5000),
                FileUpload::make('image_path')
                    ->label('Photo')
                    ->disk('public')
                    ->directory('auctions')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->nullable()
                    ->helperText('JPG, PNG or WebP, up to 2 MB. Public disk - run php artisan storage:link.'),
                // All money fields are integers in kopecks - never rubles with floats.
                TextInput::make('starting_price')
                    ->label('Starting price (kopecks)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                TextInput::make('min_increment')
                    ->label('Min increment (kopecks)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->label('Ends at')
                    ->secondsStep(1)
                    ->required()
                    // On edit a past deadline is legitimate (closed/cancelled
                    // lots); only a brand-new lot must end in the future.
                    ->rules(fn (string $operation): array => $operation === 'create' ? ['after:now'] : []),
                Select::make('status')
                    ->label('Status')
                    ->options(AuctionStatus::class)
                    ->default(AuctionStatus::Active->value)
                    ->required(),
            ]);
    }
}
