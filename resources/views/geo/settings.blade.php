@extends('layouts.app')
@section('title', 'Configurações · PService')
@section('content')
@php
    $roles = config('pservice.roles');
    $presos = collect(\App\Support\Geo::RESTRICTED_ROLES)->map(fn ($r) => $roles[$r] ?? $r)->implode(', ');
    $livres = collect(array_keys($roles))->diff(\App\Support\Geo::RESTRICTED_ROLES)->map(fn ($r) => $roles[$r])->implode(', ');
@endphp
<div class="page-head"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h1>Configurações</h1><p>Área da empresa (acesso por GPS)</p></div></div>

<div class="panel geo-settings">
    <h2>📍 Área da empresa</h2>
    <p class="muted">Vale para os perfis <b>{{ $presos }}</b>. <b>{{ $livres }}</b> acessam de qualquer lugar. Todo acesso fora da área gera um alerta no 🔔 para Admin e Gerente.</p>

    <form method="post" action="{{ route('settings.save') }}" id="geoForm">@csrf
        <label class="switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))> Restrição por área ativada</label>

        <div class="geo-coords">
            <label>Latitude<input name="lat" id="geoLat" value="{{ old('lat', $lat) }}" inputmode="decimal" placeholder="-25.5347000"></label>
            <label>Longitude<input name="lng" id="geoLng" value="{{ old('lng', $lng) }}" inputmode="decimal" placeholder="-49.2064000"></label>
        </div>
        <button type="button" class="secondary full" id="geoHere">📍 Usar minha localização atual</button>
        <p class="geo-status" id="geoStatus" hidden></p>

        <fieldset class="geo-mode">
            <legend>Fora da área:</legend>
            <label class="opt"><input type="radio" name="mode" value="photos" @checked(old('mode', $mode) === 'photos')> <span><b>Só enviar fotos</b> (recomendado)<small>Pode abrir as OS e enviar fotos, mas não vê, não baixa e não altera nada.</small></span></label>
            <label class="opt"><input type="radio" name="mode" value="block" @checked(old('mode', $mode) === 'block')> <span><b>Bloquear tudo</b><small>Entra, mas não usa nada até voltar para a área.</small></span></label>
        </fieldset>

        <label>Raio (metros)<input type="number" name="radius" value="{{ old('radius', $radius) }}" min="50" max="5000" step="10"></label>

        <p id="geoMapWrap" @if(! $lat) hidden @endif><a id="geoMap" href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener">Conferir o ponto no mapa ↗</a></p>

        <button class="primary full">Salvar</button>
    </form>
    <p class="muted small">Dica: marque a localização estando dentro da empresa, de preferência no meio do galpão. O GPS pode variar de 20 a 50 m em ambiente fechado; o sistema já considera essa folga.</p>
</div>
<script>
(() => {
  const btn = document.getElementById('geoHere'), st = document.getElementById('geoStatus');
  const lat = document.getElementById('geoLat'), lng = document.getElementById('geoLng');
  const map = document.getElementById('geoMap'), wrap = document.getElementById('geoMapWrap');
  btn.addEventListener('click', () => {
    st.hidden = false; st.textContent = 'Obtendo localização…';
    if (!navigator.geolocation) { st.textContent = 'Este navegador não informa localização.'; return; }
    navigator.geolocation.getCurrentPosition((p) => {
      lat.value = p.coords.latitude.toFixed(7); lng.value = p.coords.longitude.toFixed(7);
      map.href = `https://www.google.com/maps?q=${lat.value},${lng.value}`; wrap.hidden = false;
      st.textContent = `Localização capturada (precisão de ${Math.round(p.coords.accuracy)} m). Confira no mapa e toque em Salvar.`;
    }, (e) => { st.textContent = e.code === 1 ? 'Permissão de localização negada.' : 'Não foi possível obter a localização. Tente perto de uma janela.'; },
    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
  });
})();
</script>
@endsection
