<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Datos fiscales SAT
            $table->string('rfc', 13);
            $table->string('razon_social', 200);
            // Claves SAT (códigos numéricos: 601, 612, 621, etc.)
            $table->string('regimen_fiscal_sat', 5);
            // Uso CFDI default del perfil (G03, S01, etc.); puede sobreescribirse al solicitar factura
            $table->string('uso_cfdi_default', 5)->default('G03');
            $table->string('cp_fiscal', 5);
            $table->string('email_facturacion', 200);

            // Alias opcional para distinguir "personal" vs "empresa" en la app
            $table->string('alias', 60)->nullable();
            $table->boolean('is_default')->default(false);

            // ID en Facturapi (customer). Nullable porque podríamos crearlo después
            // si la llamada al PAC falla la primera vez.
            $table->string('facturapi_customer_id', 60)->nullable();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->unique(['user_id', 'rfc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_profiles');
    }
};
