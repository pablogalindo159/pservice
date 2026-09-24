@if ($paginator->hasPages())
<nav class="pager-nav" role="navigation" aria-label="Paginação">
@if ($paginator->onFirstPage())<span class="disabled">‹ Anterior</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>@endif
<span class="page-info">Página {{ $paginator->currentPage() }}@if(method_exists($paginator, 'lastPage')) de {{ $paginator->lastPage() }}@endif</span>
@if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima ›</a>@else<span class="disabled">Próxima ›</span>@endif
</nav>
@endif
