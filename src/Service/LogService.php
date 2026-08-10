<?php

namespace MarkHitchk\TrafficGuard\Service;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use MarkHitchk\TrafficGuard\Model\AccessLog;

class LogService
{
    private $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function record($ip, $path, $userAgent, Decision $decision)
    {
        if ($decision->blocked && ! $this->enabled('log_blocks')) {
            return;
        }

        if (! $decision->blocked && ! $this->enabled('log_allowed')) {
            return;
        }

        $metadata = $decision->threat;
        if ($decision->providerError) {
            $metadata['provider_error'] = $decision->providerError;
        }

        AccessLog::create([
            'ip' => $this->formatIp($ip),
            'action' => $decision->blocked ? 'blocked' : 'allowed',
            'category' => $decision->category,
            'rule_id' => $decision->ruleId,
            'reason' => $decision->reason,
            'path' => mb_substr((string) $path, 0, 2048),
            'user_agent' => mb_substr((string) $userAgent, 0, 1024),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'created_at' => Carbon::now(),
        ]);
    }

    public function prune()
    {
        $days = max(1, min(3650, (int) $this->get('log_retention_days', '30')));

        return AccessLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();
    }

    private function formatIp($ip)
    {
        $mode = (string) $this->get('log_ip_mode', 'full');

        if ($mode === 'hashed') {
            return 'sha256:'.hash('sha256', (string) $ip);
        }

        if ($mode === 'masked') {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = explode('.', $ip);
                $parts[3] = '0';
                return implode('.', $parts).'/24';
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $packed = inet_pton($ip);
                if ($packed !== false) {
                    for ($i = 8; $i < 16; $i++) {
                        $packed[$i] = "\0";
                    }
                    return inet_ntop($packed).'/64';
                }
            }
        }

        return (string) $ip;
    }

    private function enabled($name)
    {
        return $this->get($name, '0') === '1';
    }

    private function get($name, $default = null)
    {
        return $this->settings->get('markhitchk-traffic-guard.'.$name, $default);
    }
}
