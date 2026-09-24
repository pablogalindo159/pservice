@extends('layouts.auth')
@section('title', 'Nova senha')
@section('content')
<h1>Nova senha</h1>
<form method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
<label>E-mail<input type="email" name="email" value="{{ $email }}" required></label>
<label>Nova senha<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
<label>Confirme<input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password"></label>
<button class="primary full">Salvar</button></form>
@endsection
