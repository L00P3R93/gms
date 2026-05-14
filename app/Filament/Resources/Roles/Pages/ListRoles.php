<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Spatie\Permission\Models\Role;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_role')
                ->label('Add Role')
                ->icon('heroicon-o-plus')
                ->form([
                    TextInput::make('name')
                        ->label('Role Name')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    Role::create(['name' => $data['name']]);

                    Notification::make()
                        ->title('Role created.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
