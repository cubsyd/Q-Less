<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private string $adminEmail = 'daniandrescubidesh@gmail.com';

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255', "regex:/^[\\pL\\s'-]+$/u"],
            'email' => 'required|string|email|max:255',
            'telefono' => 'nullable|string|min:7|max:30|regex:/^[0-9+\s()-]+$/',
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
            'telefono.regex' => 'El telefono solo puede contener numeros, espacios, parentesis, + o -.',
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
            'telefono' => $request->telefono,
            'rol' => $isAdmin ? 'admin' : $request->rol,
            'password' => Hash::make($request->password),
        ];

        if ($this->emailVerificationEnabled()) {
            $userData['email_verified_at'] = now();
            $userData['email_verification_token'] = null;
            $userData['email_verification_sent_at'] = null;
        }

        $user = User::create($userData);

        return response()->json([
            'status' => true,
            'message' => $isAdmin
                ? 'Usuario administrador registrado correctamente.'
                : 'Usuario registrado correctamente. Ya puedes iniciar sesion.',
            'user' => $user,
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
                'message' => 'Usuario no existe',
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'message' => 'Contrasena incorrecta',
            ], 401);
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
            'user' => $user,
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

    public function verifyEmail(string $token)
    {
        return redirect($this->frontendUrl('/email-verificado?status=success'));
    }

    public function resendVerificationEmail(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'La verificacion de correo esta desactivada. Ya puedes iniciar sesion.',
            'email_sent' => false,
        ]);
    }

    private function frontendUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('services.app_urls.frontend_url'), '/');

        return $baseUrl . $path;
    }

    private function emailVerificationEnabled(): bool
    {
        return Schema::hasColumn('users', 'email_verified_at')
            && Schema::hasColumn('users', 'email_verification_token')
            && Schema::hasColumn('users', 'email_verification_sent_at');
    }
}
