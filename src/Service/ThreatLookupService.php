<?php

namespace MarkHitchk\TrafficGuard\Service;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use MarkHitchk\TrafficGuard\Model\IpCache;
use MarkHitchk\TrafficGuard\Provider\ProxyCheckProvider;
use MarkHitchk\TrafficGuard\Support\IpMatcher;
use RuntimeException;

class ThreatLookupService
{
    private $settings;
    private $proxyCheck;

    public function __construct(SettingsRepositoryInterface $settings, ProxyCheckProvider $proxyCheck)
    {
        $this->settings = $settings;
        $this->proxyCheck = $proxyCheck;
    }

    public function lookup($ip, $force = false)
    {
        if (! IpMatcher::isValidIp($ip)) {
            throw new RuntimeException('Invalid IP address.');
        }

        if (! IpMatcher::isPublicIp($ip)) {
            return $this->emptyResult('private');
        }

        if (! $force && $this->enabled('cache_enabled')) {
            $cached = IpCache::where('ip', $ip)->first();
            if ($cached && $cached->expires_at && $cached->expires_at->isFuture()) {
                $payload = json_decode($cached->payload, true);
                if (is_array($payload)) {
                    $payload['cached'] = true;
                    return $payload;
                }
            }
        }

        $provider = (string) $this->get('provider', 'none');
        if ($provider === 'none') {
            return $this->emptyResult('none');
        }

        if ($provider !== 'proxycheck') {
            throw new RuntimeException('Unsupported threat provider: '.$provider);
        }

        $result = $this->proxyCheck->lookup($ip);
        $result['cached'] = false;

        if ($this->enabled('cache_enabled')) {
            $hours = max(1, min(720, (int) $this->get('cache_hours', '24')));

            IpCache::updateOrCreate(
                ['ip' => $ip],
                [
                    'payload' => json_encode($result, JSON_UNESCAPED_SLASHES),
                    'expires_at' => Carbon::now()->addHours($hours),
                    'updated_at' => Carbon::now(),
                ]
            );
        }

        return $result;
    }

    public function purgeExpired()
    {
        return IpCache::where('expires_at', '<=', Carbon::now())->delete();
    }

    public function purgeAll()
    {
        return IpCache::query()->delete();
    }

    private function emptyResult($provider)
    {
        return [
            'provider' => $provider,
            'proxy' => false,
            'vpn' => false,
            'tor' => false,
            'hosting' => false,
            'risk' => null,
            'country_code' => null,
            'country' => null,
            'asn' => null,
            'organisation' => null,
            'type' => null,
            'cached' => false,
        ];
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
