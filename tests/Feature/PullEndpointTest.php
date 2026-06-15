<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mindtwo\Monitoring\Laravel\Http\Controllers\PullController;

test('a correctly signed request receives the snapshot', function () {
    $response = $this->withHeaders(signedHeaders())->get('/api/m2-monitoring');

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('schema_version', '1.0')
        ->assertJsonPath('project_key', 'prj_test')
        ->assertJsonPath('source.type', 'laravel')
        ->assertJsonPath('source.package', 'mindtwo/laravel-monitoring')
        ->assertJsonPath('metrics.laravel.status', 'ok')
        ->assertJsonPath('metrics.database.technology', 'sqlite')
        ->assertJsonStructure(['metrics' => ['laravel_environment' => ['drivers']], 'technologies']);
});

test('the laravel framework version is reported as a technology', function () {
    $response = $this->withHeaders(signedHeaders())->get('/api/m2-monitoring');

    $technologies = collect($response->json('technologies'))->pluck('technology');

    expect($technologies)->toContain('laravel')
        ->and($technologies)->toContain('sqlite');
});

test('unsigned requests are rejected', function () {
    $this->getJson('/api/m2-monitoring')->assertUnauthorized();
});

test('a wrong secret is rejected', function () {
    $this->withHeaders(signedHeaders(secret: 'wrong-secret'))
        ->getJson('/api/m2-monitoring')
        ->assertUnauthorized();
});

test('a wrong project key is rejected', function () {
    $this->withHeaders(signedHeaders(key: 'prj_other'))
        ->getJson('/api/m2-monitoring')
        ->assertUnauthorized();
});

test('expired signatures are rejected (replay protection)', function () {
    $this->withHeaders(signedHeaders(timestamp: time() - 301))
        ->getJson('/api/m2-monitoring')
        ->assertUnauthorized();

    $this->withHeaders(signedHeaders(timestamp: time() - 200))
        ->get('/api/m2-monitoring')
        ->assertOk();
});

test('unconfigured credentials disable the endpoint with a 503', function () {
    config()->set('monitoring.secret', null);

    $this->withHeaders(signedHeaders())
        ->getJson('/api/m2-monitoring')
        ->assertStatus(503);
});

test('requests from IPs outside the allow-list are rejected', function () {
    config()->set('monitoring.ip_allow_list', '10.0.0.0/8');

    $this->withHeaders(signedHeaders())
        ->getJson('/api/m2-monitoring')
        ->assertForbidden();
});

test('requests from allow-listed IPs pass', function () {
    config()->set('monitoring.ip_allow_list', '10.0.0.0/8, 127.0.0.1');

    $this->withHeaders(signedHeaders())
        ->get('/api/m2-monitoring')
        ->assertOk();
});

test('the endpoint is throttled', function () {
    config()->set('monitoring.route.rate_limit_per_minute', 2);

    $this->withHeaders(signedHeaders())->get('/api/m2-monitoring')->assertOk();
    $this->withHeaders(signedHeaders())->get('/api/m2-monitoring')->assertOk();
    $this->withHeaders(signedHeaders())->get('/api/m2-monitoring')->assertStatus(429);
});

test('snapshots are cached for the configured window', function () {
    config()->set('monitoring.route.cache_seconds', 300);

    expect(cache()->has(PullController::CACHE_KEY))->toBeFalse();

    $first = $this->withHeaders(signedHeaders())->get('/api/m2-monitoring');
    $second = $this->withHeaders(signedHeaders())->get('/api/m2-monitoring');

    expect(cache()->has(PullController::CACHE_KEY))->toBeTrue()
        ->and($second->json('collected_at'))->toBe($first->json('collected_at'));
});

test('caching can be disabled', function () {
    $this->withHeaders(signedHeaders())->get('/api/m2-monitoring')->assertOk();

    expect(cache()->has(PullController::CACHE_KEY))->toBeFalse();
});

test('the route is registered under its configurable name', function () {
    expect(Route::has('monitoring.pull'))->toBeTrue();
});
