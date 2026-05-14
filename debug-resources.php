<?php

use Illuminate\Filesystem\Filesystem;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->boot();

$fs = new Filesystem;
$dir = app_path('Filament/Resources');
$namespace = 'App\\Filament\\Resources';

foreach ($fs->allFiles($dir) as $file) {
    $rp = $file->getRelativePathname();
    $class = $namespace.'\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $rp);

    $exists = class_exists($class);
    $isResource = $exists && is_subclass_of($class, Filament\Resources\Resource::class);

    echo ($isResource ? '[RESOURCE] ' : '[skip]    ').$class."\n";
}
