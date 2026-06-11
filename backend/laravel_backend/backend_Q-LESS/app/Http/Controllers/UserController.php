<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'users' => User::query()
                ->orderByRaw("rol = 'admin' DESC")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => $this->serializeUser($user))
                ->values(),
        ]);
    }

    public function show(User $user)
    {
        return response()->json([
            'status' => true,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telefono' => ['sometimes', 'nullable', 'string', 'min:7', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).+$/',
            ],
            'photo' => ['nullable', 'image', 'max:4096'],
        ], [
            'email.email' => 'El correo no tiene un formato valido.',
            'email.unique' => 'Este correo ya esta registrado.',
            'password.min' => 'La contrasena debe tener minimo 8 caracteres.',
            'password.regex' => 'La contrasena debe incluir mayuscula, minuscula, numero y simbolo especial.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
            'telefono.regex' => 'El telefono solo puede contener numeros, espacios, parentesis, + o -.',
            'photo.image' => 'La foto debe ser una imagen valida.',
            'photo.max' => 'La foto no puede superar 4 MB.',
        ]);

        if ($request->has('name') && trim((string) ($data['name'] ?? '')) !== '') {
            $user->name = trim((string) $data['name']);
        }

        if ($request->has('email') && trim((string) ($data['email'] ?? '')) !== '') {
            $user->email = trim((string) $data['email']);
        }

        if ($request->has('telefono')) {
            $user->telefono = $data['telefono'] ?? null;
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if ($request->hasFile('photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $user->profile_photo_path = $request->file('photo')->store('profile-photos', 'public');
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Perfil actualizado correctamente.',
            'user' => $this->serializeUser($user),
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->rol === 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'No se puede eliminar una cuenta administradora.',
            ], 422);
        }

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telefono' => $user->telefono,
            'rol' => $user->rol,
            'profile_photo_path' => $user->profile_photo_path,
            'profile_photo_url' => $this->profilePhotoUrl($user),
            'created_at' => optional($user->created_at)->toISOString(),
            'updated_at' => optional($user->updated_at)->toISOString(),
        ];
    }

    private function profilePhotoUrl(User $user): ?string
    {
        if (!$user->profile_photo_path) {
            return null;
        }

        $backendUrl = rtrim((string) config('services.app_urls.backend_url'), '/');
        $version = optional($user->updated_at)->timestamp ?: time();

        return $backendUrl . Storage::url($user->profile_photo_path) . '?v=' . $version;
    }
}
