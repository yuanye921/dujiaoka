@if(!empty($orderRecoveryVerified) && method_exists($orders, 'lastPage') && $orders->lastPage() > 1)
    <nav style="display:flex;align-items:center;justify-content:center;gap:14px;margin:28px auto">
        @if($orders->onFirstPage())
            <span style="opacity:.45">上一页</span>
        @else
            <a href="{{ $orders->previousPageUrl() }}">上一页</a>
        @endif
        <span>第 {{ $orders->currentPage() }} / {{ $orders->lastPage() }} 页，共 {{ $orders->total() }} 笔</span>
        @if($orders->hasMorePages())
            <a href="{{ $orders->nextPageUrl() }}">下一页</a>
        @else
            <span style="opacity:.45">下一页</span>
        @endif
    </nav>
@endif
