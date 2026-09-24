@extends('layouts.auth')
@section('title', 'Fora da área · PService')
@section('content')
@php
    $outside = is_array($geo) && ! ($geo['inside'] ?? false) && ($geo['at'] ?? 0) > 0;
@endphp
<div class="geo-box" id="geoBox" data-check-url="{{ route('geo.check') }}" data-token="{{ csrf_token() }}">
    <div class="geo-icon" id="geoIcon">📍</div>
    <h1 id="geoTitle">{{ $outside ? 'Fora da área da empresa' : 'Verificando sua localização…' }}</h1>
    <p id="geoText">
        @if($outside)
            @if($geo['distance'] !== null)
                Você está a <b>{{ \App\Support\Geo::formatDistance($geo['distance']) }}</b> da empresa. O acesso é liberado num raio de {{ $radius }} m.
            @else
                {{ $geo['reason'] }}
            @endif
        @else
            Seu perfil só acessa o sistema dentro da empresa. Permita o acesso à localização quando o celular perguntar.
        @endif
    </p>
    <button type="button" class="primary full" id="geoRetry">Verificar de novo</button>
    <details class="geo-help">
        <summary>A localização foi bloqueada?</summary>
        <p><b>iPhone:</b> Ajustes → Privacidade e Segurança → Serviços de Localização → ative e, em Sites do Safari, escolha "Ao Usar". Depois, no Safari, toque em "aA" na barra de endereço → Ajustes do Site → Localização → Permitir.</p>
        <p><b>Android (Chrome):</b> toque no cadeado ao lado do endereço → Permissões → Localização → Permitir. Confira também se a localização do celular está ligada.</p>
    </details>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="link-btn">Sair</button></form>
</div>
<script>
(() => {
  const box = document.getElementById('geoBox'), title = document.getElementById('geoTitle'), text = document.getElementById('geoText'), icon = document.getElementById('geoIcon'), btn = document.getElementById('geoRetry');
  const send = (body) => fetch(box.dataset.checkUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': box.dataset.token }, body: JSON.stringify(body) }).then((r) => r.json());
  const run = () => {
    btn.disabled = true; title.textContent = 'Verificando sua localização…'; icon.textContent = '📍';
    const done = (r) => {
      btn.disabled = false;
      if (r.inside) { title.textContent = 'Localização confirmada'; icon.textContent = '✅'; location.href = r.redirect; return; }
      icon.textContent = '🚫'; title.textContent = 'Fora da área da empresa';
      text.textContent = r.reason && !r.distance.match(/\d/) ? r.reason : `Você está a ${r.distance} da empresa. O acesso é liberado num raio de {{ $radius }} m.`;
    };
    const fail = () => { btn.disabled = false; text.textContent = 'Falha de conexão. Tente de novo.'; };
    if (!navigator.geolocation) return send({ error: 'indisponivel' }).then(done, fail);
    navigator.geolocation.getCurrentPosition(
      (p) => send({ lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy }).then(done, fail),
      (e) => send({ error: e.code === 1 ? 'negada' : e.code === 3 ? 'tempo' : 'erro' }).then(done, fail),
      { enableHighAccuracy: true, timeout: 20000, maximumAge: 30000 }
    );
  };
  btn.addEventListener('click', run);
  run();
})();
</script>
@endsection
