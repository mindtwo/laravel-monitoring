<?php

declare(strict_types=1);

test('all source files declare strict types')
    ->expect('Mindtwo\Monitoring\Laravel')
    ->toUseStrictTypes();

test('no debug or dangerous shell helpers are used')
    ->expect('Mindtwo\Monitoring\Laravel')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen']);

test('http layer classes are final')
    ->expect('Mindtwo\Monitoring\Laravel\Http')
    ->toBeFinal();
