<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Laravel\Tests\TestCase;
use Mindtwo\Monitoring\Transport\HmacRequestSigner;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Signed headers for a pull request, exactly as the central dashboard would
 * send them.
 *
 * @return array<string, string>
 */
function signedHeaders(
    string $payload = '',
    ?int $timestamp = null,
    string $key = 'prj_test',
    string $secret = 'test-secret'
): array {
    $signer = new HmacRequestSigner($timestamp !== null ? static fn (): int => $timestamp : null);

    return $signer->headers($payload, new Credentials($key, $secret));
}
