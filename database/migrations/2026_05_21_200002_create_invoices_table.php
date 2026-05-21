<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // 1:1 con ticket aprobado — único garantiza que un ticket no se factura dos veces
            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('tax_profile_id')->constrained('tax_profiles');

            // Estado del flujo (no del SAT)
            $table->enum('estado', ['requested', 'processing', 'generated', 'error', 'cancelled'])
                ->default('requested')
                ->index();

            // Datos del CFDI cuando esté generado
            $table->string('facturapi_invoice_id', 60)->nullable();
            $table->string('folio_fiscal_uuid', 40)->nullable()->unique();
            $table->string('serie', 25)->nullable();
            $table->string('folio', 40)->nullable();
            $table->decimal('monto_total', 10, 2);
            $table->string('uso_cfdi', 5)->default('G03');
            $table->string('payment_form', 5)->default('99'); // por definir

            // URLs firmadas (las regenera el endpoint show si caducan)
            $table->string('pdf_url', 500)->nullable();
            $table->string('xml_url', 500)->nullable();

            $table->timestamp('emitida_at')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->string('motivo_cancelacion', 5)->nullable();

            // Para debug
            $table->text('error_message')->nullable();
            $table->json('pac_response')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
