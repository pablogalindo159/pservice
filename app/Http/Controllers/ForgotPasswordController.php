<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function show()
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'Aguarde um pouco antes de pedir outro link.']);
        }

        // Mesma resposta para e-mail existente ou não (evita enumeração de usuários).
        return back()->with('ok', 'Se o e-mail estiver cadastrado, o link foi enviado.');
    }
}
