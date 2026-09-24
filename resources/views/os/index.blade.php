@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">OPERAÇÃO</span><h1>Ordens de Serviço</h1><p>Pesquise uma OS pelo número ou nome do cliente.</p></div></div>
<div class="searchbar"><form method="get">
<input name="q" value="{{ $q }}" placeholder="🔎  Buscar OS ou cliente..." inputmode="search">
<select name="status" onchange="this.form.submit()"><option value="">Todos os status</option>@foreach(config('pservice.statuses') as $k => $label)<option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>@endforeach</select>
<button class="primary">Buscar</button></form></div>
@if(auth()->user()->canCreateOs())
<details class="panel new-os" @if($errors->has('number') || $errors->has('client_name')) open @endif><summary>＋ Nova OS</summary>
<form method="post" action="{{ route('os.store') }}">@csrf<input name="number" value="{{ old('number') }}" placeholder="Número da OS" required maxlength="50" autocapitalize="characters"><input name="client_name" value="{{ old('client_name') }}" placeholder="Nome do cliente" required><button class="primary">Criar OS</button></form></details>
@endif
<div class="order-list">
@forelse($orders as $os)
<a class="order-row" href="{{ route('os.show', $os) }}"><div class="os-number">OS{{ $os->number }}</div><div class="client">{{ $os->client_name }}<small>{{ $os->photos_count }} foto(s) · {{ $os->created_at->format('d/m/Y') }}</small></div><span class="badge st-{{ $os->status }}">{{ $os->status_label }}</span><div class="arrow">›</div></a>
@empty<div class="panel"><p class="muted">Nenhuma OS encontrada.</p></div>@endforelse
</div>
<div class="pager">{{ $orders->links('partials.pager') }}</div>
@endsection
