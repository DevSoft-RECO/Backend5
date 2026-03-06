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

    /**
     * Export assistance records to CSV.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);

        $query = DB::table('confirmacion_asistencia')
            ->whereYear('fecha_asistencia', $year)
            ->orderBy('fecha_asistencia', 'asc');

        $headers = [
            'Content-type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=asistencias_{$year}.csv",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0'
        ];

        $columns = ['ID', 'Codigo Cliente', 'DPI', 'Nombre Completo', 'Ubicacion', 'Edad', 'Género', 'Fecha Asistencia', 'Tipo Asistencia', 'Usuario Registro', 'Observación'];

        $callback = function() use ($query, $columns) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, $columns);

            $query->chunk(100, function($asistencias) use ($file) {
                foreach ($asistencias as $asistencia) {
                    fputcsv($file, [
                        $asistencia->id,
                        $asistencia->codigo_cliente,
                        $asistencia->dpi,
                        $asistencia->nombre_completo,
                        $asistencia->ubicacion,
                        $asistencia->edad,
                        $asistencia->genero,
                        $asistencia->fecha_asistencia,
                        $asistencia->tipo_asistencia,
                        $asistencia->usuario_registro,
                        $asistencia->observacion,
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Delete an assistance record.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $roles = $user->roles ?? [];

        if (!in_array('Super Admin', $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para eliminar registros.'
            ], 403);
        }

        try {
            $deleted = DB::table('confirmacion_asistencia')->where('id', $id)->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Asistencia eliminada correctamente.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}
