@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">PAINEL</span><h1>Visão geral</h1><p>Acompanhe suas ordens de serviço e registros fotográficos.</p></div>
<a class="primary" href="{{ route('os.index') }}">Ver ordens</a></div>
<div class="metrics">
<div class="metric"><span>OS cadastradas</span><strong>{{ $total }}</strong><i>{{ $openOrders }} em aberto</i></div>
<div class="metric"><span>Fotos registradas</span><strong>{{ $photos }}</strong><i>Registros fotográficos</i></div>
<div class="metric"><span>OS hoje</span><strong>{{ $ordersToday }}</strong><i>Criadas hoje</i></div>
<div class="metric"><span>Fotos hoje</span><strong>{{ $photosToday }}</strong><i>Capturadas hoje</i></div>
</div>
<div class="dash-grid">
<section class="panel"><div class="panel-head"><h2>OS recentes</h2></div>
@forelse($recentOrders as $o)
<a class="mini-row" href="{{ route('os.show', $o) }}"><b>OS{{ $o->number }}</b><span>{{ $o->client_name }}</span><span class="badge st-{{ $o->status }}">{{ $o->status_label }}</span><small>{{ $o->photos_count }} 📷</small></a>
@empty<p class="muted">Nenhuma OS ainda.</p>@endforelse
</section>
<section class="panel"><div class="panel-head"><h2>Fotos por etapa</h2></div>
@foreach(config('pservice.stages') as $stage)
@php($n = $byStage[$stage] ?? 0)
<div class="barrow"><span>{{ $stage }}</span><div class="bar"><i style="width:{{ $photos ? min(100, ($n / $photos) * 100) : 0 }}%"></i></div><b>{{ $n }}</b></div>
@endforeach
</section>
<section class="panel"><div class="panel-head"><h2>Fotos recentes</h2></div>
@forelse($recentPhotos as $p)
<a class="activity" href="{{ route('os.show', $p->order) }}"><img loading="lazy" src="{{ route('photos.file', [$p, 'size' => 'thumb']) }}" alt=""><div><b>OS{{ $p->order->number }}</b><span>{{ $p->stage }}</span><small>{{ $p->user->name }} · {{ $p->captured_at->format('d/m/Y H:i') }}</small></div></a>
@empty<p class="muted">Nenhuma foto ainda.</p>@endforelse
</section>
@if(auth()->user()->canAudit())
<section class="panel"><div class="panel-head row-between"><h2>Atividades recentes</h2><a href="{{ route('audit.index') }}" class="link">Ver tudo</a></div>
@forelse($recentActivity as $log)
<div class="log-row"><b>{{ $log->action_label }}</b><span>{{ $log->user?->name ?? 'Sistema' }}@if($log->order) · OS{{ $log->order->number }}@endif</span><small>{{ $log->created_at->format('d/m H:i') }}</small></div>
@empty<p class="muted">Sem atividades.</p>@endforelse
</section>
@endif
</div>
@endsection
