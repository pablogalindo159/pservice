<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;use Illuminate\Support\Facades\Password;
class ForgotPasswordController extends Controller{public function show(){return view('auth.forgot-password');}public function send(Request $request){$request->validate(['email'=>'required|email']);$status=Password::sendResetLink($request->only('email'));return $status===Password::RESET_LINK_SENT?back()->with('ok','Se o e-mail estiver cadastrado, o link foi enviado.'):back()->withErrors(['email'=>'Não foi possível enviar o link agora.']);}}
