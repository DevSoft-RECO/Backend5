<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        $candidatos = DB::table('candidatos')
            ->join('urnas', 'candidatos.urna_id', '=', 'urnas.id')
            ->where('candidatos.anio', $year)
            ->select('candidatos.*', 'urnas.nombre as urna_nombre')
            ->orderBy('total_votos', 'desc')
            ->get();

        $candidatos->transform(function($candidato) {
            $candidato->foto_url = $candidato->foto_path ? asset($candidato->foto_path) : null;
            return $candidato;
        });

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
