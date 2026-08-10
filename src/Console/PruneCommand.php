<?php

namespace MarkHitchk\TrafficGuard\Console;

use Flarum\Console\AbstractCommand;
use MarkHitchk\TrafficGuard\Service\LogService;
use MarkHitchk\TrafficGuard\Service\ThreatLookupService;

class PruneCommand extends AbstractCommand
{
    private $logs;
    private $lookup;

    public function __construct(LogService $logs, ThreatLookupService $lookup)
    {
        $this->logs = $logs;
        $this->lookup = $lookup;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('traffic-guard:prune')
            ->setDescription('Delete expired Traffic Guard logs and threat cache rows.');
    }

    protected function fire()
    {
        $logs = $this->logs->prune();
        $cache = $this->lookup->purgeExpired();

        $this->info('Pruned '.$logs.' log row(s) and '.$cache.' expired cache row(s).');
    }
}
