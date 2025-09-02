<?php

use Illuminate\Support\Facades\Route;
use App\Models\PaymentSession;

// Redireccionar a login si no está autenticado
Route::get('/', function () {
    if (auth()->check() && auth()->user()->role === 'admin') {
        return redirect('/api/documentation');
    }
    return redirect('/login');
});

// Login
Route::get('/login', function () {
    if (auth()->check() && auth()->user()->role === 'admin') {
        return redirect('/api/documentation');
    }
    return view('login');
})->name('login');

Route::post('/login', function (Illuminate\Http\Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (auth()->attempt($request->only('email', 'password'))) {
        if (auth()->user()->role !== 'admin') {
            auth()->logout();
            return back()->withErrors(['email' => 'No tienes permisos.']);
        }
        return redirect('/api/documentation');
    }

    return back()->withErrors(['email' => 'Credenciales incorrectas.']);
})->name('login.post');

Route::post('/logout', function () {
    auth()->logout();
    return redirect('/login');
})->name('logout');

// Rutas de documentación protegidas - SIN extensión .json
Route::middleware(['web', 'auth', 'super.admin'])->group(function () {
    Route::get('/api-docs', function () {
        $jsonPath = storage_path('secure-docs/api-docs.json');
        if (file_exists($jsonPath)) {
            return response()->file($jsonPath, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        }
        abort(404);
    });

    Route::get('/docs/api-docs', function () {
        $jsonPath = storage_path('secure-docs/api-docs.json');
        if (file_exists($jsonPath)) {
            return response()->file($jsonPath, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        }
        abort(404);
    });
});

// Ruta PÚBLICA para MITEC
Route::get('/mitec-payment/{transactionReference}', function ($transactionReference) {
    PaymentSession::cleanExpired();

    $paymentSession = PaymentSession::where('transaction_reference', $transactionReference)->first();

    if (!$paymentSession || $paymentSession->isExpired()) {
        abort(404, 'Transacción no encontrada o expirada');
    }

    $formHtml = $paymentSession->form_html;

    return response($formHtml)->header('Content-Type', 'text/html');
})->name('mitec.payment.form');
