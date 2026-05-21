<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotion_user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('progress_current')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('points_awarded')->nullable();
            $table->timestamps();

            $table->unique(['promotion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_user_progress');
    }
};
