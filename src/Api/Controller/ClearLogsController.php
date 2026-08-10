<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Model\AccessLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClearLogsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $deleted = AccessLog::query()->delete();

        return new JsonResponse(['deleted' => $deleted]);
    }
}
