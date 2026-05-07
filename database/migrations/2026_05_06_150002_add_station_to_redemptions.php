<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            $table->foreignId('station_id')
                ->nullable()
                ->after('reward_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('station_nombre_snapshot', 150)
                ->nullable()
                ->after('reward_nombre_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn(['station_id', 'station_nombre_snapshot']);
        });
    }
};
