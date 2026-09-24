@extends('layouts.app')
@section('title', 'OS'.$os->number.' · PService')
@section('content')
@php
    $me = auth()->user();
    $canDelete = $me->canDeletePhotos();
    $done = collect($stages)->filter(fn ($s) => ($photos[$s] ?? collect())->isNotEmpty())->count();
@endphp
<div class="os-head">
    <a class="os-back" href="{{ route('os.index') }}" aria-label="Voltar para a lista">←</a>
    <div class="os-title">
        <h1>OS{{ $os->number }}</h1>
        <p>{{ $os->client_name }} · aberta em {{ $os->created_at->format('d/m/Y') }}</p>
    </div>
    @if($me->canCreateOs())
    <form method="post" action="{{ route('os.status', $os) }}">@csrf @method('PATCH')
        <select name="status" class="status-pill st-{{ $os->status }}" onchange="this.form.submit()" aria-label="Status da OS">
            @foreach(config('pservice.statuses') as $k => $label)<option value="{{ $k }}" @selected($os->status === $k)>{{ $label }}</option>@endforeach
        </select>
    </form>
    @else
    <span class="badge st-{{ $os->status }}">{{ $os->status_label }}</span>
    @endif
</div>
<div class="os-progress">
    <div class="bar"><i id="osProgress" style="width: {{ round($done / max(count($stages), 1) * 100) }}%"></i></div>
    <span id="osProgressText">{{ $done }} de {{ count($stages) }} etapas</span>
</div>

<nav class="stage-tabs" id="stageTabs" aria-label="Etapas">
@foreach($stages as $stage)
    @php($n = ($photos[$stage] ?? collect())->count())
    <button type="button" class="stage-tab" data-tab="{{ $os->stageAnchor($stage) }}"><span class="ok" @if(! $n) hidden @endif>✓</span>{{ $loop->iteration }}. {{ $stage }}<span class="n" @if(! $n) hidden @endif>{{ $n }}</span></button>
@endforeach
</nav>

@foreach($stages as $stage)
@php
    $items = $photos[$stage] ?? collect();
    $anchor = $os->stageAnchor($stage);
@endphp
<section class="stage-panel" id="{{ $anchor }}" data-panel>
    @if($me->canTakePhotos())
    <form class="upload" method="post" enctype="multipart/form-data" action="{{ route('photos.store', $os) }}" data-upload>
        @csrf<input type="hidden" name="stage" value="{{ $stage }}">
        <label class="btn-camera">📷 Tirar foto<input type="file" name="photos[]" accept="image/*" capture="environment" multiple hidden></label>
        <label class="link-gallery">ou escolher da galeria<input type="file" name="photos[]" accept="image/*" multiple hidden></label>
        <noscript><button class="primary">Salvar fotos</button></noscript>
    </form>
    @endif
    <div class="thumbs photo-grid">
    @foreach($items as $photo)
        @php($v = $photo->toViewerArray($canDelete))
        <div class="photo" data-view="{{ $v['view'] }}" data-original="{{ $v['original'] }}" data-caption="{{ $v['caption'] }}" @if($v['delete']) data-delete="{{ $v['delete'] }}" @endif><img loading="lazy" src="{{ $v['thumb'] }}" alt=""><span class="lb">{{ $v['label'] }}</span></div>
    @endforeach
    </div>
    <p class="empty" @if($items->isNotEmpty()) hidden @endif>Nenhuma foto nesta etapa</p>
    <div class="panel-foot">
        <span class="cnt"><b data-count>{{ $items->count() }}</b> foto(s) · {{ $stage }}</span>
        @if($me->canDownload())<a class="secondary sm" data-dl href="{{ route('photos.downloadStage', $os) }}?stage={{ urlencode($stage) }}" @if($items->isEmpty()) hidden @endif>⬇ Baixar etapa</a>@endif
    </div>
</section>
@endforeach

@if($me->canDownload() && $photos->isNotEmpty())
<div class="all-download"><a href="{{ route('photos.downloadAll', $os) }}">⬇ Baixar todas as fotos da OS (ZIP)</a></div>
@endif

<div class="viewer" id="viewer">
    <button class="close" data-close aria-label="Fechar">×</button>
    <img src="" alt="Foto">
    <div class="viewer-bar">
        <span class="caption"></span>
        <a class="secondary" data-original-link target="_blank" rel="noopener">Abrir original</a>
        @if($canDelete)<button type="button" class="viewer-delete" data-delete-btn>🗑 Excluir</button>@endif
    </div>
    @if($canDelete)
    <div class="viewer-confirm" data-confirm hidden>
        <b>Excluir esta foto?</b>
        <p>Ela sai da galeria e fica registrada no histórico.</p>
        <form method="post" data-delete-form>@csrf @method('DELETE')
            <button type="button" class="secondary" data-confirm-no>Cancelar</button>
            <button class="danger">Excluir</button>
        </form>
    </div>
    @endif
</div>
@endsection
