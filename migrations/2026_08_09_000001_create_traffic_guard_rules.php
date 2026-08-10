<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('traffic_guard_rules', function (Blueprint $table) {
    $table->increments('id');
    $table->string('type', 32);
    $table->string('value', 255);
    $table->string('action', 16)->default('block');
    $table->text('reason')->nullable();
    $table->string('response_key', 32)->nullable();
    $table->unsignedSmallInteger('status_code')->nullable();
    $table->integer('priority')->default(100);
    $table->boolean('enabled')->default(true);
    $table->dateTime('expires_at')->nullable();
    $table->unsignedInteger('created_by')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index(['type', 'enabled']);
    $table->index(['action', 'enabled']);
    $table->index('expires_at');
});
