<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Support\IpMatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional IP allow-list (plain IPs and CIDR ranges) in front of the pull
 * endpoint. An empty list disables the check; authentication stays enforced
 * by VerifySignature regardless. Behind a proxy, configure Laravel's trusted
 * proxies so the client IP is resolved correctly.
 */
final class EnsureIpIsAllowed
{
    public function __construct(private ConfigurationRepository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! IpMatcher::allows((string) $request->ip(), $this->config->ipAllowList())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
