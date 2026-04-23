<?php

/*
|--------------------------------------------------------------------------
| Silence PHP 8.4+ vendor deprecation notices
|--------------------------------------------------------------------------
|
| Several upstream packages (Laravel 10 core, Symfony, Carbon, Google API
| client, vlucas/phpdotenv, nunomaduro/termwind, lab404/laravel-impersonate,
| maatwebsite/excel, monolog, etc.) still use the pre-8.4 "implicit
| nullable" parameter style and trigger harmless E_DEPRECATED notices
| whenever those classes are loaded under PHP 8.4+.
|
| Two layers of protection are needed because Laravel's own
| `HandleExceptions` bootstrap later calls `error_reporting(-1)`, which
| re-enables deprecation reporting for anything that gets autoloaded after
| the framework boots (Monolog, Illuminate\Log\Logger, etc.).
|
| 1. Lower the reporting level so deprecations aren't reported at all when
|    they fire during Composer autoload (before Laravel boots).
| 2. Install a pre-framework error handler that silently swallows
|    E_DEPRECATED / E_USER_DEPRECATED even if something later bumps the
|    reporting level back up. Laravel will overwrite this handler with its
|    own during bootstrap — that's fine, its own handler routes
|    deprecations to the `null` log channel by default.
|
| Keep this BEFORE the Composer autoloader require — autoload-time
| deprecations leak otherwise.
*/
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('log_errors', '0');  // PHP 8.4+: stop PHP from writing compile-time
                              // deprecations to stderr even after Laravel's
                              // HandleExceptions bootstrap resets the
                              // reporting level back to -1.
set_error_handler(static function (int $severity): bool {
    // Swallow deprecation notices, let everything else fall through to
    // PHP's default handler (by returning false).
    return $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED;
});

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
