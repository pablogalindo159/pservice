@extends('layouts.app')
@section('title', 'OS'.$os->number.' · PService')
@section('content')
@php($me = auth()->user())
<div class="page-head"><div><span class="eyebrow">ORDEM DE SERVIÇO</span><h1>OS{{ $os->number }}</h1><p>{{ $os->client_name }} · aberta em {{ $os->created_at->format('d/m/Y') }}</p></div>
<div class="head-actions">
@if($me->canCreateOs())
<form method="post" action="{{ route('os.status', $os) }}" class="status-form">@csrf @method('PATCH')
<select name="status" onchange="this.form.submit()">@foreach(config('pservice.statuses') as $k => $label)<option value="{{ $k }}" @selected($os->status === $k)>{{ $label }}</option>@endforeach</select></form>
@else<span class="badge st-{{ $os->status }}">{{ $os->status_label }}</span>@endif
<a class="secondary" href="{{ route('os.index') }}">← Voltar</a></div></div>

<div class="stage-grid">
@foreach($stages as $stage)
@php($items = $photos[$stage] ?? collect())
@php($cover = $items->first())
@php($anchor = $os->stageAnchor($stage))
<article class="stage" id="stage-{{ $loop->index }}">
<div class="cover" data-gallery="{{ $anchor }}">@if($cover)<img loading="lazy" src="{{ route('photos.file', [$cover, 'size' => 'thumb']) }}" alt="{{ $stage }}">@else<div class="stage-icon">📷</div>@endif</div>
<div class="stage-body"><div class="stage-title">{{ $loop->iteration }}. {{ $stage }}</div><div class="count">{{ $items->count() }} foto(s)</div>
<div class="actions"><button type="button" data-gallery="{{ $anchor }}" class="primary">Abrir etapa</button>
@if($me->canDownload() && $items->isNotEmpty())<a class="secondary" href="{{ route('photos.downloadStage', $os) }}?stage={{ urlencode($stage) }}">Baixar</a>@endif</div></div>
<div class="gallery" id="{{ $anchor }}">
@if($me->canTakePhotos())
<form class="upload" method="post" enctype="multipart/form-data" action="{{ route('photos.store', $os) }}" data-upload>
@csrf<input type="hidden" name="stage" value="{{ $stage }}">
<div class="upload-buttons">
<label class="camera-input">📸 Câmera<input type="file" name="photos[]" accept="image/*" capture="environment" multiple hidden></label>
<label class="camera-input alt">🖼️ Galeria<input type="file" name="photos[]" accept="image/*" multiple hidden></label>
</div>
<div class="upload-status" hidden><div class="bar"><i></i></div><span></span></div>
<noscript><button class="primary">Salvar fotos</button></noscript>
</form>
@endif
<div class="thumbs">@foreach($items as $photo)<div class="thumb-wrap"><div class="thumb" data-view="{{ route('photos.file', [$photo, 'size' => 'preview']) }}" data-original="{{ route('photos.file', $photo) }}" data-caption="{{ $stage }} · {{ $photo->user?->name }} · {{ $photo->captured_at->format('d/m/Y H:i') }}"><img loading="lazy" src="{{ route('photos.file', [$photo, 'size' => 'thumb']) }}" alt=""></div>@if($me->canDeletePhotos())<form method="post" action="{{ route('photos.destroy', $photo) }}" onsubmit="return confirm('Excluir esta foto?')">@csrf @method('DELETE')<button class="danger-mini" aria-label="Excluir">×</button></form>@endif</div>@endforeach</div>
</div></article>
@endforeach
</div>
@if($me->canDownload() && $photos->isNotEmpty())
<div class="bottom-action"><a class="primary" href="{{ route('photos.downloadAll', $os) }}">⬇ Baixar todas as fotos da OS</a></div>
@endif
<div class="viewer" id="viewer"><button class="close" data-close aria-label="Fechar">×</button><img src="" alt="Foto"><div class="viewer-bar"><span class="caption"></span><a class="secondary" data-original-link target="_blank" rel="noopener">Abrir original</a></div></div>
@endsection
