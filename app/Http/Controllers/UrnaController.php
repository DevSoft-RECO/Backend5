<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UrnaController extends Controller
{
    public function index()
    {
        $urnas = DB::table('urnas')->get();
        return response()->json(['success' => true, 'data' => $urnas]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string',
            'descripcion' => 'nullable|string',
        ]);

        $id = DB::table('urnas')->insertGetId([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Urna creada correctamente',
            'id' => $id
        ]);
    }

    public function show($id)
    {
        $urna = DB::table('urnas')->where('id', $id)->first();
        if (!$urna) {
            return response()->json(['success' => false, 'message' => 'Urna no encontrada'], 404);
        }
        return response()->json(['success' => true, 'data' => $urna]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string',
            'descripcion' => 'nullable|string',
        ]);

        $updated = DB::table('urnas')->where('id', $id)->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Urna actualizada correctamente'
        ]);
    }

    public function destroy($id)
    {
        DB::table('urnas')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Urna eliminada correctamente']);
    }
}
