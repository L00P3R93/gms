<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->prefixIconColor('primary'),
                    TextInput::make('userName')
                        ->label('Username')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->prefixIcon(Heroicon::OutlinedAtSymbol)
                        ->prefixIconColor('primary'),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->prefixIcon(Heroicon::OutlinedEnvelope)
                        ->prefixIconColor('primary')
                        ->columnSpanFull(),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->required(fn (string $context) => $context === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->rule(Password::defaults())
                        ->prefixIcon(Heroicon::OutlinedLockClosed)
                        ->prefixIconColor('warning')
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
                        ->required()
                        ->prefixIcon(Heroicon::OutlinedShieldCheck)
                        ->prefixIconColor('info'),
                    Select::make('status')
                        ->options(UserStatus::class)
                        ->enum(UserStatus::class)
                        ->required()
                        ->prefixIcon(Heroicon::OutlinedCheckCircle)
                        ->prefixIconColor('success'),
                ]),
        ]);
    }
}
