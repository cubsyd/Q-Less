<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private string $adminEmail = 'daniandrescubidesh@gmail.com';

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255', "regex:/^[\\pL\\s'-]+$/u"],
            'email' => 'required|string|email|max:255',
            'rol' => 'required|string|in:aprendiz,instructor',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).+$/',
            ],
        ], [
            'name.min' => 'El nombre debe tener minimo 3 caracteres.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'password.min' => 'La contrasena debe tener minimo 8 caracteres.',
            'password.regex' => 'La contrasena debe incluir mayuscula, minuscula, numero y simbolo especial.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
            'rol.in' => 'Selecciona si eres aprendiz o instructor.',
        ]);

        $email = Str::lower(trim((string) $request->email));

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            return response()->json([
                'status' => false,
                'message' => 'Este correo ya esta registrado. Inicia sesion con tu cuenta.',
                'errors' => [
                    'email' => ['Este correo ya esta registrado.'],
                ],
            ], 422);
        }

        $isAdmin = $email === $this->adminEmail;

        $userData = [
            'name' => trim((string) $request->name),
            'email' => $email,
            'rol' => $isAdmin ? 'admin' : $request->rol,
            'password' => Hash::make($request->password),
        ];

        $user = User::create($userData);

        return response()->json([
            'status' => true,
            'message' => $isAdmin
                ? 'Usuario administrador registrado correctamente.'
                : 'Usuario registrado correctamente. Ya puedes iniciar sesion.',
            'user' => $this->serializeUser($user),
            'email_verification_required' => false,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
            ],
        ], [
            'password.min' => 'La contrasena debe tener minimo 8 caracteres.',
        ]);

        $key = 'login-attempts-' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'status' => false,
                'message' => 'Demasiados intentos. Intenta nuevamente en 1 minuto.',
            ], 429);
        }

        $user = User::where('email', Str::lower(trim((string) $request->email)))->first();

        if (!$user) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'status' => false,
                'code' => 'USER_NOT_FOUND',
                'message' => 'Usuario no existe',
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'status' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Contrasena incorrecta',
            ]);
        }

        RateLimiter::clear($key);

        $role = $user->email === $this->adminEmail
            ? 'admin'
            : (in_array($user->rol, ['aprendiz', 'instructor'], true) ? $user->rol : 'aprendiz');

        if ($user->rol !== $role) {
            $user->rol = $role;
            $user->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Login exitoso',
            'token' => Str::random(64),
            'user' => $this->serializeUser($user),
            'role' => $role,
        ]);
    }

    public function logout()
    {
        return response()->json([
            'status' => true,
            'message' => 'Sesion cerrada',
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->rol,
            'bio' => $user->bio,
            'profile_photo_url' => $user->profile_photo_path,
        ];
    }
}
