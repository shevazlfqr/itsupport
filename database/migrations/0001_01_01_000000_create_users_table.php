<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 80);
                $table->string('email', 120)->unique('users_email_unique');
                $table->string('password');
                $table->string('role', 20)->default('employee');
                $table->string('department', 80)->default('-');
                $table->string('job_title', 80)->default('Karyawan');
                $table->boolean('active')->default(true);
                $table->index(['role', 'active'], 'users_role_active_index');
            });
        } else {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'role')) {
                    $table->string('role', 20)->default('employee');
                }
                if (! Schema::hasColumn('users', 'department')) {
                    $table->string('department', 80)->default('-');
                }
                if (! Schema::hasColumn('users', 'job_title')) {
                    $table->string('job_title', 80)->default('Karyawan');
                }
                if (! Schema::hasColumn('users', 'active')) {
                    $table->boolean('active')->default(true);
                }
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void {}
};
