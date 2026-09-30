<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_message_sources', function (Blueprint $table) {
            $table->text('citation_url')->nullable()->after('citation_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_message_sources', function (Blueprint $table) {
            $table->dropColumn('citation_url');
        });
    }
};
