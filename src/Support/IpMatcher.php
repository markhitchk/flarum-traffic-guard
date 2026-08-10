<?php

namespace MarkHitchk\TrafficGuard\Support;

class IpMatcher
{
    public static function isValidIp($ip)
    {
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function isValidCidr($cidr)
    {
        if (! is_string($cidr) || strpos($cidr, '/') === false) {
            return false;
        }

        list($network, $prefix) = explode('/', $cidr, 2);

        if (! self::isValidIp($network) || ! ctype_digit((string) $prefix)) {
            return false;
        }

        $packed = @inet_pton($network);
        if ($packed === false) {
            return false;
        }

        $maxBits = strlen($packed) * 8;
        $prefix = (int) $prefix;

        return $prefix >= 0 && $prefix <= $maxBits;
    }

    public static function matches($ip, $ipOrCidr)
    {
        if (! self::isValidIp($ip) || ! is_string($ipOrCidr)) {
            return false;
        }

        if (strpos($ipOrCidr, '/') === false) {
            if (! self::isValidIp($ipOrCidr)) {
                return false;
            }

            $a = @inet_pton($ip);
            $b = @inet_pton($ipOrCidr);

            return $a !== false && $b !== false && hash_equals($a, $b);
        }

        if (! self::isValidCidr($ipOrCidr)) {
            return false;
        }

        list($network, $prefix) = explode('/', $ipOrCidr, 2);
        $ipPacked = @inet_pton($ip);
        $networkPacked = @inet_pton($network);

        if ($ipPacked === false || $networkPacked === false || strlen($ipPacked) !== strlen($networkPacked)) {
            return false;
        }

        $prefix = (int) $prefix;
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipPacked, 0, $fullBytes) !== substr($networkPacked, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipPacked[$fullBytes]) & $mask) === (ord($networkPacked[$fullBytes]) & $mask);
    }

    public static function isPublicIp($ip)
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
