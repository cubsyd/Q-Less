<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Mail\VerifyEmailMail;

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

        if ($this->emailVerificationEnabled() && !$isAdmin) {
            $userData['email_verified_at'] = null;
            $userData['email_verification_token'] = Str::random(64);
            $userData['email_verification_sent_at'] = now();
        } elseif ($this->emailVerificationEnabled()) {
            $userData['email_verified_at'] = now();
            $userData['email_verification_token'] = null;
            $userData['email_verification_sent_at'] = null;
        }

        $user = User::create($userData);
        $emailResult = $isAdmin || !$this->emailVerificationEnabled()
            ? ['sent' => false, 'error' => null]
            : $this->sendVerificationEmail($user);

        return response()->json([
            'status' => true,
            'message' => $isAdmin
                ? 'Usuario administrador registrado correctamente.'
                : 'Usuario registrado correctamente. Revisa tu correo para verificar la cuenta.',
            'user' => $this->serializeUser($user),
            'email_verification_required' => !$isAdmin && $this->emailVerificationEnabled(),
            'email_sent' => $emailResult['sent'],
            'email_error' => $emailResult['error'],
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

        if ($this->emailVerificationEnabled() && !$user->email_verified_at && $user->email !== $this->adminEmail) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'status' => false,
                'message' => 'Debes verificar tu correo antes de iniciar sesion.',
                'email_verification_required' => true,
            ], 403);
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

    public function verifyEmail(string $token)
    {
        if (!$this->emailVerificationEnabled()) {
            return redirect($this->frontendUrl('/email-verificado?status=success'));
        }

        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return redirect($this->frontendUrl('/email-verificado?status=invalid'));
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_sent_at' => null,
        ])->save();

        return redirect($this->frontendUrl('/email-verificado?status=success'));
    }

    public function resendVerificationEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        if (!$this->emailVerificationEnabled()) {
            return response()->json([
                'status' => true,
                'message' => 'La verificacion de correo esta desactivada. Ya puedes iniciar sesion.',
                'email_sent' => false,
            ]);
        }

        $user = User::where('email', Str::lower(trim((string) $data['email'])))->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No encontramos una cuenta con ese correo.',
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'status' => true,
                'message' => 'Este correo ya esta verificado. Ya puedes iniciar sesion.',
                'email_sent' => false,
            ]);
        }

        $user->forceFill([
            'email_verification_token' => Str::random(64),
            'email_verification_sent_at' => now(),
        ])->save();

        $emailResult = $this->sendVerificationEmail($user);

        return response()->json([
            'status' => $emailResult['sent'],
            'message' => $emailResult['sent']
                ? 'Correo de verificacion enviado nuevamente.'
                : 'No se pudo enviar el correo de verificacion.',
            'email_sent' => $emailResult['sent'],
            'email_error' => $emailResult['error'],
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

    private function sendVerificationEmail(User $user): array
    {
        $brevoApiKey = trim((string) config('services.brevo.api_key'));

        if ($brevoApiKey !== '') {
            return $this->sendVerificationEmailWithBrevo($user, $brevoApiKey);
        }

        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user));

            return [
                'sent' => true,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el correo de verificacion.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function sendVerificationEmailWithBrevo(User $user, string $apiKey): array
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        if ($fromAddress === '' || $fromAddress === 'hello@example.com') {
            return [
                'sent' => false,
                'error' => 'MAIL_FROM_ADDRESS debe ser un correo real y verificado en Brevo.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'accept' => 'application/json',
            ])
                ->asJson()
                ->timeout(15)
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $fromName !== '' ? $fromName : 'Q-LESS',
                        'email' => $fromAddress,
                    ],
                    'to' => [
                        [
                            'email' => $user->email,
                            'name' => $user->name,
                        ],
                    ],
                    'subject' => 'Verifica tu correo en Q-LESS',
                    'htmlContent' => $this->verificationEmailHtml($user),
                ]);

            if ($response->successful()) {
                return [
                    'sent' => true,
                    'error' => null,
                ];
            }

            Log::warning('Brevo no acepto el correo de verificacion.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'sent' => false,
                'error' => 'Brevo rechazo el correo: ' . $response->body(),
            ];
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el correo de verificacion por Brevo API.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function verificationEmailHtml(User $user): string
    {
        $name = htmlspecialchars((string) $user->name, ENT_QUOTES, 'UTF-8');
        $verificationUrl = htmlspecialchars($this->verificationUrl($user), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verifica tu correo - Q-LESS</title>
</head>
<body>
    <h2>Q-LESS</h2>
    <p>Hola {$name},</p>
    <p>Para activar tu cuenta, confirma tu correo electronico con el siguiente enlace:</p>
    <p><a href="{$verificationUrl}">Verificar correo</a></p>
    <p>Si el boton no funciona, copia y pega este enlace en tu navegador:</p>
    <p>{$verificationUrl}</p>
</body>
</html>
HTML;
    }

    private function verificationUrl(User $user): string
    {
        $backendUrl = rtrim((string) config('services.app_urls.backend_url'), '/');

        return $backendUrl . '/api/email/verify/' . $user->email_verification_token;
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
