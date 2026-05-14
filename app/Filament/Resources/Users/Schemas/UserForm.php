<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personal Information')
                ->description('Basic user identity details.')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),
                    TextInput::make('userName')
                        ->label('Username')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->columnSpan(1),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull(),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->required(fn (string $context) => $context === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->rule(Password::defaults())
                        ->columnSpanFull(),
                ]),
            Section::make('Access & Role')
                ->description('Configure user role and account status.')
                ->icon('heroicon-o-shield-check')
                ->columns(2)
                ->schema([
                    Select::make('role')
                        ->label('Role')
                        ->options(fn () => Role::pluck('name', 'name')->toArray())
                        ->required(),
                    Select::make('status')
                        ->options(UserStatus::class)
                        ->enum(UserStatus::class)
                        ->required(),
                ]),
        ]);
    }
}
