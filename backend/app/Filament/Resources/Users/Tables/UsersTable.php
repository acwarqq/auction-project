<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => ucfirst($state->value))
                    ->color(fn (UserRole $state): string => $state === UserRole::Admin ? 'danger' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => ucfirst($state->value))
                    ->color(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Pending => 'warning',
                        UserStatus::Approved => 'success',
                        UserStatus::Rejected => 'danger',
                        UserStatus::Banned => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(fn (): array => collect(UserStatus::cases())
                        ->mapWithKeys(fn (UserStatus $status): array => [$status->value => ucfirst($status->value)])
                        ->all()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::Check)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve user')
                    ->modalDescription('This user will be able to sign in to the website.')
                    ->visible(fn (User $record): bool => $record->status !== UserStatus::Approved)
                    // Explicit assignment: role/status never go through fill()/update().
                    ->action(function (User $record): void {
                        $record->status = UserStatus::Approved;
                        $record->save();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::XMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject user')
                    ->modalDescription('This user will no longer be able to sign in to the website.')
                    ->visible(fn (User $record): bool => $record->status !== UserStatus::Rejected)
                    // Explicit assignment: role/status never go through fill()/update().
                    ->action(function (User $record): void {
                        $record->status = UserStatus::Rejected;
                        $record->save();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve')
                        ->icon(Heroicon::Check)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve selected users')
                        ->modalDescription('Selected users will be able to sign in to the website.')
                        ->deselectRecordsAfterCompletion()
                        // Explicit assignment: role/status never go through fill()/update().
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->status = UserStatus::Approved;
                                $record->save();
                            }
                        }),
                    BulkAction::make('reject')
                        ->label('Reject')
                        ->icon(Heroicon::XMark)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reject selected users')
                        ->modalDescription('Selected users will no longer be able to sign in to the website.')
                        ->deselectRecordsAfterCompletion()
                        // Explicit assignment: role/status never go through fill()/update().
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->status = UserStatus::Rejected;
                                $record->save();
                            }
                        }),
                ]),
            ]);
    }
}
