@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">PAINEL</span><h1>Visão geral</h1><p>Acompanhe suas ordens de serviço e registros fotográficos.</p></div>
<a class="primary" href="{{ route('os.index') }}">Ver ordens</a></div>
<div class="metrics">
<div class="metric"><span>OS cadastradas</span><strong>{{ $total }}</strong><i>Ordens no sistema</i></div>
<div class="metric"><span>Fotos registradas</span><strong>{{ $photos }}</strong><i>Registros fotográficos</i></div>
<div class="metric"><span>OS hoje</span><strong>{{ $ordersToday }}</strong><i>Criadas hoje</i></div>
<div class="metric"><span>Fotos hoje</span><strong>{{ $photosToday }}</strong><i>Capturadas hoje</i></div>
</div>
<div class="dash-grid">
<section class="panel"><div class="panel-head"><h2>Fotos por etapa</h2></div>
@foreach(['Entrada','Desmontagem','Bobinagem','Montagem','Testes','Finalização'] as $stage)
@php($n=$byStage[$stage] ?? 0)
<div class="barrow"><span>{{ $stage }}</span><div class="bar"><i style="width:{{ $photos ? min(100,($n/$photos)*100) : 0 }}%"></i></div><b>{{ $n }}</b></div>
@endforeach
</section>
<section class="panel"><div class="panel-head"><h2>Fotos recentes</h2></div>
@forelse($recentPhotos as $p)
<div class="activity"><img src="{{ route('photos.file',$p) }}?thumb=1"><div><b>OS{{ $p->order->number }}</b><span>{{ $p->stage }}</span><small>{{ $p->user->name }} · {{ $p->captured_at->format('d/m/Y H:i') }}</small></div></div>
@empty<p class="muted">Nenhuma foto ainda.</p>@endforelse
</section>
</div>
@endsection
