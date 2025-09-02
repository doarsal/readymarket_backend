<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Mostrar formulario de login
     */
    public function showLogin()
    {
        if (Auth::check() && $this->isSuperAdmin(Auth::user())) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    /**
     * Procesar login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // Verificar que sea Super Administrator
            if (!$this->isSuperAdmin($user)) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'No tienes permisos de Super Administrator para acceder al panel.'
                ]);
            }

            $request->session()->regenerate();

            // Log del acceso exitoso
            \Log::info('Super Admin login exitoso', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return redirect()->intended(route('admin.dashboard'));
        }

        // Log del intento fallido
        \Log::warning('Intento de login fallido en panel admin', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.'
        ])->onlyInput('email');
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        \Log::info('Super Admin logout', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'ip' => $request->ip()
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('message', 'Sesión cerrada correctamente');
    }

    /**
     * Dashboard administrativo
     */
    public function dashboard()
    {
        $user = Auth::user();

        return view('admin.dashboard', [
            'user' => $user,
            'stats' => $this->getDashboardStats()
        ]);
    }

    /**
     * Verificar si es Super Administrator
     */
    private function isSuperAdmin($user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return true;
        }

        if ($user->hasPermission('access-admin-panel')) {
            return true;
        }

        return false;
    }

    /**
     * Obtener estadísticas del dashboard
     */
    private function getDashboardStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'recent_logins' => User::whereNotNull('last_login_at')
                ->where('last_login_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }
}
