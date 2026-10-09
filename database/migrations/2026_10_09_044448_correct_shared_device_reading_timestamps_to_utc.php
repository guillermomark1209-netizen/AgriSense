<?php

use Carbon\Carbon;
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
        Schema::create('sensor_reading_timestamp_corrections', function (Blueprint $table) {
            $table->foreignId('sensor_reading_id')->primary()->constrained('sensor_readings')->cascadeOnDelete();
            $table->dateTime('original_reading_at');
        });

        $deviceId = DB::table('devices')->where('device_id', 'agrisense-esp32-001')->value('id');

        if ($deviceId === null) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "INSERT INTO sensor_reading_timestamp_corrections (sensor_reading_id, original_reading_at)
                SELECT readings.id, readings.reading_at
                FROM sensor_readings AS readings
                WHERE readings.device_id = ?
                    AND readings.reading_at BETWEEN readings.created_at + INTERVAL '7 hours'
                    AND readings.created_at + INTERVAL '9 hours'",
                [$deviceId]
            );

            DB::statement(
                "UPDATE sensor_readings AS readings
                SET reading_at = readings.reading_at - INTERVAL '8 hours'
                WHERE readings.id IN (
                    SELECT sensor_reading_id FROM sensor_reading_timestamp_corrections
                )"
            );

            return;
        }

        foreach (DB::table('sensor_readings')->where('device_id', $deviceId)->orderBy('id')->get(['id', 'reading_at', 'created_at']) as $reading) {
            $readingAt = Carbon::parse($reading->reading_at, 'UTC');
            $createdAt = Carbon::parse($reading->created_at, 'UTC');

            if ($readingAt->lt($createdAt->copy()->addHours(7)) || $readingAt->gt($createdAt->copy()->addHours(9))) {
                continue;
            }

            DB::table('sensor_reading_timestamp_corrections')->insert([
                'sensor_reading_id' => $reading->id,
                'original_reading_at' => $readingAt->format('Y-m-d H:i:s'),
            ]);

            DB::table('sensor_readings')->where('id', $reading->id)->update([
                'reading_at' => $readingAt->subHours(8)->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sensor_reading_timestamp_corrections')) {
            return;
        }

        foreach (DB::table('sensor_reading_timestamp_corrections')->get() as $correction) {
            DB::table('sensor_readings')->where('id', $correction->sensor_reading_id)->update(['reading_at' => $correction->original_reading_at]);
        }

        Schema::dropIfExists('sensor_reading_timestamp_corrections');
    }
};
