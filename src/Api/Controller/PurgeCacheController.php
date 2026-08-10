<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Service\ThreatLookupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PurgeCacheController implements RequestHandlerInterface
{
    private $lookup;

    public function __construct(ThreatLookupService $lookup)
    {
        $this->lookup = $lookup;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return new JsonResponse(['deleted' => $this->lookup->purgeAll()]);
    }
}
