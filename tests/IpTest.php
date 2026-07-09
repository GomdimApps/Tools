<?php

use GomdimApps\Tools\Ip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns fail/local_ip for local or invalid IPs', function () {
    expect(Ip::getDetails('127.0.0.1'))->toBe([
        'status' => 'fail',
        'message' => 'local_ip',
    ]);

    expect(Ip::getDetails(null))->toBeNull();
});

it('resolves details from the primary API', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'query' => '8.8.8.8',
            'country' => 'United States',
        ], 200),
    ]);

    $details = Ip::getDetails('8.8.8.8');

    expect($details['status'])->toBe('success')
        ->and($details['country'])->toBe('United States');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'ip-api.com'));
});

it('falls back to the secondary API when the primary one fails', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([], 500),
        'ipapi.co/*' => Http::response([
            'ip' => '1.1.1.1',
            'country_name' => 'Australia',
            'country_code' => 'AU',
        ], 200),
    ]);

    $details = Ip::getDetails('1.1.1.1');

    expect($details['status'])->toBe('success')
        ->and($details['country'])->toBe('Australia')
        ->and($details['countryCode'])->toBe('AU');
});

it('returns a fetch_error when both APIs fail', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([], 500),
        'ipapi.co/*' => Http::response([], 500),
    ]);

    expect(Ip::getDetails('9.9.9.9'))->toBe([
        'status' => 'fail',
        'message' => 'fetch_error',
    ]);
});

it('caches the resolved details', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'success', 'query' => '8.8.4.4'], 200),
    ]);

    Ip::getDetails('8.8.4.4');

    expect(Cache::has('ip_details:8.8.4.4'))->toBeTrue();
});
