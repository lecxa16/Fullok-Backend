<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // tipo: ticket_approved, ticket_rejected, redemption_done, tier_up,
            //       points_expiring, promo_new, reward_new, goal_progress, inactivity
            $table->string('tipo', 40);
            $table->string('titulo', 150);
            $table->string('mensaje', 500);
            $table->string('icon', 40)->nullable();
            // deeplink: fullok://tickets/123, fullok://rewards, fullok://profile, etc.
            $table->string('deeplink', 200)->nullable();
            $table->json('payload')->nullable();
            $table->enum('prioridad', ['high', 'medium', 'low'])->default('medium');
            $table->timestamp('leida_at')->nullable();
            $table->timestamp('push_sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'leida_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
