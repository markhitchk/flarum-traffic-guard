<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('traffic_guard_logs', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('ip', 128)->nullable();
    $table->string('action', 16);
    $table->string('category', 32)->nullable();
    $table->unsignedInteger('rule_id')->nullable();
    $table->text('reason')->nullable();
    $table->text('path')->nullable();
    $table->text('user_agent')->nullable();
    $table->text('metadata')->nullable();
    $table->dateTime('created_at');

    $table->index(['action', 'created_at']);
    $table->index(['category', 'created_at']);
    $table->index('rule_id');
});
