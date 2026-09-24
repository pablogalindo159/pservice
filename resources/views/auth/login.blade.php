@extends('layouts.auth')
@section('content')
<h1>PService</h1><p>Registro fotográfico de OS</p>
<form method="post" action="{{ route('login.attempt') }}">@csrf
<label>E-mail<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
<label>Senha<input type="password" name="password" required autocomplete="current-password"></label>
<label class="check"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
<button class="primary full">Entrar</button></form>
<a class="forgot" href="{{ route('password.request') }}">Esqueci minha senha</a>
@endsection
