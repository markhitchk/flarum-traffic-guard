<?php

namespace MarkHitchk\TrafficGuard\Support;

use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

class ClientIpResolver
{
    private $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function resolve(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        $remote = isset($server['REMOTE_ADDR']) ? trim((string) $server['REMOTE_ADDR']) : '';

        if (! IpMatcher::isValidIp($remote)) {
            return null;
        }

        if (! $this->enabled('trust_proxy_headers')) {
            return $remote;
        }

        $trusted = $this->trustedProxyCidrs();
        if (! $this->matchesAny($remote, $trusted)) {
            return $remote;
        }

        $header = (string) $this->get('proxy_header', 'CF-Connecting-IP');
        $value = trim($request->getHeaderLine($header));

        if ($value === '') {
            return $remote;
        }

        if (strcasecmp($header, 'X-Forwarded-For') === 0) {
            $chain = array_values(array_filter(array_map('trim', explode(',', $value))));
            $chain[] = $remote;

            for ($i = count($chain) - 1; $i >= 0; $i--) {
                $candidate = $chain[$i];

                if (! IpMatcher::isValidIp($candidate)) {
                    continue;
                }

                if ($this->matchesAny($candidate, $trusted)) {
                    continue;
                }

                return $candidate;
            }

            return $remote;
        }

        return IpMatcher::isValidIp($value) ? $value : $remote;
    }

    private function trustedProxyCidrs()
    {
        $raw = (string) $this->get('trusted_proxy_cidrs', '');
        $items = preg_split('/[\r\n,]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $items), function ($value) {
            return IpMatcher::isValidIp($value) || IpMatcher::isValidCidr($value);
        }));
    }

    private function matchesAny($ip, array $items)
    {
        foreach ($items as $item) {
            if (IpMatcher::matches($ip, $item)) {
                return true;
            }
        }

        return false;
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
