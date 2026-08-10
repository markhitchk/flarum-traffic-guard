<?php

namespace MarkHitchk\TrafficGuard\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use Throwable;

class TrafficGuardService
{
    private $settings;
    private $rules;
    private $lookup;

    public function __construct(
        SettingsRepositoryInterface $settings,
        RuleEngine $rules,
        ThreatLookupService $lookup
    ) {
        $this->settings = $settings;
        $this->rules = $rules;
        $this->lookup = $lookup;
    }

    public function inspect($ip, $path = '/', $userAgent = '', $forceLookup = false)
    {
        $pre = $this->rules->evaluate($ip, $path, $userAgent, [], ['ip', 'cidr', 'path', 'user_agent']);
        if ($pre instanceof Decision) {
            return $pre;
        }

        $threat = [];
        $providerError = null;

        if ($forceLookup || $this->shouldLookupThreatData()) {
            try {
                $threat = $this->lookup->lookup($ip, $forceLookup);
            } catch (Throwable $e) {
                $providerError = $e->getMessage();

                if ((string) $this->get('fail_mode', 'open') === 'closed') {
                    $decision = Decision::block(
                        'provider_error',
                        'Traffic Guard could not verify this network connection.',
                        null,
                        'provider_error',
                        null,
                        null,
                        []
                    );
                    $decision->providerError = $providerError;
                    return $decision;
                }
            }
        }

        $metadataRule = $this->rules->evaluate($ip, $path, $userAgent, $threat, ['country', 'asn']);
        if ($metadataRule instanceof Decision) {
            $metadataRule->providerError = $providerError;
            return $metadataRule;
        }

        $automatic = $this->automaticDecision($threat);
        if ($automatic instanceof Decision) {
            $automatic->providerError = $providerError;
            return $automatic;
        }

        $decision = Decision::allow($threat, 'No Traffic Guard block rule matched.');
        $decision->providerError = $providerError;

        return $decision;
    }

    private function automaticDecision(array $threat)
    {
        if (! empty($threat['tor']) && $this->enabled('block_tor')) {
            return Decision::block('tor', 'Tor exit-node connections are not permitted.', null, 'tor', null, null, $threat);
        }

        if (! empty($threat['vpn']) && $this->enabled('block_vpn')) {
            return Decision::block('vpn', 'VPN connections are not permitted.', null, 'vpn', null, null, $threat);
        }

        if (! empty($threat['proxy']) && $this->enabled('block_proxy')) {
            return Decision::block('proxy', 'Proxy connections are not permitted.', null, 'proxy', null, null, $threat);
        }

        if (! empty($threat['hosting']) && $this->enabled('block_hosting')) {
            return Decision::block('hosting', 'Hosting or datacenter network connections are not permitted.', null, 'hosting', null, null, $threat);
        }

        $threshold = max(0, min(100, (int) $this->get('risk_threshold', '0')));
        $risk = isset($threat['risk']) && is_numeric($threat['risk']) ? (int) $threat['risk'] : null;

        if ($threshold > 0 && $risk !== null && $risk >= $threshold) {
            return Decision::block(
                'risk',
                'This network exceeded the configured risk threshold.',
                null,
                'risk',
                null,
                null,
                $threat
            );
        }

        return null;
    }

    private function shouldLookupThreatData()
    {
        if ((string) $this->get('provider', 'none') === 'none') {
            return false;
        }

        if ($this->enabled('block_vpn') || $this->enabled('block_proxy') || $this->enabled('block_tor') || $this->enabled('block_hosting')) {
            return true;
        }

        if ((int) $this->get('risk_threshold', '0') > 0) {
            return true;
        }

        return $this->rules->hasMetadataRules();
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
