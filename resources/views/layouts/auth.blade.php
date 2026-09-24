<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0b1e3a"><link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/icons/icon-192.png"><link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<title>@yield('title', 'PService')</title><link rel="stylesheet" href="/css/pservice.css?v=2"></head>
<body class="auth-page"><div class="auth-card"><img src="/assets/logo.jpeg" alt="PService">
@if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
@yield('content')
</div><script>if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js');</script></body></html>
