<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('traffic_guard_ip_cache', function (Blueprint $table) {
    $table->string('ip', 45)->primary();
    $table->text('payload');
    $table->dateTime('expires_at');
    $table->dateTime('updated_at');

    $table->index('expires_at');
});
