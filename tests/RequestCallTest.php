<?php

use GomdimApps\Tools\RequestCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('performs a successful GET request', function () {
    Http::fake([
        'example.com/users*' => Http::response(['id' => 1, 'name' => 'Jane'], 200),
    ]);

    $call = RequestCall::make('https://example.com/users')
        ->withQuery(['page' => 1])
        ->execute();

    expect($call->isSuccessful())->toBeTrue()
        ->and($call->status())->toBe(200)
        ->and($call->json('name'))->toBe('Jane');

    Http::assertSent(fn ($request) => $request->url() === 'https://example.com/users?page=1');
});

it('performs a POST request with a JSON payload', function () {
    Http::fake([
        'example.com/users' => Http::response(['id' => 2], 201),
    ]);

    $call = RequestCall::make('https://example.com/users', 'POST')
        ->asJson()
        ->withData(['name' => 'John'])
        ->execute();

    expect($call->status())->toBe(201);

    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['name'] === 'John');
});

it('tracks headers, token and cookies for captureData', function () {
    Http::fake([
        'example.com/*' => Http::response([], 200),
    ]);

    $call = RequestCall::make('https://example.com/profile')
        ->withHeaders(['X-Custom' => 'value'])
        ->withToken('secret-token')
        ->execute();

    $captured = $call->captureData();

    expect($captured['request']['headers'])->toHaveKey('X-Custom', 'value')
        ->and($captured['request']['headers'])->toHaveKey('Authorization', 'Bearer secret-token')
        ->and($captured['response']['status'])->toBe(200);
});

it('captures connection errors instead of throwing', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not connect');
    });

    $call = RequestCall::make('https://unreachable.example.com')->execute();

    expect($call->getResponse())->toBeNull()
        ->and($call->getError())->toBeInstanceOf(ConnectionException::class)
        ->and($call->isSuccessful())->toBeFalse();
});
