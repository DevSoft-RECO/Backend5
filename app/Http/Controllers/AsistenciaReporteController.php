<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AsistenciaReporteController extends Controller
{
    /**
     * Display a paginated listing of assistance records.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = DB::table('confirmacion_asistencia');

        // Filter by search query (DPI or Client Code)
        if ($request->has('query') && !empty($request->input('query'))) {
            $searchValue = $request->input('query');
            $query->where(function($q) use ($searchValue) {
                $q->where('dpi', 'like', "%{$searchValue}%")
                  ->orWhere('codigo_cliente', 'like', "%{$searchValue}%")
                  ->orWhere('nombre_completo', 'like', "%{$searchValue}%");
            });
        }

        // Filter by Year
        $year = $request->input('year', Carbon::now()->year);
        $query->whereYear('fecha_asistencia', $year);

        // Sorting
        $query->orderBy('fecha_asistencia', 'desc');

        // Pagination
        $perPage = $request->input('per_page', 15);
        $asistencias = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $asistencias
        ]);
    }
}
