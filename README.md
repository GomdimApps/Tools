# GomdimApps Tools

[![Tests](https://github.com/GomdimApps/Tools/actions/workflows/tests.yml/badge.svg)](https://github.com/GomdimApps/Tools/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/gomdimapps/tools.svg)](https://packagist.org/packages/gomdimapps/tools)
[![License](https://img.shields.io/packagist/l/gomdimapps/tools.svg)](LICENSE)

A wrapper of utility tools for Laravel applications, built on top of Laravel's own components (`illuminate/http`, `illuminate/cache`, `illuminate/support`) instead of reinventing them.

## Requirements

- PHP 8.3, 8.4 or 8.5
- Laravel 12 or 13 (Laravel 10 and 11 are past their security-support window and are no longer supported; Laravel 13 support is best-effort until `pestphp/pest-plugin-laravel` adds support for it)

## Installation

```bash
composer require gomdimapps/tools
```

The `ToolsServiceProvider` is auto-discovered by Laravel. If you want to customize the defaults, publish the config file:

```bash
php artisan vendor:publish --tag=tools-config
```

## `RequestCall`

A fluent wrapper around Laravel's `Http` facade to centralize HTTP calls.

```php
use GomdimApps\Tools\RequestCall;

$call = RequestCall::make('https://api.example.com/users', 'POST')
    ->asJson()
    ->withToken($token)
    ->withData(['name' => 'Jane'])
    ->execute();

if ($call->isSuccessful()) {
    $user = $call->json();
}

// Full request/response/error snapshot, handy for logging
$debugData = $call->captureData();
```

Available methods include `withHeaders()`, `withUserAgent()`, `withCookies()`, `withProxy()`, `withoutVerifying()`, `withBasicAuth()`, `asForm()`, `acceptJson()`, `timeout()`, `withQuery()`, `buffer()` and `stream()`.

### Using a proxy

`withProxy()` accepts a single proxy URL or an array to split proxies per protocol:

```php
use GomdimApps\Tools\RequestCall;

// Single proxy for all protocols
$call = RequestCall::make('https://api.example.com/users')
    ->withProxy('http://user:pass@proxy.example.com:8080')
    ->execute();

// Different proxies per protocol, plus a bypass list ("no")
$call = RequestCall::make('https://api.example.com/users')
    ->withProxy([
        'http' => 'http://proxy.example.com:8080',
        'https' => 'http://proxy.example.com:8080',
        'no' => ['localhost', '127.0.0.1'],
    ])
    ->withoutVerifying()
    ->execute();
```

## `Ip`

Resolves geolocation details for an IP address, with an automatic fallback provider and response caching.

```php
use GomdimApps\Tools\Ip;

$details = Ip::getDetails('8.8.8.8');
// ['status' => 'success', 'country' => 'United States', ...]
```

You can also resolve the details for the current request straight from `RequestCall`:

```php
use GomdimApps\Tools\RequestCall;

$details = RequestCall::getIp();
```

## Configuration

`config/tools.php`:

```php
return [
    'http' => [
        'timeout' => env('TOOLS_HTTP_TIMEOUT', 30),
    ],
    'ip' => [
        'cache_ttl' => env('TOOLS_IP_CACHE_TTL', 86400),
    ],
];
```

## Testing

```bash
composer install
vendor/bin/pest
```

If you don't have PHP/Composer installed locally, run the test suite in Docker instead, isolated per PHP version (8.3, 8.4 and 8.5):

```bash
make test-8.3
make test-8.4
make test-8.5

# runs all three, one after another
make test-all
```

Each service builds its own image, installs dependencies and runs `vendor/bin/pest` inside the container.

## License

The MIT License (MIT). Please see the [LICENSE](LICENSE) file for more information.
