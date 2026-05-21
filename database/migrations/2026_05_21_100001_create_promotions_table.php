<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            // showcase: solo banner informativo (link a reward/pantalla)
            // multiplier: aplica factor extra al earning de tickets dentro de la ventana
            // bonus_goal: usuario completa una meta (N tickets / monto) y recibe bonus_points
            $table->enum('tipo', ['showcase', 'multiplier', 'bonus_goal']);

            $table->string('titulo', 150);
            $table->string('descripcion', 500)->nullable();
            $table->string('imagen_url', 500)->nullable();
            $table->string('color_hex', 7)->nullable(); // ej: #E31E24
            $table->string('emoji', 8)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->enum('estado', ['draft', 'scheduled', 'active', 'expired', 'paused'])
                ->default('draft')->index();
            $table->unsignedInteger('prioridad')->default(100); // orden carrusel; menor = más arriba

            // multiplier
            $table->decimal('multiplier_value', 4, 2)->nullable(); // ej 2.00
            $table->enum('fuel_type_filter', ['magna', 'premium', 'diesel'])->nullable();
            $table->enum('min_tier', ['bronze', 'silver', 'gold'])->nullable();

            // bonus_goal
            $table->enum('goal_type', ['tickets_count', 'total_amount'])->nullable();
            $table->unsignedInteger('goal_target')->nullable();
            $table->unsignedInteger('bonus_points')->nullable();

            // límites
            $table->unsignedInteger('max_redemptions_per_user')->nullable();
            $table->unsignedInteger('total_budget_points')->nullable();
            $table->unsignedInteger('points_issued')->default(0);

            $table->string('deeplink_url', 200)->nullable(); // para showcase

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
