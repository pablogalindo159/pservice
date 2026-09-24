<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0b1e3a">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>@yield('title', 'PService')</title>
<link rel="stylesheet" href="/css/pservice.css?v=7">
</head>
<body>
@php($me = auth()->user())
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <div class="side-brand"><img src="/assets/logo.jpeg" alt="PService"></div>
  <nav>
    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>⌂ <span>Dashboard</span></a>
    <a href="{{ route('os.index') }}" @class(['active' => request()->routeIs('os.*')])>▣ <span>Ordens de Serviço</span></a>
    @if($me->canAudit())<a href="{{ route('audit.index') }}" @class(['active' => request()->routeIs('audit.*')])>◷ <span>Auditoria</span></a>@endif
    @if($me->isAdmin())<a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>♙ <span>Usuários</span></a>@endif
  </nav>
  <div class="side-user"><b>{{ $me->name }}</b><small>{{ $me->role_label }}</small>
  <form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Sair</button></form></div>
</aside>
<div class="backdrop" id="backdrop"></div>
<div class="main-shell">
<header class="mobilebar"><button id="menuBtn" aria-label="Menu">☰</button><img src="/assets/logo.jpeg" alt="PService"></header>
<main class="container">
@if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="alert error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@yield('content')
</main>
</div></div>
<script src="/js/pservice.js?v=7"></script>
</body></html>
