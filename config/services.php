<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
        'timeout' => env('ANTHROPIC_TIMEOUT', 25), // segundos
    ],

    'facturapi' => [
        // sk_test_XXXX (sandbox) o sk_user_XXXX (producción)
        'key' => env('FACTURAPI_KEY'),

        // Permiso CRE del emisor — requerido para CFDI con complemento de
        // hidrocarburos. El SAT lo valida: 15-35 caracteres, formato libre,
        // típicamente H/XXXXX/COM/AAAA.
        'cre_numero_permiso' => env('FACTURAPI_CRE_NUMERO_PERMISO', ''),
        // Catálogo SAT c_TipoPermiso: PER01 comercialización, PER02 distribución,
        // PER03 expendio al público (gasolineras), PER04 almacenamiento, etc.
        'cre_tipo_permiso' => env('FACTURAPI_CRE_TIPO_PERMISO', 'PER03'),

        // Cuotas IEPS vigentes por litro (factor=Cuota). Cambian anualmente
        // según el DOF. Ajusta cuando publiquen los valores oficiales del año.
        'ieps_cuota_magna' => (float) env('FACTURAPI_IEPS_CUOTA_MAGNA', 6.4555),
        'ieps_cuota_premium' => (float) env('FACTURAPI_IEPS_CUOTA_PREMIUM', 5.4485),
        'ieps_cuota_diesel' => (float) env('FACTURAPI_IEPS_CUOTA_DIESEL', 7.0978),
    ],

];
