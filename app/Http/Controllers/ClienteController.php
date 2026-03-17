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
            'query' => 'required|string',
        ]);

        $searchValue = $request->input('query');

        $cliente = Cliente::where('dpi', $searchValue)
            ->orWhere('codigo_cliente', $searchValue)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        // Search for placement data using codigo_cliente
        $colocaciones = \Illuminate\Support\Facades\DB::table('datos_colocacion')
            ->where('cliente', $cliente->codigo_cliente)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'personal' => $cliente,
                'colocacion' => $colocaciones->first(), // Maintain first for compatibility
                'colocaciones' => $colocaciones // New field for all credits
            ]
        ]);
    }

    /**
     * Search for clients by full name (partial match, case-insensitive).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function searchByName(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3',
        ]);

        $queryInput = trim($request->input('query'));
        // Split by one or more spaces
        $words = preg_split('/\s+/', $queryInput);

        $query = Cliente::query();

        // The raw SQL to concatenate all name parts into a single string
        $rawConcat = "
            TRIM(
                REPLACE(
                    CONCAT_WS(' ',
                        IFNULL(nombre1, ''),
                        IFNULL(nombre2, ''),
                        IFNULL(nombre3, ''),
                        IFNULL(apellido1, ''),
                        IFNULL(apellido2, '')
                    ),
                    '  ', ' '
                )
            )
        ";

        // Add a WHERE LIKE clause for each word user typed
        foreach ($words as $word) {
            $searchValue = '%' . $word . '%';
            $query->whereRaw("$rawConcat LIKE ?", [$searchValue]);
        }

        $clientes = $query->limit(20)->get();

        if ($clientes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron clientes con ese nombre'
            ], 404);
        }

        // We want to format the response to include the full name easily for the frontend,
        // and optionally their colocacion data (though we might just fetch colocacion upon "Verificar" to save DB hits,
        // but for consistency with 'search', let's attach it or just return the clients and the frontend can call 'search' with ID later.
        // Wait, the plan says "Clicking 'Verificar' on a result will populate the `result` ref as if it were searched by DPI/Code".
        // Let's attach colocacion to each so it's ready.

        $clientesConColocacion = $clientes->map(function ($cliente) {
            $colocaciones = \Illuminate\Support\Facades\DB::table('datos_colocacion')
                ->where('cliente', $cliente->codigo_cliente)
                ->get();

            return [
                'personal' => $cliente,
                'colocacion' => $colocaciones->first(),
                'colocaciones' => $colocaciones
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $clientesConColocacion
        ]);
    }

    /**
     * Combined search for external apps (Mother App).
     * Searches by dpi, codigo_cliente, or full name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function searchExternal(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $queryInput = trim($request->input('query'));
        
        // Check if it looks like a DPI or Code (mostly numeric)
        $isNumeric = is_numeric($queryInput);

        $query = Cliente::query();

        if ($isNumeric) {
            $query->where(function($q) use ($queryInput) {
                $q->where('dpi', $queryInput)
                  ->orWhere('codigo_cliente', $queryInput);
            });
        } else {
            $words = preg_split('/\s+/', $queryInput);
            
            // Re-using the name concatenation logic
            $rawConcat = "
                TRIM(
                    REPLACE(
                        CONCAT_WS(' ',
                            IFNULL(nombre1, ''),
                            IFNULL(nombre2, ''),
                            IFNULL(nombre3, ''),
                            IFNULL(apellido1, ''),
                            IFNULL(apellido2, '')
                        ),
                        '  ', ' '
                    )
                )
            ";

            foreach ($words as $word) {
                $searchValue = '%' . $word . '%';
                $query->whereRaw(\Illuminate\Support\Facades\DB::raw("$rawConcat LIKE ?"), [$searchValue]);
            }
        }

        // We only return the basic client information from the 'clientes' table
        $clientes = $query->limit(30)->get();

        if ($clientes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron resultados'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $clientes
        ]);
    }
}
