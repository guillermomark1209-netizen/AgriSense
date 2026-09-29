<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('knowledge_base') && ! Schema::hasColumn('knowledge_base', 'embedding_model')) {
            Schema::table('knowledge_base', function (Blueprint $table): void {
                $table->string('embedding_model')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('knowledge_base') && Schema::hasColumn('knowledge_base', 'embedding_model')) {
            Schema::table('knowledge_base', function (Blueprint $table): void {
                $table->dropIndex(['embedding_model']);
                $table->dropColumn('embedding_model');
            });
        }
    }
};
