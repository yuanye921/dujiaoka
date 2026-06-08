<!doctype html>
<html lang="{{ str_replace('_', '-', strtolower(app()->getLocale())) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($page_title) && $page_title ? $page_title . ' | ' : '' }}{{ dujiaoka_config_get('title', dujiaoka_config_get('text_logo', '预言家SHOP')) }}</title>
    <meta name="keywords" content="{{ dujiaoka_config_get('keywords') }}">
    <meta name="description" content="{{ dujiaoka_config_get('description') }}">
    <link rel="stylesheet" href="{{ asset('assets/yuyanjia/css/app.css') }}?v=2026060801">
</head>
<body>
<div class="site-shell">
    <header class="site-header">
        <div class="container nav-wrap">
            <a class="brand" href="{{ url('/') }}">
                @if(dujiaoka_config_get('img_logo'))
                    <img src="{{ picture_ulr(dujiaoka_config_get('img_logo')) }}" alt="{{ dujiaoka_config_get('text_logo', '预言家SHOP') }}">
                @endif
                <span>{{ dujiaoka_config_get('text_logo', '预言家SHOP') }}</span>
            </a>
            <nav class="top-nav">
                <a href="{{ url('/') }}">首页</a>
                <a href="{{ url('/') }}#goods">商品中心</a>
                <a href="{{ url('order-search') }}">订单查询</a>
                <button type="button" class="cart-nav" data-open-cart aria-label="打开购物车">
                    购物车 <span data-cart-count>0</span>
                </button>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <aside class="cart-drawer" data-cart-drawer aria-hidden="true">
        <div class="cart-backdrop" data-close-cart></div>
        <section class="cart-panel" aria-label="购物车">
            <header class="cart-head">
                <div>
                    <span class="eyebrow muted">Cart</span>
                    <h2>购物车</h2>
                </div>
                <button type="button" class="notice-close" data-close-cart aria-label="关闭购物车">×</button>
            </header>
            <div class="cart-items" data-cart-items></div>
            <div class="empty-state cart-empty" data-cart-empty>购物车还是空的</div>
            <footer class="cart-foot">
                <div>
                    <span>预估合计</span>
                    <strong><span data-cart-total>0.00</span> CNY</strong>
                </div>
                <div class="cart-foot-actions">
                    <button type="button" class="btn ghost" data-cart-clear>清空</button>
                    <button type="button" class="btn primary cart-checkout" data-cart-checkout disabled>去结算</button>
                </div>
            </footer>
        </section>
    </aside>

    <footer class="site-footer">
        <div class="container">
            {!! dujiaoka_config_get('footer') !!}
        </div>
    </footer>
</div>
<script src="{{ asset('assets/yuyanjia/js/app.js') }}?v=2026060801"></script>
@yield('js')
</body>
</html>
