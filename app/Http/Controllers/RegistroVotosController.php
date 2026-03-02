<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Candidato;
use App\Models\Urna;
use App\Models\ResultadoVoto;

class RegistroVotosController extends Controller
{
    /**
     * Get all candidates with their votes for a specific urn.
     */
    public function getVotosByUrna($urna_id)
    {
        $urna = Urna::findOrFail($urna_id);

        // Get all candidates
        $candidatos = Candidato::all()->map(function($candidato) use ($urna_id) {
            // Find votes for this candidate in this urn
            $resultado = ResultadoVoto::where('urna_id', $urna_id)
                ->where('candidato_id', $candidato->id)
                ->first();

            return [
                'id' => $candidato->id,
                'nombre_completo' => $candidato->nombre_completo,
                'votos' => $resultado ? $resultado->votos : 0,
                'foto_url' => $candidato->foto_path ? asset($candidato->foto_path) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'urna' => [
                    'id' => $urna->id,
                    'nombre' => $urna->nombre,
                    'votos_nulos' => $urna->votos_nulos,
                    'votos_blancos' => $urna->votos_blancos,
                ],
                'candidatos' => $candidatos
            ]
        ]);
    }

    /**
     * Save all votes for a specific urn in one batch.
     */
    public function saveVotosByUrna(Request $request, $urna_id)
    {
        $request->validate([
            'votos_nulos' => 'required|integer|min:0',
            'votos_blancos' => 'required|integer|min:0',
            'votos_candidatos' => 'required|array',
            'votos_candidatos.*.id' => 'required|exists:candidatos,id',
            'votos_candidatos.*.votos' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Update Urna (Null and Blank votes)
            $urna = Urna::findOrFail($urna_id);
            $urna->update([
                'votos_nulos' => $request->votos_nulos,
                'votos_blancos' => $request->votos_blancos,
            ]);

            // Update individual candidate votes for this urn
            foreach ($request->votos_candidatos as $voto) {
                ResultadoVoto::updateOrCreate(
                    ['urna_id' => $urna_id, 'candidato_id' => $voto['id']],
                    ['votos' => $voto['votos']]
                );
            }

            DB::commit();

            // Disparar actualización en tiempo real pasando un payload (p.ej. el ID de la urna o flag)
            \App\Events\VotesUpdated::dispatch(['urna_id' => $urna_id, 'status' => 'updated']);

            return response()->json([
                'success' => true,
                'message' => 'Resultados de la urna actualizados correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los resultados: ' . $e->getMessage()
            ], 500);
        }
    }
}
