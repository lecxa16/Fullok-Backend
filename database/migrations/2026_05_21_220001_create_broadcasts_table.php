<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->string('mensaje', 500);
            $table->string('icon', 40)->nullable();
            $table->string('deeplink', 200)->nullable();
            $table->enum('prioridad', ['high', 'medium', 'low'])->default('medium');

            // Filtros aplicados al momento del envío (snapshot)
            // null = todos. Si filtra: { "tier": "silver+" } por ejemplo.
            $table->json('audience_filter')->nullable();

            $table->unsignedInteger('total_users')->default(0);     // a quiénes se pretendía mandar
            $table->unsignedInteger('push_sent_count')->default(0); // cuántos pushes Expo aceptó
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
