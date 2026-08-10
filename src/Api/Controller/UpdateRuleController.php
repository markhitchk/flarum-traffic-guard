<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Model\Rule;
use MarkHitchk\TrafficGuard\Support\RulePresenter;
use MarkHitchk\TrafficGuard\Support\RuleValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UpdateRuleController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $rule = Rule::find($id);
        if (! $rule) {
            return new JsonResponse(['error' => 'Rule not found.'], 404);
        }

        try {
            $data = RuleValidator::normalize((array) ($request->getParsedBody() ?: []), $rule);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $data['updated_at'] = Carbon::now();
        $rule->fill($data);
        $rule->save();

        return new JsonResponse(['rule' => RulePresenter::present($rule)]);
    }
}
