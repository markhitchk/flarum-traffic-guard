<?php

namespace MarkHitchk\TrafficGuard\Service;

class Decision
{
    public $blocked;
    public $category;
    public $reason;
    public $ruleId;
    public $responseKey;
    public $statusCode;
    public $expiresAt;
    public $threat;
    public $providerError;

    public function __construct(
        $blocked,
        $category = null,
        $reason = null,
        $ruleId = null,
        $responseKey = null,
        $statusCode = null,
        $expiresAt = null,
        array $threat = [],
        $providerError = null
    ) {
        $this->blocked = (bool) $blocked;
        $this->category = $category;
        $this->reason = $reason;
        $this->ruleId = $ruleId !== null ? (int) $ruleId : null;
        $this->responseKey = $responseKey;
        $this->statusCode = $statusCode !== null ? (int) $statusCode : null;
        $this->expiresAt = $expiresAt;
        $this->threat = $threat;
        $this->providerError = $providerError;
    }

    public static function allow(array $threat = [], $reason = null)
    {
        return new self(false, 'allow', $reason, null, null, null, null, $threat);
    }

    public static function block($category, $reason, $ruleId = null, $responseKey = null, $statusCode = null, $expiresAt = null, array $threat = [])
    {
        return new self(true, $category, $reason, $ruleId, $responseKey, $statusCode, $expiresAt, $threat);
    }

    public function toArray()
    {
        return [
            'blocked' => $this->blocked,
            'category' => $this->category,
            'reason' => $this->reason,
            'ruleId' => $this->ruleId,
            'responseKey' => $this->responseKey,
            'statusCode' => $this->statusCode,
            'expiresAt' => $this->expiresAt,
            'threat' => $this->threat,
            'providerError' => $this->providerError,
        ];
    }
}
