<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('knowledge_base') && ! Schema::hasColumn('knowledge_base', 'source_url')) {
            Schema::table('knowledge_base', function (Blueprint $table): void {
                $table->string('source_url')->nullable();
            });

            DB::table('knowledge_base')->whereNull('source_url')->update(['source_url' => DB::raw('source')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('knowledge_base') && Schema::hasColumn('knowledge_base', 'source_url')) {
            Schema::table('knowledge_base', function (Blueprint $table): void {
                $table->dropColumn('source_url');
            });
        }
    }
};
