<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\OTPVerificationService;
use App\Services\UserRegistrationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for user authentication"
 * )
 */
class AuthController extends Controller
{
    private UserRegistrationNotificationService $notificationService;
    private OTPVerificationService              $otpService;

    public function __construct(
        UserRegistrationNotificationService $notificationService,
        OTPVerificationService $otpService
    ) {
        $this->notificationService = $notificationService;
        $this->otpService          = $otpService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Authentication"},
     *     summary="Register new user",
     *     description="Register a new user account",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="phone", type="string", example="+1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        // Validar datos
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+/',
            ],
            'phone'      => [
                'required',
                'string',
                'regex:/^52[1-9]\d{9}$/', // Formato: 52 + lada (1 dígito) + número (9 dígitos) = 12 dígitos total
            ],
        ], [
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required'  => 'Los apellidos son obligatorios.',
            'email.required'      => 'El email es obligatorio.',
            'email.email'         => 'El email es inválido.',
            'email.unique'        => 'El email ya esta registrado.',
            'password.regex'      => 'La contraseña debe contener al menos: 1 mayúscula, 1 minúscula, 1 número y 1 símbolo (@$!%*?&#).',
            'phone.required'      => 'El teléfono es obligatorio.',
            'phone.regex'         => 'El teléfono debe tener el formato: 52 + lada + número',
        ]);

        // Crear usuario con campos de seguridad por defecto
        $user = User::create([
            'first_name'            => $request->first_name,
            'last_name'             => $request->last_name,
            'email'                 => $request->email,
            'password'              => Hash::make($request->password),
            'phone'                 => $request->phone,
            'is_active'             => true,
            'is_verified'           => false, // Requiere verificación por OTP
            'password_changed_at'   => now(),
            'role'                  => 'user', // Rol por defecto
            'failed_login_attempts' => 0,
            'created_by_ip'         => $request->ip(),
        ]);

        // Generate and send verification code
        $verificationCode = EmailVerificationCode::generateCode($user->email, $request->ip());

        // Send OTP verification code
        $requiresOtpVerification = false;
        try {
            $otpCode = $this->otpService->sendOTPCode($user);

            // If OTP is disabled, the service returns 'DISABLED' and user is auto-verified
            if ($otpCode === 'DISABLED') {
                $requiresOtpVerification = false;
                Log::info('OTP verification disabled, user auto-verified', ['user_id' => $user->id]);
            } else {
                $requiresOtpVerification = true;
                Log::info('OTP verification required', ['user_id' => $user->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending OTP code during registration', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // Send user registration notifications (email & WhatsApp) if enabled
        try {
            $this->notificationService->sendNewUserRegistrationNotification($user);
        } catch (\Exception $e) {
            // Log error but don't fail the registration process
            Log::error('Failed to send user registration notifications', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // Prepare response message based on OTP status
        $message = $requiresOtpVerification ? 'Usuario registrado exitosamente. Se ha enviado un código de verificación a tu email y WhatsApp.' : 'Usuario registrado y verificado exitosamente. Ya puedes iniciar sesión.';

        return response()->json([
            'success'                   => true,
            'message'                   => $message,
            'user'                      => new UserResource($user->fresh()),
            'requires_otp_verification' => $requiresOtpVerification,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Authentication"},
     *     summary="Login user",
     *     description="Authenticate user and return access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de acceso incorrectos',
            ], 401);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta temporalmente bloqueada por muchos intentos fallidos. Intenta más tarde.',
            ], 423);
        }

        // Check if account is active
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta inactiva. Contacta al administrador.',
            ], 423);
        }

        // Check if email is verified via OTP (only if OTP is enabled)
        if (!$user->is_verified && $this->otpService->isOTPEnabled()) {
            // Check if user has a valid unexpired OTP code
            $hasValidOTP = \App\Models\EmailVerificationCode::where('email', $user->email)
                ->where('used', false)
                ->where('expires_at', '>', \Carbon\Carbon::now(config('app.timezone')))
                ->exists();

            // If no valid OTP exists, send a new one automatically (but don't notify admin)
            if (!$hasValidOTP) {
                try {
                    $this->otpService->sendOTPForReturningUser($user, $request->ip());

                    Log::info('New OTP sent to returning unverified user', [
                        'user_id' => $user->id,
                        'email'   => $user->email,
                        'ip'      => $request->ip(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send OTP to returning user', [
                        'user_id' => $user->id,
                        'email'   => $user->email,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success'                   => false,
                'message'                   => 'Cuenta no verificada. Se ha enviado un código de verificación a tu correo y WhatsApp.',
                'requires_otp_verification' => true,
                'email'                     => $user->email,
                'phone'                     => $user->phone,
            ], 422);
        }

        // Validate password
        if (!Hash::check($request->password, $user->password)) {
            $user->incrementFailedLogins();

            // Log intento de login fallido
            Log::warning('Intento de login fallido', [
                'email'           => $request->email,
                'ip'              => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'failed_attempts' => $user->failed_login_attempts,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de acceso incorrectos',
            ], 401);
        }

        // Reset failed login attempts on successful login
        $user->resetFailedLogins();

        // Update last login info
        $user->updateLastLogin($request->ip());

        // Check if password needs to be changed
        if ($user->needsPasswordChange()) {
            return response()->json([
                'success'                  => false,
                'message'                  => 'Cambio de contraseña requerido',
                'requires_password_change' => true,
            ], 422);
        }

        // Revocar tokens anteriores (opcional)
        $user->tokens()->delete();

        $token = $user->createToken('marketplace-token')->plainTextToken;

        // Log login exitoso
        Log::info('Login exitoso', [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Merge cart on login (if user has items in session cart)
        try {
            $guestCartToken = request()->header('X-Cart-Token');
            $cartService    = new \App\Services\CartService();
            $cartService->mergeCartOnLogin($user->id, $guestCartToken);

            // Clean up any duplicate carts for this user
            $cartService->cleanupUserCarts($user->id);
        } catch (\Exception $e) {
            // Log error but don't fail login
            Log::warning('Cart merge failed on login', [
                'user_id'          => $user->id,
                'guest_cart_token' => request()->header('X-Cart-Token'),
                'error'            => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user'  => new UserResource($user),
                'token' => $token,
            ],
            'message' => 'Login exitoso',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     description="Revoke user access token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Authentication"},
     *     summary="Get authenticated user",
     *     description="Get current authenticated user information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        // Cargamos las relaciones para evitar N+1 queries
        $user->load('roles');

        return response()->json([
            'success' => true,
            'data'    => [
                'user'                  => new UserResource($user),
                'needs_password_change' => $user->needsPasswordChange(),
            ],
            'message' => 'Usuario autenticado',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/change-password",
     *     tags={"Authentication"},
     *     summary="Change user password",
     *     description="Change current user password",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Contraseña actual incorrecta',
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Mark password as changed
        $user->markPasswordChanged();

        // Revoke all tokens to force re-login
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña cambiada exitosamente. Por favor, inicia sesión nuevamente.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verify-email",
     *     tags={"Authentication"},
     *     summary="Verify email with OTP",
     *     description="Verify user email with OTP code",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","code"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully"
     *     )
     * )
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code'  => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'El email ya está verificado',
            ], 400);
        }

        if (EmailVerificationCode::verifyCode($request->email, $request->code)) {
            $user->update([
                'is_verified'       => true,
                'email_verified_at' => now(),
            ]);

            // Update last login info for first verification
            $user->updateLastLogin($request->ip());

            // Create token for verified user
            $token = $user->createToken('marketplace-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email verificado exitosamente',
                'data'    => [
                    'user'  => [
                        'id'                => $user->id,
                        'name'              => $user->full_name,
                        'email'             => $user->email,
                        'is_verified'       => $user->is_verified,
                        'email_verified_at' => $user->email_verified_at,
                    ],
                    'token' => $token,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Código de verificación inválido o expirado',
        ], 400);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/resend-verification",
     *     tags={"Authentication"},
     *     summary="Resend verification code",
     *     description="Resend OTP verification code to email",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification code sent"
     *     )
     * )
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'El email ya está verificado',
            ], 400);
        }

        // Generate and send new verification code
        $verificationCode = EmailVerificationCode::generateCode($user->email, $request->ip());

        // TODO: Send email with verification code

        return response()->json([
            'success'           => true,
            'message'           => 'Nuevo código de verificación enviado',
            'verification_code' => $verificationCode, // Remove this in production
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/forgot-password",
     *     tags={"Authentication"},
     *     summary="Send password reset email",
     *     description="Send password reset link to user email",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset email sent"
     *     )
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No encontramos una cuenta con este correo electrónico.',
        ]);

        // Generate password reset token
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos una cuenta con este correo electrónico.',
            ], 404);
        }

        // Create password reset token
        $token = \Str::random(64);

        // Store token in database
        \DB::table('password_reset_tokens')->updateOrInsert(['email' => $request->email], [
            'token'      => \Hash::make($token),
            'created_at' => now(),
        ]);

        // Send email using service
        $emailService = new \App\Services\PasswordResetEmailService();
        $emailSent    = $emailService->sendPasswordResetEmail($user, $token);

        // Log password reset request
        \Log::info('Password reset requested', [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'email_sent' => $emailSent,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($emailSent) {
            return response()->json([
                'success' => true,
                'message' => 'Te hemos enviado un enlace de restablecimiento de contraseña a tu correo electrónico.',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo de recuperación. Por favor intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/validate-reset-token",
     *     tags={"Authentication"},
     *     summary="Validate password reset token",
     *     description="Validate password reset token and get user info",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","token"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token is valid"
     *     )
     * )
     */
    public function validateResetToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
        ]);

        // Find the password reset token
        $resetToken = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$resetToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token de restablecimiento no válido o expirado.',
            ], 400);
        }

        // Check if token is valid (not expired - 60 minutes)
        $tokenAge = now()->diffInMinutes($resetToken->created_at);
        if ($tokenAge > 60) {
            // Delete expired token
            \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'El token de restablecimiento ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        // Verify token hash
        if (!\Hash::check($request->token, $resetToken->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token de restablecimiento no válido.',
            ], 400);
        }

        // Get user info
        $user = User::where('email', $request->email)->first();

        return response()->json([
            'success' => true,
            'message' => 'Token válido',
            'data'    => [
                'user_name'                => $user->first_name . ' ' . $user->last_name,
                'user_email'               => $user->email,
                'token_expires_in_minutes' => 60 - $tokenAge,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/reset-password",
     *     tags={"Authentication"},
     *     summary="Reset password",
     *     description="Reset user password using token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","token","password","password_confirmation"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successful"
     *     )
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.exists'       => 'No encontramos una cuenta con este correo electrónico.',
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        // Find the password reset token
        $resetToken = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$resetToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token de restablecimiento no válido o expirado.',
            ], 400);
        }

        // Check if token is valid (not expired - 60 minutes)
        $tokenAge = now()->diffInMinutes($resetToken->created_at);
        if ($tokenAge > 60) {
            // Delete expired token
            \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'El token de restablecimiento ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        // Verify token hash
        if (!\Hash::check($request->token, $resetToken->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token de restablecimiento no válido.',
            ], 400);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password'              => \Hash::make($request->password),
            'force_password_change' => false, // Reset flag if it existed
        ]);

        // Delete the token
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Log password reset
        \Log::info('Password reset successful', [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tu contraseña ha sido restablecida exitosamente.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verify-otp",
     *     tags={"Authentication"},
     *     summary="Verify OTP code",
     *     description="Verify user account using OTP code",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","otp_code"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="otp_code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid or expired OTP"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function verifyOTP(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            $verified = $this->otpService->verifyOTPCode($user, $request->otp_code);

            if (!$verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código OTP inválido o expirado.',
                ], 400);
            }

            // Revocar tokens anteriores (opcional)
            $user->tokens()->delete();

            // Crear un nuevo token para autenticar automáticamente al usuario
            $token = $user->createToken('marketplace-token')->plainTextToken;

            // Actualizar last login info
            $user->updateLastLogin($request->ip());

            return response()->json([
                'success' => true,
                'message' => 'Cuenta verificada exitosamente. Sesión iniciada automáticamente.',
                'user'    => new UserResource($user->fresh()),
                'token'   => $token,
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying OTP', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Intenta nuevamente.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/resend-otp",
     *     tags={"Authentication"},
     *     summary="Resend OTP code",
     *     description="Resend OTP verification code to user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Too many requests"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function resendOTP(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            if ($user->is_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuenta ya está verificada.',
                ], 400);
            }

            $resent = $this->otpService->resendOTPCode($user);

            if (!$resent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes esperar antes de solicitar un nuevo código. Intenta en 1 minuto.',
                ], 429);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nuevo código OTP enviado a tu email y WhatsApp.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error resending OTP', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Intenta nuevamente.',
            ], 500);
        }
    }

    /**
     * Invalidate password reset token
     */
    public function invalidateResetToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            // Delete the used token
            $deleted = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('token', $request->token)
                ->delete();

            return response()->json([
                'success'     => true,
                'message'     => 'Token invalidado correctamente',
                'invalidated' => $deleted > 0,
            ]);
        } catch (Exception $e) {
            Log::error('Error invalidating password reset token', [
                'error' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }
}
