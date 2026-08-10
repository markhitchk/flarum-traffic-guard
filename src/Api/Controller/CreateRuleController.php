<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use InvalidArgumentException;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Model\Rule;
use MarkHitchk\TrafficGuard\Support\RulePresenter;
use MarkHitchk\TrafficGuard\Support\RuleValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CreateRuleController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        try {
            $data = RuleValidator::normalize((array) ($request->getParsedBody() ?: []));
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $data['created_by'] = $actor->id;
        $data['created_at'] = Carbon::now();
        $data['updated_at'] = Carbon::now();

        $rule = Rule::create($data);

        return new JsonResponse(['rule' => RulePresenter::present($rule)], 201);
    }
}
