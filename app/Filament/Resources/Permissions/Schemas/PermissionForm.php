<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Permission Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'Permission name is required.',
                        'unique' => 'This permission name is already in use.',
                        'length' => 'Permission name must be between 2 and 255 characters.',
                    ])
                    ->maxLength(255),
                Select::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->native(false)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Role Name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->validationMessages([
                        'required' => 'At least one role is required.',
                    ])
                    ->required(),
            ])->columnSpan(['lg' => fn (?Permission $record) => $record === null ? 3 : 2]),

            Section::make()->schema([
                TextEntry::make('created_at')->state(fn (Permission $record): ?string => $record->created_at?->diffForHumans()),
                TextEntry::make('updated_at')->label('Last modified at')->state(fn (Permission $record): ?string => $record->updated_at?->diffForHumans()),
            ])->columnSpan(['lg' => 1])->hidden(fn (?Permission $record) => $record === null),
        ]);
    }
}
