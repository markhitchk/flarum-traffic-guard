<?php

namespace MarkHitchk\TrafficGuard\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;

class EnableCommand extends AbstractCommand
{
    private $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('traffic-guard:enable')
            ->setDescription('Enable Traffic Guard from the command line.');
    }

    protected function fire()
    {
        $this->settings->set('markhitchk-traffic-guard.enabled', '1');
        $this->info('Traffic Guard has been enabled.');
    }
}
