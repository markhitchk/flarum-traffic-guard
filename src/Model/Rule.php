<?php

namespace MarkHitchk\TrafficGuard\Model;

use Flarum\Database\AbstractModel;

class Rule extends AbstractModel
{
    protected $table = 'traffic_guard_rules';

    protected $fillable = [
        'type',
        'value',
        'action',
        'reason',
        'response_key',
        'status_code',
        'priority',
        'enabled',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'priority' => 'integer',
        'enabled' => 'boolean',
        'created_by' => 'integer',
    ];

    protected $dates = ['expires_at', 'created_at', 'updated_at'];
}
