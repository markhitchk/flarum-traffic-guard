<?php

namespace MarkHitchk\TrafficGuard\Provider;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use RuntimeException;

class ProxyCheckProvider implements ProviderInterface
{
    private $client;
    private $settings;

    public function __construct(Client $client, SettingsRepositoryInterface $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
    }

    public function lookup($ip)
    {
        $timeout = max(1, min(10, (int) $this->get('provider_timeout', '3')));
        $key = trim((string) $this->get('proxycheck_key', ''));

        $query = [
            'vpn' => 1,
            'asn' => 1,
            'risk' => 1,
            'tag' => 0,
        ];

        if ($key !== '') {
            $query['key'] = $key;
        }

        $response = $this->client->request('GET', 'https://proxycheck.io/v2/'.rawurlencode($ip), [
            'query' => $query,
            'timeout' => $timeout,
            'connect_timeout' => min(2, $timeout),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Flarum-Traffic-Guard/1.0',
            ],
        ]);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('Threat provider returned HTTP '.$response->getStatusCode().'.');
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Threat provider returned invalid JSON.');
        }

        $row = isset($payload[$ip]) && is_array($payload[$ip]) ? $payload[$ip] : null;
        if (! $row) {
            throw new RuntimeException('Threat provider response did not contain the requested IP.');
        }

        $type = strtolower((string) ($row['type'] ?? ''));
        $proxy = $this->yes($row['proxy'] ?? false);
        $vpn = $this->yes($row['vpn'] ?? false) || strpos($type, 'vpn') !== false;
        $tor = $this->yes($row['tor'] ?? false) || strpos($type, 'tor') !== false;
        $hosting = $this->yes($row['hosting'] ?? false) || strpos($type, 'hosting') !== false || strpos($type, 'server') !== false;

        $risk = isset($row['risk']) && is_numeric($row['risk']) ? (int) $row['risk'] : null;
        if ($risk !== null) {
            $risk = max(0, min(100, $risk));
        }

        $countryCode = strtoupper((string) ($row['isocode'] ?? $row['country_code'] ?? ''));
        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            $countryCode = null;
        }

        $asn = strtoupper(trim((string) ($row['asn'] ?? ''));
        if ($asn !== '' && strpos($asn, 'AS') !== 0 && ctype_digit($asn)) {
            $asn = 'AS'.$asn;
        }
        if ($asn !== '' && ! preg_match('/^AS\d+$/', $asn)) {
            $asn = null;
        }

        return [
            'provider' => 'proxycheck',
            'proxy' => $proxy,
            'vpn' => $vpn,
            'tor' => $tor,
            'hosting' => $hosting,
            'risk' => $risk,
            'country_code' => $countryCode,
            'country' => isset($row['country']) ? (string) $row['country'] : null,
            'asn' => $asn,
            'organisation' => isset($row['organisation']) ? (string) $row['organisation'] : null,
            'type' => isset($row['type']) ? (string) $row['type'] : null,
        ];
    }

    private function yes($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['yes', 'true', '1', 'y'], true);
    }

    private function get($name, $default = null)
    {
        return $this->settings->get('markhitchk-traffic-guard.'.$name, $default);
    }
}
