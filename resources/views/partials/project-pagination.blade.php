@php
    $firstPage = max(1, $paginator->currentPage() - 1);
    $lastPage = min($paginator->lastPage(), $paginator->currentPage() + 1);
@endphp
<nav aria-label="Pagination">
    <a href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-disabled="{{ $paginator->onFirstPage() ? 'true' : 'false' }}">Previous</a>
    @foreach ($paginator->getUrlRange($firstPage, $lastPage) as $page => $url)
        <a href="{{ $url }}" @if($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
    @endforeach
    <a href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-disabled="{{ $paginator->hasMorePages() ? 'false' : 'true' }}">Next</a>
</nav>
