<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;

#[Signature('logs:clear {names?* : Log basenames to clear, e.g. laravel mpesa} {--all : Clear every application log} {--dry-run : List matched files without changing them} {--force : Skip the confirmation prompt}')]
#[Description('Truncate application log files without deleting them')]
class ClearLogs extends Command
{
    use ConfirmableTrait;

    /**
     * Log basenames considered application logs when --all is used.
     *
     * @var array<int, string>
     */
    private const ALL_LOG_NAMES = ['laravel', 'mpesa'];

    public function handle(): int
    {
        /** @var array<int, string> $names */
        $names = $this->argument('names');

        if ($names === [] && ! $this->option('all')) {
            $this->error('Specify one or more log names to clear, or use --all to clear every application log.');

            return self::FAILURE;
        }

        $names = $this->option('all') ? self::ALL_LOG_NAMES : $names;

        $files = $this->matchingFiles($names);

        if ($files === []) {
            $this->info('No matching log files found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['File', 'Size'], collect($files)
                ->map(fn (string $file): array => [$file, Number::fileSize(filesize($file))])
                ->all());

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        foreach ($files as $file) {
            File::put($file, '');
            $this->line("Cleared {$file}");
        }

        $this->info(sprintf('Cleared %d log file(s).', count($files)));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function matchingFiles(array $names): array
    {
        $files = [];

        foreach ($names as $name) {
            $files = array_merge(
                $files,
                File::glob(storage_path("logs/{$name}.log")),
                File::glob(storage_path("logs/{$name}-*.log")),
            );
        }

        return array_values(array_unique($files));
    }
}
