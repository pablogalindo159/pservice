<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $wait = RateLimiter::availableIn($key);

            return back()->withErrors(['email' => "Muitas tentativas. Aguarde {$wait} segundos."])->onlyInput('email');
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->active || ! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'E-mail ou senha inválidos ou usuário inativo.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget(['geo', 'geo_logged']);
        AuditLog::record('auth.login', null, null, \App\Support\Geo::appliesTo($user)
            ? ['area_restrita' => true, 'obs' => 'localização verificada em seguida']
            : []);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
