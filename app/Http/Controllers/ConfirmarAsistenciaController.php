<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Events\AsistenciaRegistrada;

class ConfirmarAsistenciaController extends Controller
{
    /**
     * Verify if a client is eligible for assistance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|integer',
        ]);

        $codigoCliente = $request->codigo_cliente;
        $cliente = Cliente::where('codigo_cliente', $codigoCliente)->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        // 1. Exact Age Calculation (Float for precision)
        $fechaNacimiento = Carbon::parse($cliente->fecha_nacimiento);
        $hoy = Carbon::now();
        $edad = $fechaNacimiento->diffInDays($hoy) / 365.25;
        $cumpleEdad = $edad >= 18;

        // 2. Aportaciones Check
        $saldoAportaciones = (float) $cliente->saldo_aportaciones;
        $cumpleAportaciones = $saldoAportaciones >= 25.0;

        // 3. Colocacion Check (Mora)
        $colocaciones = DB::table('datos_colocacion')
            ->where('cliente', $codigoCliente)
            ->get();

        $cumpleMora = true;
        $maxMora = 0;
        $hasCredits = $colocaciones->isNotEmpty();

        if ($hasCredits) {
            foreach ($colocaciones as $colocacion) {
                $moraCredito = (int) $colocacion->diasmora;
                if ($moraCredito > 0) {
                    $cumpleMora = false;
                }
                if ($moraCredito > $maxMora) {
                    $maxMora = $moraCredito;
                }
            }
        }

        $aprobado = $cumpleEdad && $cumpleAportaciones && $cumpleMora;

        return response()->json([
            'success' => true,
            'data' => [
                'approved' => $aprobado,
                'checks' => [
                    'edad' => [
                        'val' => $edad,
                        'passed' => $cumpleEdad,
                        'message' => $cumpleEdad ? 'Mayor de 18 años' : 'Menor de 18 años'
                    ],
                    'aportaciones' => [
                        'val' => $saldoAportaciones,
                        'passed' => $cumpleAportaciones,
                        'message' => $cumpleAportaciones ? 'Saldo de aportaciones suficiente' : 'Saldo de aportaciones insuficiente (< Q25.00)'
                    ],
                    'mora' => [
                        'val' => $maxMora,
                        'passed' => $cumpleMora,
                        'has_credit' => $hasCredits,
                        'credits_count' => $colocaciones->count(),
                        'credits' => $colocaciones->map(function($c) {
                            return [
                                'numero_credito' => $c->numerodocumento ?? $c->cuentadiaria ?? $c->numero_credito ?? 'N/A',
                                'diasmora' => (int) $c->diasmora
                            ];
                        }),
                        'message' => $hasCredits
                            ? ($cumpleMora ? 'Sin mora acumulada en sus ' . $colocaciones->count() . ' créditos' : 'Posee mora en uno o más de sus créditos')
                            : 'No tiene créditos'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Confirm attendance and record in DB.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|integer',
            'dpi' => 'required|string',
            'nombre_completo' => 'required|string',
            'ubicacion' => 'required|string',
            'tipo_asistencia' => 'nullable|string|in:sistema,manual',
            'edad' => 'nullable|integer',
            'genero' => 'nullable|string|max:20',
            'observacion' => 'nullable|string'
        ]);

        try {
            // Check if already registered this year by DPI
            $yaRegistrado = DB::table('confirmacion_asistencia')
                ->where('dpi', $request->dpi)
                ->whereYear('fecha_asistencia', Carbon::now()->year)
                ->exists();

            if ($yaRegistrado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya se ha registrado la asistencia para este DPI en el año actual.'
                ], 422);
            }

            $tipoAsistencia = $request->input('tipo_asistencia', 'sistema');
            $edad = $request->edad;
            $genero = $request->genero;
            $observacion = $request->observacion;

            // If it's system (automatic) and age/gender are missing, try to fetch from Cliente table
            if ($tipoAsistencia === 'sistema' && (is_null($edad) || is_null($genero))) {
                $cliente = Cliente::where('codigo_cliente', $request->codigo_cliente)->first();
                if ($cliente) {
                    $edad = $edad ?? $cliente->edad;
                    $genero = $genero ?? $cliente->genero;
                }
            }

            DB::table('confirmacion_asistencia')->insert([
                'codigo_cliente' => $request->codigo_cliente,
                'dpi' => $request->dpi,
                'nombre_completo' => $request->nombre_completo,
                'ubicacion' => $request->ubicacion,
                'edad' => $edad,
                'genero' => $genero,
                'tipo_asistencia' => $tipoAsistencia,
                'observacion' => $observacion,
                'usuario_registro' => $request->user()?->username ?? $request->user()?->name ?? 'sistema',
                'fecha_asistencia' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            try {
                broadcast(new AsistenciaRegistrada());
            } catch (\Exception $e) {
                // Log error or ignore to keep registration working even if broadcast fails
                \Log::error('Error al transmitir asistencia: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Asistencia confirmada correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar asistencia: ' . $e->getMessage()
            ], 500);
        }
    }
}
