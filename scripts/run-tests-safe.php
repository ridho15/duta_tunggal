<?php

$args = array_slice($_SERVER['argv'], 1);

$run = function (array $command): int {
    $escaped = array_map('escapeshellarg', $command);
    passthru(implode(' ', $escaped), $exitCode);

    return (int) $exitCode;
};

$clearExitCode = $run([PHP_BINARY, 'artisan', 'config:clear', '--ansi']);

if ($clearExitCode !== 0) {
    exit($clearExitCode);
}

$testCommand = array_merge([PHP_BINARY, 'artisan', 'test'], $args);

exit($run($testCommand));
