<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Contracts\SignatureVerifier;
use Mindtwo\Monitoring\Transport\HmacRequestSigner;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates pull requests: the client signs "{timestamp}.{body}" with the
 * shared secret; only the project key, timestamp and signature travel on the
 * wire. Unconfigured credentials disable the endpoint entirely.
 */
final class VerifySignature
{
    public function __construct(
        private ConfigurationRepository $config,
        private SignatureVerifier $verifier
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $credentials = $this->config->credentials();

        if (! $credentials->isComplete()) {
            return response()->json(['message' => 'Monitoring is not configured.'], 503);
        }

        $headers = [
            HmacRequestSigner::HEADER_KEY => (string) $request->header(HmacRequestSigner::HEADER_KEY, ''),
            HmacRequestSigner::HEADER_TIMESTAMP => (string) $request->header(HmacRequestSigner::HEADER_TIMESTAMP, ''),
            HmacRequestSigner::HEADER_SIGNATURE => (string) $request->header(HmacRequestSigner::HEADER_SIGNATURE, ''),
        ];

        if (! $this->verifier->verify((string) $request->getContent(), $headers, $credentials)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
