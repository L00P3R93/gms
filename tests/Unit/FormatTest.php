<?php

use App\Support\Format;

it('abbreviates thousands and millions and keeps the sign of negatives', function (int $number, string $expected): void {
    expect(Format::formatNumber($number))->toBe($expected);
})->with([
    'small' => [999, '999'],
    'thousands' => [5000, '5.00k'],
    'millions' => [1851147, '1.85M'],
    'negative small' => [-500, '-500'],
    'negative thousands' => [-6578, '-6.58k'],
    'negative millions' => [-2372596, '-2.37M'],
]);

it('shows a dash for empty values and whole numbers otherwise in compact', function (): void {
    expect(Format::compact(null))->toBe('—')
        ->and(Format::compact(''))->toBe('—')
        ->and(Format::compact(0))->toBe('—')
        ->and(Format::compact(122510.75))->toBe('122.51k')
        ->and(Format::compact('2500'))->toBe('2.50k')
        ->and(Format::compactMoney(1851147.35))->toBe('KES 1.85M')
        ->and(Format::compactMoney(null))->toBe('KES —');
});
