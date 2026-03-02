<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistroVotosController extends Controller
{
    /**
     * Get candidates filtered by Urna.
     */
    public function getCandidatosByUrna(Request $request)
    {
        $request->validate([
            'urna_id' => 'required|exists:urnas,id'
        ]);

        $candidatos = DB::table('candidatos')
            ->where('urna_id', $request->urna_id)
            ->get();

        // Add full URL for photos
        $candidatos->transform(function($candidato) {
            $candidato->foto_url = $candidato->foto_path ? asset($candidato->foto_path) : null;
            return $candidato;
        });

        return response()->json([
            'success' => true,
            'data' => $candidatos
        ]);
    }

    /**
     * Update the total votes for a candidate.
     */
    public function updateVotos(Request $request, $id)
    {
        $request->validate([
            'total_votos' => 'required|integer|min:0'
        ]);

        $affected = DB::table('candidatos')
            ->where('id', $id)
            ->update([
                'total_votos' => $request->total_votos,
                'updated_at' => now()
            ]);

        if ($affected) {
            return response()->json([
                'success' => true,
                'message' => 'Votos actualizados correctamente'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo actualizar los votos o el candidato no existe'
        ], 404);
    }
}
