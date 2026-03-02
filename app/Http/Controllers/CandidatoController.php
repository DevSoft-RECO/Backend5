<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CandidatoController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('candidatos');

        if ($request->has('anio')) {
            $query->where('anio', $request->anio);
        }

        $candidatos = $query->get();

        // Formatear la URL de la foto para el frontend
        $candidatos->transform(function($candidato) {
            $candidato->foto_url = $candidato->foto_path ? asset($candidato->foto_path) : null;
            return $candidato;
        });

        return response()->json(['success' => true, 'data' => $candidatos]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_completo' => 'required|string',
            'anio' => 'required|integer',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,avif,webp|max:2048'
        ]);

        $foto_path = null;
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = time() . '_' . $file->getClientOriginalName();
            $destinationPath = public_path('uploads/candidatos');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);
            $foto_path = 'uploads/candidatos/' . $filename;
        }

        $id = DB::table('candidatos')->insertGetId([
            'nombre_completo' => $request->nombre_completo,
            'anio' => $request->anio,
            'foto_path' => $foto_path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Candidato registrado correctamente',
            'id' => $id
        ]);
    }

    public function show($id)
    {
        $candidato = DB::table('candidatos')
            ->where('id', $id)
            ->first();

        if (!$candidato) {
            return response()->json(['success' => false, 'message' => 'Candidato no encontrado'], 404);
        }

        $candidato->foto_url = $candidato->foto_path ? asset($candidato->foto_path) : null;

        return response()->json(['success' => true, 'data' => $candidato]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre_completo' => 'required|string',
            'anio' => 'required|integer',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,avif,webp|max:2048'
        ]);

        $candidato = DB::table('candidatos')->where('id', $id)->first();
        if (!$candidato) {
            return response()->json(['success' => false, 'message' => 'Candidato no encontrado'], 404);
        }

        $foto_path = $candidato->foto_path;
        if ($request->hasFile('foto')) {
            if ($foto_path && File::exists(public_path($foto_path))) {
                File::delete(public_path($foto_path));
            }

            $file = $request->file('foto');
            $filename = time() . '_' . $file->getClientOriginalName();
            $destinationPath = public_path('uploads/candidatos');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);
            $foto_path = 'uploads/candidatos/' . $filename;
        }

        DB::table('candidatos')->where('id', $id)->update([
            'nombre_completo' => $request->nombre_completo,
            'anio' => $request->anio,
            'foto_path' => $foto_path,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Candidato actualizado correctamente'
        ]);
    }

    public function destroy($id)
    {
        $candidato = DB::table('candidatos')->where('id', $id)->first();
        if ($candidato && $candidato->foto_path && File::exists(public_path($candidato->foto_path))) {
            File::delete(public_path($candidato->foto_path));
        }

        DB::table('candidatos')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Candidato eliminado correctamente']);
    }
}
