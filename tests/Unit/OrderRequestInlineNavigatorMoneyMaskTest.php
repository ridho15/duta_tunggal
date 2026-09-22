<?php

it('masks inline navigator override price as live money input', function () {
    $file = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/filament/forms/order-request-item-navigator.blade.php'
    );

    $position = strpos($file, 'data-dt-inline-unit-price');

    expect($position)->not->toBeFalse();

    $block = substr($file, max(0, $position - 300), 800);

    expect($block)
        ->toContain('inputmode="decimal"')
        ->toContain('x-mask:dynamic="$money($input, \',\', \'.\', 2)"')
        ->toContain('x-on:input.debounce.500ms=')
        ->toContain("updateInlineItem(@js(\$row['key']), 'unit_price', \$event.target.value)");
});
