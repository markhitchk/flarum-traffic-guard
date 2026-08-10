<?php

namespace MarkHitchk\TrafficGuard\Model;

use Flarum\Database\AbstractModel;

class AccessLog extends AbstractModel
{
    protected $table = 'traffic_guard_logs';

    public $timestamps = false;

    protected $fillable = [
        'ip',
        'action',
        'category',
        'rule_id',
        'reason',
        'path',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'rule_id' => 'integer',
    ];

    protected $dates = ['created_at'];
}
