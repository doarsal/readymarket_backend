<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for user authentication"
 * )
 */
class AuthController extends Controller
{
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
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'phone' => 'nullable|string|max:20',
        ], [
            'password.regex' => 'La contraseña debe contener al menos: 1 mayúscula, 1 minúscula, 1 número y 1 símbolo (@$!%*?&).',
        ]);

        // Crear usuario con campos de seguridad por defecto
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'is_active' => true,
            'is_verified' => false, // Requiere verificación por OTP
            'password_changed_at' => now(),
            'role' => 'user', // Rol por defecto
            'failed_login_attempts' => 0,
            'created_by_ip' => $request->ip(),
        ]);

        // Generate and send verification code
        $verificationCode = EmailVerificationCode::generateCode($user->email, $request->ip());

        // TODO: Send email with verification code
        // Verification code is NOT exposed in response for security

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente. Se ha enviado un código de verificación a tu email.',
            'user' => new UserResource($user),
            'requires_email_verification' => true
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
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de acceso incorrectos'
            ], 401);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta temporalmente bloqueada por muchos intentos fallidos. Intenta más tarde.'
            ], 423);
        }

        // Check if account is active
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta inactiva. Contacta al administrador.'
            ], 423);
        }

        // Check if email is verified
        if (!$user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Email no verificado. Por favor verifica tu email antes de iniciar sesión.',
                'requires_email_verification' => true
            ], 422);
        }

        // Validate password
        if (!Hash::check($request->password, $user->password)) {
            $user->incrementFailedLogins();

            // Log intento de login fallido
            Log::warning('Intento de login fallido', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'failed_attempts' => $user->failed_login_attempts
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de acceso incorrectos'
            ], 401);
        }

        // Reset failed login attempts on successful login
        $user->resetFailedLogins();

        // Update last login info
        $user->updateLastLogin($request->ip());

        // Check if password needs to be changed
        if ($user->needsPasswordChange()) {
            return response()->json([
                'success' => false,
                'message' => 'Cambio de contraseña requerido',
                'requires_password_change' => true
            ], 422);
        }

        // Revocar tokens anteriores (opcional)
        $user->tokens()->delete();

        $token = $user->createToken('marketplace-token')->plainTextToken;

        // Log login exitoso
        Log::info('Login exitoso', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        // Merge cart on login (if user has items in session cart)
        try {
            $guestCartToken = request()->header('X-Cart-Token');
            $cartService = new \App\Services\CartService();
            $cartService->mergeCartOnLogin($user->id, $guestCartToken);

            // Clean up any duplicate carts for this user
            $cartService->cleanupUserCarts($user->id);
        } catch (\Exception $e) {
            // Log error but don't fail login
            Log::warning('Cart merge failed on login', [
                'user_id' => $user->id,
                'guest_cart_token' => request()->header('X-Cart-Token'),
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($user),
                'token' => $token
            ],
            'message' => 'Login exitoso'
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
            'message' => 'Logout exitoso'
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
            'data' => [
                'user' => new UserResource($user),
                'needs_password_change' => $user->needsPasswordChange(),
            ],
            'message' => 'Usuario autenticado'
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
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Contraseña actual incorrecta'
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
            'message' => 'Contraseña cambiada exitosamente. Por favor, inicia sesión nuevamente.'
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
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'El email ya está verificado'
            ], 400);
        }

        if (EmailVerificationCode::verifyCode($request->email, $request->code)) {
            $user->update([
                'is_verified' => true,
                'email_verified_at' => now()
            ]);

            // Update last login info for first verification
            $user->updateLastLogin($request->ip());

            // Create token for verified user
            $token = $user->createToken('marketplace-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email verificado exitosamente',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'email' => $user->email,
                        'is_verified' => $user->is_verified,
                        'email_verified_at' => $user->email_verified_at,
                    ],
                    'token' => $token
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Código de verificación inválido o expirado'
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
                'message' => 'El email ya está verificado'
            ], 400);
        }

        // Generate and send new verification code
        $verificationCode = EmailVerificationCode::generateCode($user->email, $request->ip());

        // TODO: Send email with verification code

        return response()->json([
            'success' => true,
            'message' => 'Nuevo código de verificación enviado',
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
        ]);

        // TODO: Implement password reset functionality
        // For now, just return success message

        return response()->json([
            'success' => true,
            'message' => 'Si el email existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.'
        ]);
    }
}
