<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;

class BaseSeeder extends Seeder
{
    protected function withProgressBar(int $amount, \Closure $createCollectionOfOne): Collection
    {
        $progressBar = new ProgressBar($this->command->getOutput(), $amount);
        $progressBar->start();

        $items = new Collection;

        foreach (range(1, $amount) as $index) {
            $items = $items->merge($createCollectionOfOne());
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');

        return $items;
    }
}
