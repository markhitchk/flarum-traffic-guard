<?php

namespace MarkHitchk\TrafficGuard\Console;

use Flarum\Console\AbstractCommand;
use MarkHitchk\TrafficGuard\Model\Rule;
use MarkHitchk\TrafficGuard\Support\IpMatcher;
use Symfony\Component\Console\Input\InputArgument;

class UnbanCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('traffic-guard:unban')
            ->setDescription('Delete exact IP and matching CIDR block rules for an IP address.')
            ->addArgument('ip', InputArgument::REQUIRED, 'IPv4 or IPv6 address to unblock');
    }

    protected function fire()
    {
        $ip = trim((string) $this->input->getArgument('ip'));
        if (! IpMatcher::isValidIp($ip)) {
            $this->error('Invalid IP address.');
            return 1;
        }

        $rules = Rule::where('action', 'block')->whereIn('type', ['ip', 'cidr'])->get();
        $deleted = 0;

        foreach ($rules as $rule) {
            if (IpMatcher::matches($ip, $rule->value)) {
                $rule->delete();
                $deleted++;
            }
        }

        $this->info('Removed '.$deleted.' matching block rule(s).');
        return 0;
    }
}
