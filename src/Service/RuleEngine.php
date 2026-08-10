<?php

namespace MarkHitchk\TrafficGuard\Service;

use Carbon\Carbon;
use MarkHitchk\TrafficGuard\Model\Rule;
use MarkHitchk\TrafficGuard\Support\IpMatcher;

class RuleEngine
{
    public function evaluate($ip, $path, $userAgent, array $threat, array $types)
    {
        $rules = Rule::query()
            ->where('enabled', true)
            ->whereIn('type', $types)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $matching = [];
        foreach ($rules as $rule) {
            if ($this->matches($rule, $ip, $path, $userAgent, $threat)) {
                $matching[] = $rule;
            }
        }

        // Explicit allows are a safety override for both manual and automatic checks.
        foreach ($matching as $rule) {
            if ($rule->action === 'allow') {
                return $this->decisionForRule($rule, false, $threat);
            }
        }

        foreach ($matching as $rule) {
            if ($rule->action === 'block') {
                return $this->decisionForRule($rule, true, $threat);
            }
        }

        return null;
    }

    public function hasMetadataRules()
    {
        return Rule::query()
            ->where('enabled', true)
            ->whereIn('type', ['country', 'asn'])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->exists();
    }

    private function matches(Rule $rule, $ip, $path, $userAgent, array $threat)
    {
        switch ($rule->type) {
            case 'ip':
            case 'cidr':
                return IpMatcher::matches($ip, $rule->value);

            case 'country':
                return isset($threat['country_code']) && $threat['country_code'] !== null
                    && strtoupper($rule->value) === strtoupper($threat['country_code']);

            case 'asn':
                return isset($threat['asn']) && $threat['asn'] !== null
                    && $this->normalizeAsn($rule->value) === $this->normalizeAsn($threat['asn']);

            case 'user_agent':
                return $rule->value !== '' && stripos((string) $userAgent, $rule->value) !== false;

            case 'path':
                return $this->wildcardMatch($rule->value, (string) $path);
        }

        return false;
    }

    private function decisionForRule(Rule $rule, $blocked, array $threat)
    {
        if (! $blocked) {
            return Decision::allow($threat, $rule->reason ?: 'Matched allow rule #'.$rule->id.'.');
        }

        $category = in_array($rule->type, ['ip', 'cidr'], true) ? 'manual' : $rule->type;
        $reason = $rule->reason ?: 'Access denied by Traffic Guard rule #'.$rule->id.'.';
        $expiresAt = $rule->expires_at ? $rule->expires_at->toIso8601String() : null;

        return Decision::block(
            $category,
            $reason,
            $rule->id,
            $rule->response_key ?: $category,
            $rule->status_code,
            $expiresAt,
            $threat
        );
    }

    private function normalizeAsn($value)
    {
        $value = strtoupper(trim((string) $value));
        if (strpos($value, 'AS') !== 0 && ctype_digit($value)) {
            $value = 'AS'.$value;
        }

        return $value;
    }

    private function wildcardMatch($pattern, $value)
    {
        $quoted = preg_quote((string) $pattern, '~');
        $quoted = str_replace(['\\*', '\\?'], ['.*', '.'], $quoted);

        return preg_match('~^'.$quoted.'$~i', (string) $value) === 1;
    }
}
