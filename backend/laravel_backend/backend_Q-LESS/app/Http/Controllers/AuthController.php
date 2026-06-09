<?php

namespace App\Http\Controllers;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private string $adminEmail = 'daniandrescubidesh@gmail.com';

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'telefono' => 'required|string|min:7|max:30|regex:/^[0-9+\s()-]+$/',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).+$/',
            ],
        ], [
            'password.min' => 'La contrasena debe tener minimo 8 caracteres.',
            'password.regex' => 'La contrasena debe incluir mayuscula, minuscula, numero y simbolo especial.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
            'telefono.regex' => 'El telefono solo puede contener numeros, espacios, parentesis, + o -.',
        ]);

        $emailVerificationEnabled = $this->emailVerificationEnabled();
        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser) {
            if (!$emailVerificationEnabled || $existingUser->email_verified_at) {
                return response()->json([
                    'status' => false,
                    'message' => 'Este correo ya esta registrado. Inicia sesion con tu cuenta.',
                    'errors' => [
                        'email' => ['Este correo ya esta registrado.'],
                    ],
                ], 422);
            }

            if (!$existingUser->email_verification_token) {
                $existingUser->email_verification_token = Str::random(64);
            }

            $existingUser->name = $request->name;
            $existingUser->telefono = $request->telefono;
            $existingUser->password = Hash::make($request->password);
            $existingUser->email_verification_sent_at = now();
            $existingUser->save();

            $emailSent = $this->sendVerificationEmail($existingUser);

            return response()->json([
                'status' => true,
                'message' => $emailSent
                    ? 'La cuenta ya existia sin verificar. Te enviamos un nuevo correo de verificacion.'
                    : 'La cuenta ya existia sin verificar, pero no se pudo enviar el correo de verificacion.',
                'user' => $existingUser,
                'email_verification_required' => true,
                'email_sent' => $emailSent,
            ]);
        }

        $isAdmin = $request->email === $this->adminEmail;

        if (!$isAdmin && !$emailVerificationEnabled) {
            Log::error('La verificacion de correo no esta habilitada porque faltan columnas en users.');

            return response()->json([
                'status' => false,
                'message' => 'No se pudo preparar la verificacion de correo. Intenta de nuevo en unos minutos.',
                'email_verification_required' => true,
            ], 503);
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'rol' => $isAdmin ? 'admin' : 'usuario',
            'password' => Hash::make($request->password),
        ];

        if ($emailVerificationEnabled) {
            $userData['email_verified_at'] = $isAdmin ? now() : null;
            $userData['email_verification_token'] = $isAdmin ? null : Str::random(64);
            $userData['email_verification_sent_at'] = $isAdmin ? null : now();
        }

        $user = User::create($userData);

        $emailSent = $isAdmin || $this->sendVerificationEmail($user);

        return response()->json([
            'status' => $emailSent,
            'message' => $isAdmin
                ? 'Usuario administrador registrado correctamente.'
                : ($emailSent
                    ? 'Usuario registrado correctamente. Revisa tu correo para verificar la cuenta.'
                    : 'Usuario registrado, pero no se pudo enviar el correo de verificacion. Usa la opcion de reenviar correo desde el login.'),
            'user' => $user,
            'email_verification_required' => $emailVerificationEnabled && !$isAdmin,
            'email_sent' => $emailSent,
        ], $emailSent ? 201 : 202);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).+$/',
            ],
        ], [
            'password.min' => 'La contrasena debe tener minimo 8 caracteres.',
            'password.regex' => 'La contrasena debe incluir mayuscula, minuscula, numero y simbolo especial.',
        ]);

        $key = 'login-attempts-' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'status' => false,
                'message' => 'Demasiados intentos. Intenta nuevamente en 1 minuto.',
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

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

        if ($this->emailVerificationEnabled() && $user->email_verification_token && !$user->email_verified_at) {
            return response()->json([
                'status' => false,
                'message' => 'Debes verificar tu correo antes de iniciar sesion. Revisa tu bandeja de entrada.',
                'email_verification_required' => true,
            ], 403);
        }

        RateLimiter::clear($key);

        $role = $user->email === $this->adminEmail ? 'admin' : 'usuario';

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
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return redirect($this->frontendUrl('/email-verificado?status=invalid'));
        }

        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        return redirect($this->frontendUrl('/email-verificado?status=success'));
    }

    public function resendVerificationEmail(Request $request)
    {
        if (!$this->emailVerificationEnabled()) {
            return response()->json([
                'status' => false,
                'message' => 'La verificacion de correo no esta habilitada en esta base de datos.',
            ], 422);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json([
                'status' => true,
                'message' => 'Este correo ya esta verificado.',
            ]);
        }

        if (!$user->email_verification_token) {
            $user->email_verification_token = Str::random(64);
        }

        $user->email_verification_sent_at = now();
        $user->save();

        $emailSent = $this->sendVerificationEmail($user);

        return response()->json([
            'status' => $emailSent,
            'message' => $emailSent
                ? 'Correo de verificacion reenviado.'
                : 'No se pudo enviar el correo de verificacion.',
            'email_sent' => $emailSent,
        ], $emailSent ? 200 : 500);
    }

    private function sendVerificationEmail(User $user): bool
    {
        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el correo de verificacion.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
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
