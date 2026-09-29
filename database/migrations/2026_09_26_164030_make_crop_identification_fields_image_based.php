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
        if (! Schema::hasColumn('crops', 'crop_name')) {
            Schema::table('crops', function (Blueprint $table): void {
                $table->string('crop_name')->nullable();
            });

            DB::table('crops')->whereNull('crop_name')->update(['crop_name' => DB::raw('name')]);
        }

        if (! Schema::hasColumn('crops', 'common_name')) {
            Schema::table('crops', function (Blueprint $table): void {
                $table->string('common_name')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('crops', 'common_name')) {
            Schema::table('crops', function (Blueprint $table): void {
                $table->dropColumn('common_name');
            });
        }

        if (Schema::hasColumn('crops', 'crop_name')) {
            Schema::table('crops', function (Blueprint $table): void {
                $table->dropColumn('crop_name');
            });
        }
    }
};
