@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">SEGURANÇA</span><h1>Auditoria</h1><p>Histórico de ações</p></div></div>
<div class="searchbar"><form><input name="q" value="{{ $q }}" placeholder="Ação, usuário ou OS"><button class="primary">Buscar</button></form></div>
<div class="panel table-wrap"><table>
<tr><th>Data</th><th>Usuário</th><th>Ação</th><th>OS</th><th>Detalhes</th><th>IP</th></tr>
@forelse($logs as $log)
<tr><td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $log->user?->name ?? 'Sistema' }}</td><td>{{ $log->action_label }}</td>
<td>@if($log->order)<a href="{{ route('os.show', $log->order) }}">OS{{ $log->order->number }}</a>@else — @endif</td>
<td class="meta">@if($log->metadata)@foreach($log->metadata as $k => $v)<span><b>{{ $k }}:</b> {{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'sim' : 'não') : $v) }}</span>@endforeach @endif</td>
<td>{{ $log->ip }}</td></tr>
@empty<tr><td colspan="6" class="muted">Nenhum registro.</td></tr>@endforelse
</table></div>
<div class="pager">{{ $logs->links('partials.pager') }}</div>
@endsection
