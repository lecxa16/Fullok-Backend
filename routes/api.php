<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BroadcastController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PointsController;
use App\Http\Controllers\Api\ProgramSettingsController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\RewardInventoryController;
use App\Http\Controllers\Api\SimulatorScenarioController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\SurveyController;
use App\Http\Controllers\Api\TaxProfileController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ── Públicas — sin token ───────────────────────────────────────────────────
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/register',        [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-code',     [AuthController::class, 'verifyCode']);
Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

// Descarga firmada de facturas — no requiere Bearer porque el browser no lo
// manda. Acepta solo URLs firmadas (5 min de validez) generadas por el
// endpoint /me/invoices/{id}/download-url.
Route::get('/invoices/{invoice}/download/{kind}', [InvoiceController::class, 'downloadSigned'])
    ->middleware('signed')
    ->name('invoices.download');

// ── Protegidas — requieren token Sanctum ───────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Estaciones (lectura) — cualquier usuario autenticado, p. ej. cliente desde la app móvil
    Route::get('/stations',             [StationController::class, 'index']);
    Route::get('/stations/{station}',   [StationController::class, 'show']);

    // Recompensas (catálogo) — visible para todos los autenticados
    Route::get('/rewards',                       [RewardController::class, 'index']);
    Route::get('/rewards/{reward}',              [RewardController::class, 'show']);
    Route::get('/rewards/{reward}/availability', [RewardInventoryController::class, 'availability']);

    // Promociones (catálogo) — visible para todos los autenticados
    Route::get('/promotions',              [PromotionController::class, 'index']);
    Route::get('/promotions/{promotion}',  [PromotionController::class, 'show']);

    // Mi balance, transacciones, canjes
    Route::get('/me/points/balance',      [PointsController::class, 'myBalance']);
    Route::get('/me/points/stats',        [PointsController::class, 'myProfileStats']);
    Route::get('/me/points/transactions', [PointsController::class, 'myTransactions']);
    Route::post('/me/redemptions',        [PointsController::class, 'redeem']);
    Route::get('/me/redemptions',         [PointsController::class, 'myRedemptions']);

    // Notificaciones in-app
    Route::get('/me/notifications',                       [NotificationController::class, 'index']);
    Route::get('/me/notifications/unread-count',          [NotificationController::class, 'unreadCount']);
    Route::post('/me/notifications/read-all',             [NotificationController::class, 'markAllRead']);
    Route::post('/me/notifications/{notification}/read',  [NotificationController::class, 'markRead']);
    Route::delete('/me/notifications/{notification}',     [NotificationController::class, 'destroy']);

    // Push tokens (registro desde la app)
    Route::post('/me/push-tokens',           [PushTokenController::class, 'register']);
    Route::delete('/me/push-tokens/{token}', [PushTokenController::class, 'destroy']);

    // Perfiles fiscales (datos para emitir CFDI)
    Route::get('/me/tax-profiles',                 [TaxProfileController::class, 'index']);
    Route::post('/me/tax-profiles',                [TaxProfileController::class, 'store']);
    Route::get('/me/tax-profiles/{taxProfile}',    [TaxProfileController::class, 'show']);
    Route::put('/me/tax-profiles/{taxProfile}',    [TaxProfileController::class, 'update']);
    Route::delete('/me/tax-profiles/{taxProfile}', [TaxProfileController::class, 'destroy']);

    // Encuestas de satisfacción
    Route::get('/me/surveys/pending',                       [SurveyController::class, 'myPending']);
    Route::get('/me/surveys/{invitation}',                  [SurveyController::class, 'myShow']);
    Route::post('/me/surveys/{invitation}/respond',         [SurveyController::class, 'respond']);

    // Facturas
    Route::get('/me/invoices',                    [InvoiceController::class, 'index']);
    Route::post('/me/invoices',                   [InvoiceController::class, 'request']);
    Route::get('/me/invoices/{invoice}',          [InvoiceController::class, 'show']);
    Route::post('/me/invoices/{invoice}/cancel',  [InvoiceController::class, 'cancel']);
    Route::get('/me/invoices/{invoice}/download-url', [InvoiceController::class, 'signDownloadUrl']);

    // Mis tickets de carga
    Route::post('/me/tickets/extract', [TicketController::class, 'extract']);
    Route::post('/me/tickets',         [TicketController::class, 'store']);
    Route::get('/me/tickets',          [TicketController::class, 'myIndex']);
    Route::get('/me/tickets/{ticket}', [TicketController::class, 'myShow']);

    // Solo admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/roles',                 [UserController::class, 'roles']);
        Route::get('/usuarios',              [UserController::class, 'index']);
        Route::get('/usuarios/{usuario}',    [UserController::class, 'show']);
        Route::put('/usuarios/{usuario}',    [UserController::class, 'update']);
        Route::delete('/usuarios/{usuario}', [UserController::class, 'destroy']);

        Route::get('/program-settings',          [ProgramSettingsController::class, 'index']);
        Route::put('/program-settings',          [ProgramSettingsController::class, 'update']);
        Route::get('/program-settings/history',  [ProgramSettingsController::class, 'history']);

        Route::apiResource('scenarios', SimulatorScenarioController::class)
            ->parameters(['scenarios' => 'scenario']);

        // Estaciones — solo mutaciones para admin (lectura ya está fuera del grupo admin)
        Route::post('/stations',                [StationController::class, 'store']);
        Route::put('/stations/{station}',       [StationController::class, 'update']);
        Route::delete('/stations/{station}',    [StationController::class, 'destroy']);

        // Recompensas — mutaciones solo admin (lectura ya está fuera del grupo)
        Route::post('/rewards',                  [RewardController::class, 'store']);
        Route::put('/rewards/{reward}',          [RewardController::class, 'update']);
        Route::delete('/rewards/{reward}',       [RewardController::class, 'destroy']);
        Route::post('/rewards/{reward}/image',   [RewardController::class, 'uploadImage']);
        Route::delete('/rewards/{reward}/image', [RewardController::class, 'deleteImage']);

        // Inventario de recompensas por sucursal (admin)
        Route::get('/rewards/{reward}/inventory', [RewardInventoryController::class, 'show']);
        Route::put('/rewards/{reward}/inventory', [RewardInventoryController::class, 'update']);

        // Canjes (admin) — listar todos y marcar como usado
        Route::get('/redemptions',                       [PointsController::class, 'adminIndex']);
        Route::post('/redemptions/{redemption}/mark-used', [PointsController::class, 'adminMarkUsed']);

        // Tickets (admin) — revisar y aprobar/rechazar
        Route::get('/tickets',                  [TicketController::class, 'adminIndex']);
        Route::get('/tickets/{ticket}',         [TicketController::class, 'adminShow']);
        Route::post('/tickets/{ticket}/approve', [TicketController::class, 'approve']);
        Route::post('/tickets/{ticket}/reject',  [TicketController::class, 'reject']);

        // Puntos: ajuste manual y consulta de balance de cualquier usuario
        Route::post('/usuarios/{usuario}/points-adjustment', [PointsController::class, 'adminAdjust']);
        Route::get('/usuarios/{usuario}/balance',            [PointsController::class, 'adminUserBalance']);

        // Dashboard admin (métricas agregadas)
        Route::get('/admin/dashboard', [DashboardController::class, 'index']);

        // Facturas (admin) — listado, métricas y URL firmada de descarga
        Route::get('/admin/invoices',                    [InvoiceController::class, 'adminIndex']);
        Route::get('/admin/invoices/stats',              [InvoiceController::class, 'adminStats']);
        Route::get('/admin/invoices/{invoice}/download-url', [InvoiceController::class, 'adminSignDownload']);

        // Encuestas — admin CRUD + métricas
        Route::get('/admin/surveys',                              [SurveyController::class, 'adminIndex']);
        Route::post('/admin/surveys',                             [SurveyController::class, 'store']);
        Route::get('/admin/surveys/{survey}',                     [SurveyController::class, 'adminShow']);
        Route::put('/admin/surveys/{survey}',                     [SurveyController::class, 'update']);
        Route::delete('/admin/surveys/{survey}',                  [SurveyController::class, 'destroy']);
        Route::post('/admin/surveys/{survey}/toggle',             [SurveyController::class, 'toggle']);
        Route::get('/admin/surveys/{survey}/responses',           [SurveyController::class, 'responses']);
        Route::get('/admin/surveys/{survey}/stats',               [SurveyController::class, 'stats']);
        Route::get('/admin/surveys/{survey}/station-ranking',     [SurveyController::class, 'stationRanking']);

        // Avisos push masivos
        Route::get('/admin/broadcasts',                 [BroadcastController::class, 'index']);
        Route::get('/admin/broadcasts/audience-stats',  [BroadcastController::class, 'audienceStats']);
        Route::post('/admin/broadcasts',                [BroadcastController::class, 'store']);

        // Promociones — admin (lectura completa + mutaciones)
        Route::get('/admin/promotions',                  [PromotionController::class, 'adminIndex']);
        Route::post('/promotions',                       [PromotionController::class, 'store']);
        Route::put('/promotions/{promotion}',            [PromotionController::class, 'update']);
        Route::delete('/promotions/{promotion}',         [PromotionController::class, 'destroy']);
        Route::post('/promotions/{promotion}/toggle',    [PromotionController::class, 'pause']);
    });

});