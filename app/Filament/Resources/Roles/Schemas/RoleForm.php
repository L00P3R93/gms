<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Role Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'Role name is required.',
                        'unique' => 'This role name is already in use.',
                    ])
                    ->maxLength(255),
                Select::make('permissions')
                    ->label('Permissions')
                    ->relationship('permissions', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->native(false)
                    ->createOptionAction(fn (Action $action) => $action->visible(auth()->user()->can('manage permissions')))
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Permission Name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->validationMessages([
                        'required' => 'At least one permission is required.',
                    ])
                    ->required(),
            ])->columnSpan(['lg' => fn (?Role $record) => $record === null ? 3 : 2]),
            Section::make()->schema([
                TextEntry::make('created_at')->state(fn (Role $record): ?string => $record->created_at?->diffForHumans()),
                TextEntry::make('updated_at')->label('Last modified at')->state(fn (Role $record): ?string => $record->updated_at?->diffForHumans()),
            ])->columnSpan(['lg' => 1])->hidden(fn (?Role $record) => $record === null),
        ])->columns(3);
    }
}
