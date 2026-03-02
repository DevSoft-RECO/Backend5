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
        $colocacion = DB::table('datos_colocacion')
            ->where('cliente', $codigoCliente)
            ->first();

        $cumpleMora = true; // Default to true if not found in colocacion
        if ($colocacion) {
            $cumpleMora = (int) $colocacion->diasmora === 0;
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
                        'val' => $colocacion ? $colocacion->diasmora : 0,
                        'passed' => $cumpleMora,
                        'has_credit' => !!$colocacion,
                        'message' => $colocacion
                            ? ($cumpleMora ? 'Sin mora acumulada' : 'Posee mora en sus créditos')
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
                'fecha_asistencia' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            broadcast(new AsistenciaRegistrada());

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
