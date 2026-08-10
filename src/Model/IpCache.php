<?php

namespace MarkHitchk\TrafficGuard\Model;

use Flarum\Database\AbstractModel;

class IpCache extends AbstractModel
{
    protected $table = 'traffic_guard_ip_cache';

    protected $primaryKey = 'ip';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['ip', 'payload', 'expires_at', 'updated_at'];
    protected $dates = ['expires_at', 'updated_at'];
}
