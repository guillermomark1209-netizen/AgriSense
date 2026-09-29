<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table): void {
            $table->string('session_id')->nullable()->index()->after('user_id');
            $table->string('ip_address', 45)->nullable()->after('session_id');
        });

        DB::table('user_sessions')->where('status', 'offline')->update(['status' => 'inactive']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table): void {
            $table->dropIndex(['session_id']);
            $table->dropColumn(['session_id', 'ip_address']);
        });
    }
};
