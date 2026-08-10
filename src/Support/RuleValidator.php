<?php

namespace MarkHitchk\TrafficGuard\Support;

use Carbon\Carbon;
use InvalidArgumentException;
use MarkHitchk\TrafficGuard\Model\Rule;

class RuleValidator
{
    const TYPES = ['ip', 'cidr', 'country', 'asn', 'path', 'user_agent'];
    const ACTIONS = ['allow', 'block'];
    const RESPONSE_KEYS = ['manual', 'vpn', 'proxy', 'tor', 'hosting', 'country', 'asn', 'path', 'user_agent', 'risk', 'provider_error', 'default'];

    public static function normalize(array $input, Rule $existing = null)
    {
        $type = self::value($input, 'type', $existing ? $existing->type : null);
        $value = trim((string) self::value($input, 'value', $existing ? $existing->value : ''));
        $action = self::value($input, 'action', $existing ? $existing->action : 'block');

        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid rule type.');
        }

        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Invalid rule action.');
        }

        if ($value === '') {
            throw new InvalidArgumentException('Rule value is required.');
        }

        switch ($type) {
            case 'ip':
                if (! IpMatcher::isValidIp($value)) {
                    throw new InvalidArgumentException('Enter a valid IPv4 or IPv6 address.');
                }
                break;

            case 'cidr':
                if (! IpMatcher::isValidCidr($value)) {
                    throw new InvalidArgumentException('Enter a valid IPv4 or IPv6 CIDR range.');
                }
                break;

            case 'country':
                $value = strtoupper($value);
                if (! preg_match('/^[A-Z]{2}$/', $value)) {
                    throw new InvalidArgumentException('Country rules use a two-letter ISO country code, for example US.');
                }
                break;

            case 'asn':
                $value = strtoupper($value);
                if (strpos($value, 'AS') !== 0 && ctype_digit($value)) {
                    $value = 'AS'.$value;
                }
                if (! preg_match('/^AS\d+$/', $value)) {
                    throw new InvalidArgumentException('ASN rules must look like AS12345.');
                }
                break;
        }

        $reason = self::nullableString(self::value($input, 'reason', $existing ? $existing->reason : null), 5000);
        $responseKey = self::value($input, 'responseKey', $existing ? $existing->response_key : null);
        $responseKey = $responseKey === '' ? null : $responseKey;

        if ($responseKey !== null && ! in_array($responseKey, self::RESPONSE_KEYS, true)) {
            throw new InvalidArgumentException('Invalid response template.');
        }

        $statusCode = self::value($input, 'statusCode', $existing ? $existing->status_code : null);
        if ($statusCode === '' || $statusCode === null) {
            $statusCode = null;
        } else {
            $statusCode = (int) $statusCode;
            if ($statusCode < 400 || $statusCode > 599) {
                throw new InvalidArgumentException('Status code must be between 400 and 599.');
            }
        }

        $priority = (int) self::value($input, 'priority', $existing ? $existing->priority : 100);
        $priority = max(-10000, min(10000, $priority));

        $enabledRaw = self::value($input, 'enabled', $existing ? $existing->enabled : true);
        $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled === null) {
            $enabled = (bool) $enabledRaw;
        }

        $expiresRaw = self::value($input, 'expiresAt', $existing && $existing->expires_at ? $existing->expires_at->toIso8601String() : null);
        $expiresAt = null;
        if ($expiresRaw !== null && trim((string) $expiresRaw) !== '') {
            try {
                $expiresAt = Carbon::parse($expiresRaw);
            } catch (\Throwable $e) {
                throw new InvalidArgumentException('Expiration date is invalid.');
            }
        }

        return [
            'type' => $type,
            'value' => $value,
            'action' => $action,
            'reason' => $reason,
            'response_key' => $responseKey,
            'status_code' => $statusCode,
            'priority' => $priority,
            'enabled' => $enabled,
            'expires_at' => $expiresAt,
        ];
    }

    private static function value(array $input, $key, $default)
    {
        return array_key_exists($key, $input) ? $input[$key] : $default;
    }

    private static function nullableString($value, $max)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException('A text field is too long.');
        }

        return $value;
    }
}
