<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_events')) {
            return;
        }

        Schema::create('ticket_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('ticket_id');
            $table->string('event_key', 64);
            $table->string('command_id', 36);
            $table->unsignedSmallInteger('event_ordinal')->default(1);
            $table->string('event_type', 64);
            $table->dateTime('occurred_at_utc', 6);
            $table->dateTime('recorded_at_utc', 6);
            $table->string('actor_kind', 20)->default('user');
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('actor_role_snapshot', 20)->nullable();
            $table->string('source', 80);
            $table->text('reason')->nullable();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('from_priority', 20)->nullable();
            $table->string('to_priority', 20)->nullable();
            $table->unsignedInteger('from_assignee_id')->nullable();
            $table->unsignedInteger('to_assignee_id')->nullable();
            $table->unsignedInteger('comment_id')->nullable();
            $table->string('wait_interval_key', 36)->nullable();
            $table->unsignedInteger('resolution_cycle')->nullable();

            $table->unique(['ticket_id', 'event_key'], 'ticket_events_ticket_event_key_unique');
            $table->unique(['command_id', 'event_ordinal'], 'ticket_events_command_ordinal_unique');
            $table->index(['ticket_id', 'occurred_at_utc', 'id'], 'ticket_events_ticket_timeline_index');
            $table->index(['event_type', 'occurred_at_utc'], 'ticket_events_type_time_index');
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('comment_id')->references('id')->on('comments')->restrictOnDelete();
        });
    }

    public function down(): void {}
};
