<?php

use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;
use MarkHitchk\TrafficGuard\Api\Controller\ClearLogsController;
use MarkHitchk\TrafficGuard\Api\Controller\CreateRuleController;
use MarkHitchk\TrafficGuard\Api\Controller\DeleteRuleController;
use MarkHitchk\TrafficGuard\Api\Controller\ListLogsController;
use MarkHitchk\TrafficGuard\Api\Controller\ListRulesController;
use MarkHitchk\TrafficGuard\Api\Controller\PurgeCacheController;
use MarkHitchk\TrafficGuard\Api\Controller\TestIpController;
use MarkHitchk\TrafficGuard\Api\Controller\UpdateRuleController;
use MarkHitchk\TrafficGuard\Console\DisableCommand;
use MarkHitchk\TrafficGuard\Console\EnableCommand;
use MarkHitchk\TrafficGuard\Console\PruneCommand;
use MarkHitchk\TrafficGuard\Console\StatusCommand;
use MarkHitchk\TrafficGuard\Console\UnbanCommand;
use MarkHitchk\TrafficGuard\Middleware\AdminTrafficGuardMiddleware;
use MarkHitchk\TrafficGuard\Middleware\ApiTrafficGuardMiddleware;
use MarkHitchk\TrafficGuard\Middleware\ForumTrafficGuardMiddleware;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    (new Extend\Middleware('forum'))
        ->add(ForumTrafficGuardMiddleware::class),

    (new Extend\Middleware('api'))
        ->add(ApiTrafficGuardMiddleware::class),

    (new Extend\Middleware('admin'))
        ->add(AdminTrafficGuardMiddleware::class),

    (new Extend\Routes('api'))
        ->get('/traffic-guard/rules', 'markhitchk.traffic-guard.rules.index', ListRulesController::class)
        ->post('/traffic-guard/rules', 'markhitchk.traffic-guard.rules.create', CreateRuleController::class)
        ->patch('/traffic-guard/rules/{id}', 'markhitchk.traffic-guard.rules.update', UpdateRuleController::class)
        ->delete('/traffic-guard/rules/{id}', 'markhitchk.traffic-guard.rules.delete', DeleteRuleController::class)
        ->get('/traffic-guard/logs', 'markhitchk.traffic-guard.logs.index', ListLogsController::class)
        ->delete('/traffic-guard/logs', 'markhitchk.traffic-guard.logs.clear', ClearLogsController::class)
        ->post('/traffic-guard/test-ip', 'markhitchk.traffic-guard.test-ip', TestIpController::class)
        ->delete('/traffic-guard/cache', 'markhitchk.traffic-guard.cache.clear', PurgeCacheController::class),

    (new Extend\Console())
        ->command(DisableCommand::class)
        ->command(EnableCommand::class)
        ->command(UnbanCommand::class)
        ->command(StatusCommand::class)
        ->command(PruneCommand::class)
        ->schedule('traffic-guard:prune', function (Event $event) {
            $event->daily();
        }),
];
