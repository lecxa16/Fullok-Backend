<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('costo_puntos');
            $table->enum('tipo', ['descuento', 'combustible', 'servicio', 'producto', 'otro'])
                ->default('descuento');
            $table->string('imagen_url', 500)->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->timestamp('inicia_at')->nullable();
            $table->timestamp('termina_at')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
