<?php

namespace App\Http\Controllers;

class CategoriaController extends Controller
{
    public function index()
    {
        return response()->json([
            'ok' => true,
            'mensaje' => 'La ruta funciona'
        ]);
    }
}