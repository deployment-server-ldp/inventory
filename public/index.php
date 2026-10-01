<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Friendly messages for the two most common installation mistakes (instead of a blank 500 page).
if (! file_exists(__DIR__.'/../vendor/autoload.php') || ! file_exists(__DIR__.'/../.env')) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    $missing = ! file_exists(__DIR__.'/../vendor/autoload.php')
        ? 'The <code>vendor/</code> folder is missing. Upload the release ZIP that includes vendor/ (a GitHub "Download ZIP" does not), or run <code>composer install --no-dev</code>.'
        : 'The <code>.env</code> file is missing. Copy <code>.env.example</code> to <code>.env</code> and fill in the database details and APP_KEY.';
    exit('<!doctype html><title>SPIMS setup incomplete</title><div style="font-family:sans-serif;max-width:640px;margin:60px auto;line-height:1.5">'
        .'<h2>SPIMS installation incomplete</h2><p>'.$missing.'</p><p>Do not move files out of the <code>public/</code> folder — keep the project structure as it is; the root <code>.htaccess</code> handles routing.</p>'
        .'<p>See <code>docs/INSTALLATION_HOSTINGER.md</code>.</p></div>');
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
