<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Model\Rule;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteRuleController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $rule = Rule::find($id);
        if (! $rule) {
            return new JsonResponse(['error' => 'Rule not found.'], 404);
        }

        $rule->delete();

        return new EmptyResponse(204);
    }
}
