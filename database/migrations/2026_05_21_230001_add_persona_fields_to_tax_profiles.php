<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende tax_profiles para guardar el desglose de persona (fisica/moral)
 * que la app móvil ya captura. El backend mantiene `razon_social` como
 * campo canónico (lo que se manda a Facturapi), y estos campos son auxiliares
 * para reconstruir la UI al editar.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tax_profiles', function (Blueprint $table) {
            $table->enum('tipo_persona', ['fisica', 'moral'])->default('fisica')->after('razon_social');
            $table->string('nombre', 100)->nullable()->after('tipo_persona');
            $table->string('apellido_paterno', 100)->nullable()->after('nombre');
            $table->string('apellido_materno', 100)->nullable()->after('apellido_paterno');
            $table->string('nombre_fiscal', 200)->nullable()->after('apellido_materno');
        });
    }

    public function down(): void
    {
        Schema::table('tax_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_persona', 'nombre', 'apellido_paterno', 'apellido_materno', 'nombre_fiscal',
            ]);
        });
    }
};
