<?php

it('bounds order request index supplier and item summaries to prevent overlap', function () {
    $file = file_get_contents(
        dirname(__DIR__, 2) . '/app/Filament/Resources/OrderRequestResource.php'
    );

    $supplierPosition = strpos($file, 'renderIndexSupplierSummary');
    $itemPosition = strpos($file, 'renderIndexItemSummary');

    expect($supplierPosition)->not->toBeFalse()
        ->and($itemPosition)->not->toBeFalse();

    $supplierBlock = substr($file, $supplierPosition, $itemPosition - $supplierPosition);
    $itemBlock = substr($file, $itemPosition, 2200);

    expect($supplierBlock)
        ->toContain('display:flex;flex-wrap:wrap;gap:4px;max-width:320px;overflow:hidden;')
        ->toContain('max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;')
        ->toContain('max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;');

    expect($itemBlock)
        ->toContain('width:260px;max-width:360px;overflow:hidden;')
        ->toContain('white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:360px;')
        ->not->toContain('min-width:260px;');
});
