<?php

namespace MarkHitchk\TrafficGuard\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;

class DisableCommand extends AbstractCommand
{
    private $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('traffic-guard:disable')
            ->setDescription('Emergency-disable Traffic Guard without using the web admin.');
    }

    protected function fire()
    {
        $this->settings->set('markhitchk-traffic-guard.enabled', '0');
        $this->info('Traffic Guard has been disabled.');
    }
}
