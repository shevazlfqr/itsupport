<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('subject', 100);
                $table->text('description');
                $table->string('category', 50);
                $table->string('priority', 20);
                $table->string('status', 30)->index();
                $table->unsignedInteger('requester_id');
                $table->unsignedInteger('assignee_id')->nullable();
                $table->text('resolution_note');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('sla_deadline')->nullable()->index();
                $table->foreign('requester_id')->references('id')->on('users');
                $table->foreign('assignee_id')->references('id')->on('users')->nullOnDelete();
                $table->index('requester_id');
                $table->index('assignee_id');
            });
        } elseif (! Schema::hasColumn('tickets', 'sla_deadline')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dateTime('sla_deadline')->nullable()->index();
            });
        }

        if (! Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('ticket_id')->index();
                $table->unsignedInteger('user_id');
                $table->text('message');
                $table->dateTime('created_at');
                $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('activities')) {
            Schema::create('activities', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('ticket_id')->index();
                $table->unsignedInteger('user_id');
                $table->string('message', 255);
                $table->dateTime('created_at');
                $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('attachments')) {
            Schema::create('attachments', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('ticket_id')->index();
                $table->unsignedInteger('user_id');
                $table->string('stored_name', 255);
                $table->string('original_name', 255);
                $table->string('mime', 100);
                $table->unsignedInteger('size');
                $table->dateTime('created_at');
                $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('ticket_reads')) {
            Schema::create('ticket_reads', function (Blueprint $table): void {
                $table->unsignedInteger('ticket_id');
                $table->unsignedInteger('user_id');
                $table->dateTime('last_read_at');
                $table->primary(['ticket_id', 'user_id']);
                $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void {}
};
