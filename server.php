<?php

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

// Handle broken pipe errors gracefully - only log if connection is still alive
$logMessage = "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n";
if (!connection_aborted()) {
    @file_put_contents('php://stdout', $logMessage);
}

require_once $publicPath.'/index.php';