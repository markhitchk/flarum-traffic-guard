<?php

namespace MarkHitchk\TrafficGuard\Middleware;

class AdminTrafficGuardMiddleware extends AbstractTrafficGuardMiddleware
{
    protected function scope()
    {
        return 'admin';
    }
}
