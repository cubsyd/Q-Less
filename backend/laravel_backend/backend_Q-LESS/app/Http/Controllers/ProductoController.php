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

    private function storeUploadedFile($file): string
    {
        $directory = public_path('storage/productos');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return url('storage/productos/' . $filename);
    }

    private function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        return $this->storeUploadedFile($request->file('image'));
    }

    private function storeImages(Request $request): array
    {
        $images = [];

        if ($request->hasFile('images')) {
            foreach ((array) $request->file('images') as $file) {
                if ($file) {
                    $images[] = $this->storeUploadedFile($file);
                }
            }
        }

        if (empty($images) && $request->hasFile('image')) {
            $images[] = $this->storeUploadedFile($request->file('image'));
        }

        return array_slice($images, 0, 5);
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

    private function deleteImagesIfExist(?array $imagePaths): void
    {
        foreach ($imagePaths ?? [] as $imagePath) {
            $this->deleteImageIfExists($imagePath);
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
            'image' => 'required_without:images|nullable|image|mimes:jpg,jpeg,png|max:5120',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:5120',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $this->resolveCategoria($data);
        $data['stock'] = max(0, (int) $data['stock']);

        $imagePaths = $this->storeImages($request);
        if (!empty($imagePaths)) {
            $data['image_path'] = $imagePaths[0];
            $data['product_images'] = $imagePaths;
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
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:5120',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $this->resolveCategoria($data);

        if (array_key_exists('stock', $data)) {
            $data['stock'] = max(0, (int) $data['stock']);
        }

        $imagePaths = $this->storeImages($request);
        if (!empty($imagePaths)) {
            $this->deleteImagesIfExist($producto->product_images ?: [$producto->image_path]);
            $data['image_path'] = $imagePaths[0];
            $data['product_images'] = $imagePaths;
        }

        $producto->update($data);

        return response()->json($producto);
    }

    public function destroy(string $id)
    {
        $producto = Producto::findOrFail($id);

        $this->deleteImagesIfExist($producto->product_images ?: [$producto->image_path]);

        $producto->delete();

        return response()->json(['message' => 'Producto eliminado']);
    }
}
