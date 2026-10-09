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
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->decimal('soil_temperature', 8, 2)->nullable();
            $table->decimal('air_temperature', 8, 2)->nullable();
            $table->decimal('air_humidity', 8, 2)->nullable();
            $table->decimal('light_percent', 8, 2)->nullable();
            $table->integer('soil_raw')->nullable();
            $table->integer('ldr_raw')->nullable();
            $table->decimal('ph_raw', 10, 4)->nullable();
            $table->boolean('pump')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->dropColumn([
                'soil_temperature',
                'air_temperature',
                'air_humidity',
                'light_percent',
                'soil_raw',
                'ldr_raw',
                'ph_raw',
                'pump',
            ]);
        });
    }
};
