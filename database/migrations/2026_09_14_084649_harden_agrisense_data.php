<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', fn (Blueprint $t) => $t->unique('user_id'));
        Schema::table('user_roles', fn (Blueprint $t) => $t->unique(['user_id', 'role']));
        Schema::table('devices', function (Blueprint $t) {
            $t->string('token_hash', 64)->nullable()->unique();
            $t->index(['user_id', 'last_seen_at']);
        });
        Schema::table('sensor_readings', function (Blueprint $t) {
            $t->uuid('reading_id')->nullable();
            $t->unique(['device_id', 'reading_id']);
            $t->index(['crop_id', 'reading_at']);
            $t->index(['device_id', 'reading_at']);
        });
        Schema::table('crop_sensor_thresholds', function (Blueprint $t) {
            $t->foreignId('source_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('variety')->nullable();
            $t->string('growth_stage')->nullable();
            $t->unique(['crop_id', 'sensor_type', 'variety', 'growth_stage'], 'threshold_context_unique');
        });
        Schema::table('sources', function (Blueprint $t) {
            $t->date('expires_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->index(['verification_status', 'is_active']);
        });
        Schema::table('alerts', function (Blueprint $t) {
            $t->timestamp('resolved_at')->nullable();
            $t->index(['user_id', 'status', 'detected_at']);
        });
        Schema::table('document_chunks', function (Blueprint $t) {
            $t->string('embedding_model')->nullable();
            $t->unique(['document_id', 'chunk_index']);
        });
        Schema::table('ai_messages', fn (Blueprint $t) => $t->index(['conversation_id', 'created_at']));
        Schema::create('crop_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('crop_id')->constrained()->cascadeOnDelete();
            $t->string('event');
            $t->timestamps();
        });
        Schema::create('system_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value');
            $t->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding_vector vector(768)');
            DB::statement('CREATE INDEX chunks_vector_idx ON document_chunks USING hnsw (embedding_vector vector_cosine_ops)');
            DB::statement('ALTER TABLE sensor_readings ADD CONSTRAINT sensor_physical_ranges CHECK ((humidity IS NULL OR humidity BETWEEN 0 AND 100) AND (soil_moisture IS NULL OR soil_moisture BETWEEN 0 AND 100) AND (soil_ph IS NULL OR soil_ph BETWEEN 0 AND 14) AND (light_intensity IS NULL OR light_intensity >= 0))');
            DB::statement('ALTER TABLE crop_sensor_thresholds ADD CONSTRAINT threshold_range CHECK (min_value IS NULL OR max_value IS NULL OR min_value < max_value)');
            DB::statement("ALTER TABLE user_roles ADD CONSTRAINT allowed_role CHECK (role IN ('farmer', 'admin'))");
            DB::statement("ALTER TABLE alerts ADD CONSTRAINT alert_status CHECK (status IN ('active', 'resolved'))");
            foreach (['users', 'profiles', 'user_roles', 'crops', 'devices', 'sensor_readings', 'crop_sensor_thresholds', 'alerts', 'sources', 'documents', 'document_chunks', 'ai_conversations', 'ai_messages', 'ai_message_sources', 'ai_audit_logs', 'crop_events', 'system_settings'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('crop_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_chunks DROP COLUMN embedding_vector');
            DB::statement('ALTER TABLE sensor_readings DROP CONSTRAINT sensor_physical_ranges');
            DB::statement('ALTER TABLE crop_sensor_thresholds DROP CONSTRAINT threshold_range');
            DB::statement('ALTER TABLE user_roles DROP CONSTRAINT allowed_role');
            DB::statement('ALTER TABLE alerts DROP CONSTRAINT alert_status');
        }
        Schema::table('profiles', fn (Blueprint $t) => $t->dropUnique(['user_id']));
        Schema::table('user_roles', fn (Blueprint $t) => $t->dropUnique(['user_id', 'role']));
        Schema::table('devices', function (Blueprint $t) {
            $t->dropColumn('token_hash');
            $t->dropIndex(['user_id', 'last_seen_at']);
        });
        Schema::table('sensor_readings', function (Blueprint $t) {
            $t->dropUnique(['device_id', 'reading_id']);
            $t->dropColumn('reading_id');
            $t->dropIndex(['crop_id', 'reading_at']);
            $t->dropIndex(['device_id', 'reading_at']);
        });
        Schema::table('crop_sensor_thresholds', function (Blueprint $t) {
            $t->dropUnique('threshold_context_unique');
            $t->dropConstrainedForeignId('source_id');
            $t->dropColumn(['variety', 'growth_stage']);
        });
        Schema::table('sources', function (Blueprint $t) {
            $t->dropConstrainedForeignId('verified_by');
            $t->dropColumn(['expires_at', 'verified_at']);
            $t->dropIndex(['verification_status', 'is_active']);
        });
        Schema::table('alerts', function (Blueprint $t) {
            $t->dropColumn('resolved_at');
            $t->dropIndex(['user_id', 'status', 'detected_at']);
        });
        Schema::table('document_chunks', function (Blueprint $t) {
            $t->dropColumn('embedding_model');
            $t->dropUnique(['document_id', 'chunk_index']);
        });
        Schema::table('ai_messages', fn (Blueprint $t) => $t->dropIndex(['conversation_id', 'created_at']));
    }
};
