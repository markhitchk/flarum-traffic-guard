<?php

namespace MarkHitchk\TrafficGuard\Middleware;

class ApiTrafficGuardMiddleware extends AbstractTrafficGuardMiddleware
{
    protected function scope()
    {
        return 'api';
    }
}
