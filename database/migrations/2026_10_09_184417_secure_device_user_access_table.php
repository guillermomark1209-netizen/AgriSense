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
        if (! Schema::hasTable('device_user_access')) {
            Schema::create('device_user_access', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
                $table->boolean('is_selected')->default(false);
                $table->timestampsTz();
                $table->primary(['user_id', 'device_id']);
                $table->index('device_id');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.device_user_access ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL ON TABLE public.device_user_access FROM anon, authenticated');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep this shared access table and its existing associations on rollback.
    }
};
