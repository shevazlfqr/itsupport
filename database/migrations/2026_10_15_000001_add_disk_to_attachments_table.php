<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attachments') && ! Schema::hasColumn('attachments', 'disk')) {
            Schema::table('attachments', function (Blueprint $table): void {
                $table->string('disk', 32)->default('public');
            });
        }
    }

    public function down(): void {}
};
