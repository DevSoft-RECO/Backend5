<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Candidato;
use App\Models\Urna;

class DashboardController extends Controller
{
    /**
     * Get assistance metrics for the dashboard.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $year = Carbon::now()->year;

        // Stats for assistance (Attendance)
        $total_asistencia = DB::table('confirmacion_asistencia')
            ->whereYear('fecha_asistencia', $year)
            ->count();

        $sistema = DB::table('confirmacion_asistencia')
            ->whereYear('fecha_asistencia', $year)
            ->where('tipo_asistencia', 'sistema')
            ->count();

        $manual = DB::table('confirmacion_asistencia')
            ->whereYear('fecha_asistencia', $year)
            ->where('tipo_asistencia', 'manual')
            ->count();

        // Vote Stats (Aggregated from all urnas)
        $votos_nulos = (int) Urna::sum('votos_nulos');
        $votos_blancos = (int) Urna::sum('votos_blancos');

        // Stats for candidates (aggregated from all urnas)
        $candidatos = Candidato::where('anio', $year)
            ->get()
            ->map(function($candidato) {
                $totalVotos = DB::table('resultados_votos')
                    ->where('candidato_id', $candidato->id)
                    ->sum('votos');

                return [
                    'id' => $candidato->id,
                    'nombre_completo' => $candidato->nombre_completo,
                    'total_votos' => (int)$totalVotos,
                    'foto_url' => $candidato->foto_path ? asset($candidato->foto_path) : null,
                ];
            })
            ->sortByDesc('total_votos')
            ->values();

        $votos_validos = $candidatos->sum('total_votos');
        $total_votantes = $votos_validos + $votos_nulos + $votos_blancos;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total_asistencia, // Historically attendance
                'sistema' => $sistema,
                'manual' => $manual,
                'year' => $year,
                'candidatos' => $candidatos,
                'votos_validos' => $votos_validos,
                'votos_nulos' => $votos_nulos,
                'votos_blancos' => $votos_blancos,
                'total_votantes' => $total_votantes
            ]
        ]);
    }
}
