<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CartReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductoController extends Controller
{
    public function __construct(private CartReservationService $cartReservationService)
    {
    }

    private function resolveCategoria(array &$data): void
    {
        $categoria = null;

        if (!empty($data['categoria_id'])) {
            $categoria = Categoria::find($data['categoria_id']);
        }

        if (!$categoria && !empty($data['categoria'])) {
            $categoria = Categoria::where('nombre', $data['categoria'])->first();
        }

        if ($categoria) {
            $data['categoria_id'] = $categoria->id;
            $data['categoria'] = $categoria->nombre;
        }
    }

    private function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        $directory = public_path('storage/productos');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $file = $request->file('image');
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return url('storage/productos/' . $filename);
    }

    private function deleteImageIfExists(?string $imagePath): void
    {
        if (!$imagePath) {
            return;
        }

        $paths = [
            str_replace(url('/') . '/', public_path() . DIRECTORY_SEPARATOR, $imagePath),
            public_path('storage/productos/' . basename($imagePath)),
            public_path('storage/products/' . basename($imagePath)),
            storage_path('app/public/productos/' . basename($imagePath)),
            storage_path('app/public/products/' . basename($imagePath)),
        ];

        foreach ($paths as $path) {
            if ($path && File::exists($path)) {
                File::delete($path);
            }
        }
    }

    public function index()
    {
        $this->cartReservationService->cleanupExpiredReservations();

        return response()->json(
            Producto::with(['categoriaRelacion', 'usuario'])->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required_without:categoria_id|string|in:Cuadernos y libretas,Lapices y marcadores,Cartulinas y hojas,Herramientas escolares',
            'categoria_id' => 'nullable|exists:categorias,id',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $this->resolveCategoria($data);
        $data['stock'] = max(0, (int) $data['stock']);

        $imagePath = $this->storeImage($request);
        if ($imagePath) {
            $data['image_path'] = $imagePath;
        }

        $producto = Producto::create($data);

        return response()->json($producto, 201);
    }

    public function show(string $id)
    {
        $this->cartReservationService->cleanupExpiredReservations();

        return response()->json(
            Producto::with(['categoriaRelacion', 'usuario'])->findOrFail($id)
        );
    }

    public function update(Request $request, string $id)
    {
        $producto = Producto::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'categoria' => 'nullable|string|in:Cuadernos y libretas,Lapices y marcadores,Cartulinas y hojas,Herramientas escolares',
            'categoria_id' => 'nullable|exists:categorias,id',
            'descripcion' => 'nullable|string',
            'precio' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $this->resolveCategoria($data);

        if (array_key_exists('stock', $data)) {
            $data['stock'] = max(0, (int) $data['stock']);
        }

        $imagePath = $this->storeImage($request);
        if ($imagePath) {
            $this->deleteImageIfExists($producto->image_path);
            $data['image_path'] = $imagePath;
        }

        $producto->update($data);

        return response()->json($producto);
    }

    public function destroy(string $id)
    {
        $producto = Producto::findOrFail($id);

        $this->deleteImageIfExists($producto->image_path);

        $producto->delete();

        return response()->json(['message' => 'Producto eliminado']);
    }
}
