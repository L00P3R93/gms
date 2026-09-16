<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempStoragePath = storage_path('framework/testing/clear-logs-'.uniqid());
    File::ensureDirectoryExists($this->tempStoragePath.'/logs');
    $this->app->useStoragePath($this->tempStoragePath);
});

afterEach(function () {
    File::deleteDirectory($this->tempStoragePath);
});

function putTestLog(string $name, string $content = 'log contents'): string
{
    $path = storage_path("logs/{$name}");
    File::put($path, $content);

    return $path;
}

it('fails when no names and no --all are given', function () {
    $this->artisan('logs:clear')->assertFailed();
});

it('clears every application log with --all', function () {
    $laravel = putTestLog('laravel.log', 'laravel content');
    $mpesa = putTestLog('mpesa.log', 'mpesa content');

    $this->artisan('logs:clear', ['--all' => true])->assertSuccessful();

    expect(File::get($laravel))->toBe('');
    expect(File::get($mpesa))->toBe('');
});

it('clears only the named logs, including rotated files', function () {
    $laravel = putTestLog('laravel.log', 'laravel content');
    $rotated = putTestLog('laravel-2024-01-01.log', 'rotated content');
    $mpesa = putTestLog('mpesa.log', 'mpesa content');

    $this->artisan('logs:clear', ['names' => ['laravel']])->assertSuccessful();

    expect(File::get($laravel))->toBe('');
    expect(File::get($rotated))->toBe('');
    expect(File::get($mpesa))->toBe('mpesa content');
});

it('leaves files untouched on a dry run', function () {
    $laravel = putTestLog('laravel.log', 'laravel content');

    $this->artisan('logs:clear', ['--all' => true, '--dry-run' => true])->assertSuccessful();

    expect(File::get($laravel))->toBe('laravel content');
});

it('does not clear logs when confirmation is declined in production', function () {
    $this->app['env'] = 'production';
    $laravel = putTestLog('laravel.log', 'laravel content');

    $this->artisan('logs:clear', ['--all' => true])
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    expect(File::get($laravel))->toBe('laravel content');
});

it('skips the confirmation prompt in production with --force', function () {
    $this->app['env'] = 'production';
    $laravel = putTestLog('laravel.log', 'laravel content');

    $this->artisan('logs:clear', ['--all' => true, '--force' => true])->assertSuccessful();

    expect(File::get($laravel))->toBe('');
});
