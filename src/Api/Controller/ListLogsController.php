<?php

namespace MarkHitchk\TrafficGuard\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Model\AccessLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListLogsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $params = $request->getQueryParams();
        $limit = max(1, min(200, (int) Arr::get($params, 'limit', 100)));
        $offset = max(0, (int) Arr::get($params, 'offset', 0));
        $action = trim((string) Arr::get($params, 'action', ''));
        $category = trim((string) Arr::get($params, 'category', ''));
        $search = trim((string) Arr::get($params, 'search', ''));

        $query = AccessLog::query();

        if (in_array($action, ['blocked', 'allowed'], true)) {
            $query->where('action', $action);
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('ip', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhere('path', 'like', $like)
                    ->orWhere('user_agent', 'like', $like);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->skip($offset)->take($limit)->get();

        $logs = $rows->map(function (AccessLog $log) {
            $metadata = json_decode((string) $log->metadata, true);

            return [
                'id' => (int) $log->id,
                'ip' => $log->ip,
                'action' => $log->action,
                'category' => $log->category,
                'ruleId' => $log->rule_id !== null ? (int) $log->rule_id : null,
                'reason' => $log->reason,
                'path' => $log->path,
                'userAgent' => $log->user_agent,
                'metadata' => is_array($metadata) ? $metadata : [],
                'createdAt' => $log->created_at ? $log->created_at->toIso8601String() : null,
            ];
        })->values()->all();

        return new JsonResponse([
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
