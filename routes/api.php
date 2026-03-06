<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http; // Added this use statement

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
Route::middleware('sso')->group(function () {
    Route::get('/me', function (Request $request) {
        // ... (existing /me logic) ...
        $token = $request->bearerToken();
        $madreUrl = config('services.app_madre.url') ?? env('APP_MADRE_URL');

        try {
            $response = Http::withToken($token)
                ->get($madreUrl . '/api/user');

            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json(['message' => 'Error validando con App Madre'], $response->status());
            }
        } catch (\Exception $e) {
             return response()->json(['message' => 'Error de conexión con App Madre: ' . $e->getMessage()], 500);
        }
    });

    // Rutas protegidas por SSO
    Route::post('/clientes/search', [App\Http\Controllers\ClienteController::class, 'search']);
    Route::post('/clientes/search-name', [App\Http\Controllers\ClienteController::class, 'searchByName']);
    Route::post('/asistencia/verificar', [App\Http\Controllers\ConfirmarAsistenciaController::class, 'verify']);
    Route::post('/asistencia/confirmar', [App\Http\Controllers\ConfirmarAsistenciaController::class, 'confirm']);
    Route::get('/asistencia/reporte', [App\Http\Controllers\AsistenciaReporteController::class, 'index']);
    Route::get('/asistencia/export', [App\Http\Controllers\AsistenciaReporteController::class, 'export']);
    Route::get('/dashboard/stats', [App\Http\Controllers\DashboardController::class, 'stats']);

    Route::apiResource('urnas', App\Http\Controllers\UrnaController::class);
    Route::apiResource('candidatos', App\Http\Controllers\CandidatoController::class);

    Route::get('/votos/urna/{urna_id}', [App\Http\Controllers\RegistroVotosController::class, 'getVotosByUrna']);
    Route::post('/votos/urna/{urna_id}/guardar', [App\Http\Controllers\RegistroVotosController::class, 'saveVotosByUrna']);

    // Import Routes (potentially not protected or kept outside if needed)
Route::post('/import/upload', [App\Http\Controllers\ImportController::class, 'upload']);
Route::post('/import/status/{id}', [App\Http\Controllers\ImportController::class, 'status']);
});


