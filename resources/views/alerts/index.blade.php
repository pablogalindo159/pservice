@extends('layouts.app')
@section('title', 'Alertas · PService')
@section('content')
@php
    $unseen = \App\Models\Alert::unseenCount();
@endphp
<div class="page-head"><div><span class="eyebrow">SEGURANÇA</span><h1>🔔 Alertas</h1><p>Acessos e envios fora da área da empresa</p></div>
@if($unseen)
<form method="post" action="{{ route('alerts.seen') }}">@csrf<button class="primary">Marcar {{ $unseen }} como visto(s)</button></form>
@endif
</div>

<div class="alert-list">
@forelse($alerts as $a)
    <article @class(['alert-item', 'new' => ! $a->seen_at])>
        <div class="alert-ico">{{ $a->type === 'photo.outside' ? '📷' : '📍' }}</div>
        <div class="alert-body">
            <b>{{ $a->message }}</b>
            <small>
                {{ $a->updated_at->format('d/m/Y H:i') }}
                @if($a->order) · <a href="{{ route('os.show', $a->order) }}">abrir OS{{ $a->order->number }}</a>@endif
                @if(isset($a->meta['lat'], $a->meta['lng'])) · <a href="https://www.google.com/maps?q={{ $a->meta['lat'] }},{{ $a->meta['lng'] }}" target="_blank" rel="noopener">ver no mapa ↗</a>@endif
                @if($a->seen_at) · visto por {{ $a->seenBy?->name ?? '—' }}@endif
            </small>
        </div>
        @unless($a->seen_at)<span class="alert-dot" aria-label="Novo"></span>@endunless
    </article>
@empty
    <div class="panel"><p class="muted">Nenhum alerta. Quando Técnico, Laboratório ou Visualizador acessarem ou enviarem fotos fora da área, aparece aqui.</p></div>
@endforelse
</div>
<div class="pager">{{ $alerts->links('partials.pager') }}</div>
@endsection
