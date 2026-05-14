<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->boot();
$panel = Filament\Facades\Filament::getPanel('admin');
print_r($panel->getPages());
