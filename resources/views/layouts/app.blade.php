<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#0d5bd7">
<link rel="manifest" href="/manifest.webmanifest"><title>PService</title>
<link rel="stylesheet" href="/css/pservice.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <div class="side-brand"><img src="/assets/logo.jpeg" alt="Positivo Service"></div>
  <nav>
    <a href="{{ route('dashboard') }}">⌂ <span>Dashboard</span></a>
    <a href="{{ route('os.index') }}">▣ <span>Ordens de Serviço</span></a>
    @if(auth()->user()->role==='admin')<a href="{{ route('users.index') }}">♙ <span>Usuários</span></a>@endif
  </nav>
  <div class="side-user"><b>{{ auth()->user()->name }}</b><small>{{ ucfirst(auth()->user()->role) }}</small>
  <form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Sair</button></form></div>
</aside>
<div class="main-shell">
<header class="mobilebar"><button id="menuBtn">☰</button><img src="/assets/logo.jpeg" alt="Positivo Service"></header>
<main class="container">
@if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
@yield('content')
</main>
</div></div>
<script src="/js/pservice.js"></script>
</body></html>
