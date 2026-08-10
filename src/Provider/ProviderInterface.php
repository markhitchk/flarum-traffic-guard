<?php

namespace MarkHitchk\TrafficGuard\Provider;

interface ProviderInterface
{
    /**
     * @return array<string,mixed>
     */
    public function lookup($ip);
}
