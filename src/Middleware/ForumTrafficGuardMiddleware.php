<?php

namespace MarkHitchk\TrafficGuard\Middleware;

class ForumTrafficGuardMiddleware extends AbstractTrafficGuardMiddleware
{
    protected function scope()
    {
        return 'forum';
    }
}
