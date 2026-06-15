<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mindtwo\Monitoring\Contracts\Transport;
use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Monitor;
use Mindtwo\Monitoring\Transport\HmacSignatureVerifier;

test('push delivers a signed snapshot to the configured endpoint', function () {
    Http::fake(['monitoring.mindtwo.com/*' => Http::response(['status' => 'received'], 200)]);

    $result = app(Monitor::class)->push();

    expect($result->success)->toBeTrue()
        ->and($result->statusCode)->toBe(200);

    Http::assertSent(function (Request $request): bool {
        $verifier = new HmacSignatureVerifier;

        return $request->url() === 'https://monitoring.mindtwo.com/api/monitoring'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Monitoring-Key', 'prj_test')
            && $request->hasHeader('X-Monitoring-Timestamp')
            && $request->hasHeader('X-Monitoring-Signature')
            && $request->header('Content-Type')[0] === 'application/json'
            && json_decode($request->body(), true)['schema_version'] === '1.0'
            && $verifier->verify($request->body(), [
                'X-Monitoring-Key' => $request->header('X-Monitoring-Key')[0],
                'X-Monitoring-Timestamp' => $request->header('X-Monitoring-Timestamp')[0],
                'X-Monitoring-Signature' => $request->header('X-Monitoring-Signature')[0],
            ], new Credentials('prj_test', 'test-secret'));
    });
});

test('a custom endpoint from the configuration is used', function () {
    config()->set('monitoring.endpoint', 'https://staging-monitoring.example.com/ingest');

    Http::fake(['staging-monitoring.example.com/*' => Http::response('', 202)]);

    $result = app(Monitor::class)->push();

    expect($result->success)->toBeTrue()
        ->and($result->statusCode)->toBe(202);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://staging-monitoring.example.com/ingest');
});

test('server errors are reported with status code and excerpt', function () {
    Http::fake(['*' => Http::response('ingest exploded', 503)]);

    $result = app(Monitor::class)->push();

    expect($result->success)->toBeFalse()
        ->and($result->statusCode)->toBe(503)
        ->and($result->error)->toContain('HTTP 503')
        ->and($result->error)->toContain('ingest exploded');
});

test('a redirecting endpoint is reported as a failure and never silently followed', function () {
    Http::fake([
        'monitoring.mindtwo.com/api/monitoring' => Http::response('', 301, [
            'Location' => 'https://monitoring.mindtwo.com/api/monitoring/',
        ]),
    ]);

    $result = app(Monitor::class)->push();

    expect($result->success)->toBeFalse()
        ->and($result->statusCode)->toBe(301);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST');
});

test('connection failures are reported without throwing', function () {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('Connection refused'));

    $result = app(Monitor::class)->push();

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Connection refused');
});

test('missing credentials abort the push before any request', function () {
    config()->set('monitoring.project_key', null);

    Http::fake();

    $result = app(Transport::class)->send(app(Monitor::class)->snapshot());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('MONITORING_PROJECT_KEY');

    Http::assertNothingSent();
});
