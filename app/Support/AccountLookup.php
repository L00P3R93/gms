<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Support\Collection;

class AccountLookup
{
    private static ?Collection $accounts = null;

    public static function flush(): void
    {
        self::$accounts = null;
    }

    private static function all(): Collection
    {
        return self::$accounts ??= Account::select(['id', 'name', 'phone'])->get()->keyBy('id');
    }

    public static function name(string|int $id): string
    {
        if (empty($id)) {
            return '—';
        }

        return self::all()[(int) $id]?->name ?? "ID:{$id}";
    }

    public static function maskedPhone(string|int $id): string
    {
        if (empty($id)) {
            return '—';
        }

        $phone = self::all()[(int) $id]?->phone;

        return $phone ? '****'.substr($phone, -4) : '—';
    }
}
