<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'session_version')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedInteger('session_version')->default(0);
            });
        }
    }

    public function down(): void {}
};
