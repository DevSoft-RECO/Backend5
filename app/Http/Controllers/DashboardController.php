<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Candidato;

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

        // Stats for assistance
        $total = DB::table('confirmacion_asistencia')
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

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'sistema' => $sistema,
                'manual' => $manual,
                'year' => $year,
                'candidatos' => $candidatos
            ]
        ]);
    }
}
