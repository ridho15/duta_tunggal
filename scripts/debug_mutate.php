<?php
require __DIR__.'/../vendor/autoload.php';

use App\Filament\Resources\DepositResource\Pages\CreateDeposit;

try {
    $instance = new CreateDeposit();
    $result = $instance->mutateFormDataBeforeCreate([]);
    echo "OK\n";
    var_export($result);
} catch (Throwable $e) {
    echo 'EX: '.$e->getMessage().PHP_EOL;
    echo $e->getTraceAsString();
}
