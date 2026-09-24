@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">ORDEM DE SERVIÇO</span><h1>OS{{ $os->number }}</h1><p>{{ $os->client_name }}</p></div><a class="secondary" href="{{ route('os.index') }}">← Voltar</a></div>
<div class="stage-grid">
@foreach($stages as $stage)
@php($items=$photos[$stage] ?? collect()) @php($cover=$items->first())
<article class="stage">
<div class="cover">@if($cover)<img src="{{ route('photos.file',$cover) }}?thumb=1" alt="{{ $stage }}">@else<div class="stage-icon">📷</div>@endif</div>
<div class="stage-body"><div class="stage-title">{{ $stage }}</div><div class="count">{{ $items->count() }} foto(s)</div>
<div class="actions"><button data-gallery="gallery-{{ $loop->index }}" class="primary">Abrir etapa</button><a class="secondary" href="{{ route('photos.downloadStage',$os) }}?stage={{ urlencode($stage) }}">Baixar</a></div></div>
<div class="gallery" id="gallery-{{ $loop->index }}">
@if(auth()->user()->canTakePhotos())<form class="upload" method="post" enctype="multipart/form-data" action="{{ route('photos.store',$os) }}">@csrf<input type="hidden" name="stage" value="{{ $stage }}"><label class="camera-input">📸 Tirar / selecionar fotos<input type="file" name="photos[]" accept="image/*" capture="environment" multiple required></label><button class="primary">Salvar fotos</button></form>@endif
<div class="thumbs">@foreach($items as $photo)<div class="thumb-wrap"><div class="thumb" data-view="{{ route('photos.file',$photo) }}"><img src="{{ route('photos.file',$photo) }}?thumb=1" alt=""></div>@if(auth()->user()->canDeletePhotos())<form method="post" action="{{ route('photos.destroy',$photo) }}" onsubmit="return confirm('Excluir esta foto?')">@csrf @method('DELETE')<button class="danger-mini">×</button></form>@endif</div>@endforeach</div>
</div></article>
@endforeach
</div>
<div class="bottom-action"><a class="primary" href="{{ route('photos.downloadAll',$os) }}">⬇ Baixar todas as fotos da OS</a></div>
<div class="viewer" id="viewer"><button class="close" data-close>×</button><img src="" alt="Foto"></div>
@endsection
