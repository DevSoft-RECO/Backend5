<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;

class ClienteController extends Controller
{
    /**
     * Search for a client by DPI.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $request->validate([
            'field' => 'required|string|in:dpi,codigo_cliente',
            'query' => 'required|string',
        ]);

        $query = Cliente::query();

        if ($request->field === 'dpi') {
            $query->where('dpi', $request->input('query'));
        } else {
            $query->where('codigo_cliente', $request->input('query'));
        }

        $cliente = $query->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        // Search for placement data using codigo_cliente
        $colocacion = \Illuminate\Support\Facades\DB::table('datos_colocacion')
            ->where('cliente', $cliente->codigo_cliente)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'personal' => $cliente,
                'colocacion' => $colocacion
            ]
        ]);
    }
}
