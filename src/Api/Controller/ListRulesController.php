<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Model\Rule;
use MarkHitchk\TrafficGuard\Support\RulePresenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListRulesController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $rules = Rule::query()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->map(function (Rule $rule) {
                return RulePresenter::present($rule);
            })
            ->values()
            ->all();

        return new JsonResponse(['rules' => $rules]);
    }
}
