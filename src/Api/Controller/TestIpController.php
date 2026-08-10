<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Service\TrafficGuardService;
use MarkHitchk\TrafficGuard\Support\IpMatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TestIpController implements RequestHandlerInterface
{
    private $guard;

    public function __construct(TrafficGuardService $guard)
    {
        $this->guard = $guard;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = (array) ($request->getParsedBody() ?: []);
        $ip = trim((string) ($body['ip'] ?? ''));
        $path = trim((string) ($body['path'] ?? '/')) ?: '/';
        $userAgent = trim((string) ($body['userAgent'] ?? 'Traffic Guard test'));

        if (! IpMatcher::isValidIp($ip)) {
            return new JsonResponse(['error' => 'Enter a valid IPv4 or IPv6 address.'], 422);
        }

        $decision = $this->guard->inspect($ip, $path, $userAgent, true);

        return new JsonResponse(['result' => $decision->toArray()]);
    }
}
