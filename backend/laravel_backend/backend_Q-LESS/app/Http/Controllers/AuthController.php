<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
            ], 200);
        }

        if (!Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'status' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Contrasena incorrecta',
            ], 200);
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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $email = Str::lower(trim((string) $request->email));
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No existe una cuenta registrada con ese correo.',
            ], 404);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $frontendUrl = rtrim((string) env('FRONTEND_URL', $request->headers->get('origin', 'http://localhost:4200')), '/');
        $resetUrl = $frontendUrl . '/reset-password?email=' . urlencode($email) . '&token=' . urlencode($token);

        try {
            Mail::html($this->passwordResetEmailHtml($user, $resetUrl), function ($message) use ($email, $user) {
                $message
                    ->to($email, $user->name)
                    ->subject('Restablece tu contrasena en Q-LESS');
            });
        } catch (\Throwable $exception) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'status' => false,
                'message' => 'No se pudo enviar el correo de recuperacion. Revisa la configuracion SMTP e intenta de nuevo.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Te enviamos un enlace para restablecer tu contrasena.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255',
            'token' => 'required|string',
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
        ]);

        $email = Str::lower(trim((string) $request->email));
        $reset = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$reset || !Hash::check((string) $request->token, $reset->token)) {
            return response()->json([
                'status' => false,
                'message' => 'El enlace de recuperacion no es valido.',
            ], 422);
        }

        if (now()->diffInMinutes(\Carbon\Carbon::parse($reset->created_at)) > 60) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'status' => false,
                'message' => 'El enlace de recuperacion expiro. Solicita uno nuevo.',
            ], 422);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No existe una cuenta registrada con ese correo.',
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.',
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

    private function passwordResetEmailHtml(User $user, string $resetUrl): string
    {
        $safeName = e($user->name);
        $safeUrl = e($resetUrl);

        return <<<HTML
        <div style="margin:0;padding:32px;background:#edf7df;font-family:Arial,sans-serif;color:#1f3d24;">
          <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #cce8b2;box-shadow:0 16px 36px rgba(47,143,53,.16);">
            <div style="padding:28px;background:linear-gradient(135deg,#35a043,#79c81a);color:#ffffff;text-align:center;">
              <h1 style="margin:0;font-size:34px;letter-spacing:1px;">Q-LESS</h1>
              <p style="margin:8px 0 0;font-size:16px;">Recuperacion de contrasena</p>
            </div>
            <div style="padding:30px;">
              <h2 style="margin:0 0 12px;color:#1f3d24;font-size:24px;">Hola, {$safeName}</h2>
              <p style="margin:0 0 16px;line-height:1.6;color:#4b5d43;">
                Recibimos una solicitud para restablecer la contrasena de tu cuenta en Q-LESS.
                Usa el siguiente boton para crear una nueva contrasena segura.
              </p>
              <div style="text-align:center;margin:28px 0;">
                <a href="{$safeUrl}" style="display:inline-block;padding:14px 24px;border-radius:12px;background:linear-gradient(135deg,#35a043,#79c81a);color:#ffffff;text-decoration:none;font-weight:700;">
                  Restablecer contrasena
                </a>
              </div>
              <p style="margin:0 0 12px;line-height:1.6;color:#4b5d43;">
                Este enlace vence en 60 minutos. Si no solicitaste este cambio, puedes ignorar este mensaje.
              </p>
              <p style="margin:18px 0 0;font-size:13px;line-height:1.5;color:#6c7a58;">
                Si el boton no funciona, copia y pega este enlace en tu navegador:<br>
                <span style="word-break:break-all;color:#2f8f35;">{$safeUrl}</span>
              </p>
            </div>
          </div>
        </div>
        HTML;
    }
}
