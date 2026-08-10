<?php

namespace MarkHitchk\TrafficGuard\Support;

use MarkHitchk\TrafficGuard\Model\Rule;

class RulePresenter
{
    public static function present(Rule $rule)
    {
        return [
            'id' => (int) $rule->id,
            'type' => $rule->type,
            'value' => $rule->value,
            'action' => $rule->action,
            'reason' => $rule->reason,
            'responseKey' => $rule->response_key,
            'statusCode' => $rule->status_code !== null ? (int) $rule->status_code : null,
            'priority' => (int) $rule->priority,
            'enabled' => (bool) $rule->enabled,
            'expiresAt' => $rule->expires_at ? $rule->expires_at->toIso8601String() : null,
            'createdBy' => $rule->created_by !== null ? (int) $rule->created_by : null,
            'createdAt' => $rule->created_at ? $rule->created_at->toIso8601String() : null,
            'updatedAt' => $rule->updated_at ? $rule->updated_at->toIso8601String() : null,
        ];
    }
}
