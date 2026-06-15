<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Contracts\ConfigurationRepository;

test('credentials are read and trimmed from config', function () {
    config()->set('monitoring.project_key', '  prj_test  ');
    config()->set('monitoring.secret', " test-secret \n");

    $credentials = app(ConfigurationRepository::class)->credentials();

    expect($credentials->projectKey)->toBe('prj_test')
        ->and($credentials->secret)->toBe('test-secret')
        ->and($credentials->isComplete())->toBeTrue();
});

test('missing credentials are incomplete', function () {
    config()->set('monitoring.project_key', null);

    expect(app(ConfigurationRepository::class)->credentials()->isComplete())->toBeFalse();
});

test('the endpoint falls back to the central default when emptied', function () {
    config()->set('monitoring.endpoint', '');

    expect(app(ConfigurationRepository::class)->endpoint())
        ->toBe('https://monitoring.mindtwo.com/api/monitoring');
});

test('the ip allow-list accepts comma-separated strings and arrays', function () {
    $repository = app(ConfigurationRepository::class);

    config()->set('monitoring.ip_allow_list', ' 10.0.0.0/8 , 192.168.1.1 ,, ');
    expect($repository->ipAllowList())->toBe(['10.0.0.0/8', '192.168.1.1']);

    config()->set('monitoring.ip_allow_list', ['::1', '']);
    expect($repository->ipAllowList())->toBe(['::1']);

    config()->set('monitoring.ip_allow_list', null);
    expect($repository->ipAllowList())->toBe([]);
});

test('get() reads from the monitoring namespace with defaults', function () {
    expect(app(ConfigurationRepository::class)->get('timeout'))->toBe(15)
        ->and(app(ConfigurationRepository::class)->get('does-not-exist', 'fallback'))->toBe('fallback');
});
