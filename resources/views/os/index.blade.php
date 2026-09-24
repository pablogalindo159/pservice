@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">OPERAÇÃO</span><h1>Ordens de Serviço</h1><p>Pesquise uma OS pelo número ou nome do cliente.</p></div></div>
<div class="searchbar"><form method="get"><input name="q" value="{{ $q }}" placeholder="🔎  Buscar OS ou cliente..."><button class="primary">Buscar</button></form></div>
<div class="order-list">
@forelse($orders as $os)
<a class="order-row" href="{{ route('os.show',$os) }}"><div class="os-number">OS{{ $os->number }}</div><div class="client">{{ $os->client_name }}</div><div class="arrow">›</div></a>
@empty<div class="panel"><p class="muted">Nenhuma OS encontrada.</p></div>@endforelse
</div>
@if(auth()->user()->canCreateOs())
<div class="panel new-os"><h2>Nova OS</h2><form method="post" action="{{ route('os.store') }}">@csrf<input name="number" placeholder="Número da OS" required><input name="client_name" placeholder="Nome do cliente" required><button class="primary">Criar OS</button></form></div>
@endif
{{ $orders->links() }}
@endsection
