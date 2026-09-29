<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(file_get_contents(database_path('rbac-roles.sql')));

            return;
        }

        if (! Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' CONSTRAINT users_role_check CHECK (role IN ('admin', 'user'))");
            if (Schema::hasTable('user_roles')) {
                DB::table('users')->whereIn('id', DB::table('user_roles')->select('user_id')->where('role', 'admin'))->update(['role' => 'admin']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('RBAC rollback requires a reviewed forward migration to preserve account roles.');
    }
};
