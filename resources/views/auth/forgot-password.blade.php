@extends('layouts.auth')
@section('title', 'Recuperar senha')
@section('content')
<h1>Recuperar senha</h1>
<form method="post" action="{{ route('password.email') }}">@csrf<label>E-mail<input type="email" name="email" required></label><button class="primary full">Enviar link</button></form>
<a class="forgot" href="{{ route('login') }}">Voltar</a>
@endsection
