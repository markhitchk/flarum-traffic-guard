<?php

namespace MarkHitchk\TrafficGuard\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use MarkHitchk\TrafficGuard\Model\AccessLog;
use MarkHitchk\TrafficGuard\Model\Rule;

class StatusCommand extends AbstractCommand
{
    private $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('traffic-guard:status')
            ->setDescription('Show Traffic Guard status and rule counts.');
    }

    protected function fire()
    {
        $enabled = $this->settings->get('markhitchk-traffic-guard.enabled', '0') === '1';
        $provider = $this->settings->get('markhitchk-traffic-guard.provider', 'none');

        $this->info('Traffic Guard: '.($enabled ? 'ENABLED' : 'DISABLED'));
        $this->info('Provider: '.$provider);
        $this->info('Rules: '.Rule::count().' total, '.Rule::where('enabled', true)->count().' enabled');
        $this->info('Logs: '.AccessLog::count());
    }
}
