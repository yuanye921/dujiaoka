@extends('yuyanjia.layouts.default')

@section('content')
    @php
        $groups = collect($data ?? []);
        $banners = collect($banners ?? []);
        $banner = $banners->first();
        $heroImage = $banner ? data_get($banner, 'image') : null;
        $heroTitle = $banner ? data_get($banner, 'title') : dujiaoka_config_get('text_logo', '预言家SHOP');
        $heroSubtitle = $banner ? data_get($banner, 'subtitle') : dujiaoka_config_get('description', '专业的产品与服务提供商');
        $heroButton = $banner ? data_get($banner, 'button_text') : '浏览商品';
        $heroLink = $banner ? (data_get($banner, 'link') ?: '#goods') : '#goods';
        $heroStyle = $heroImage ? "background-image: linear-gradient(90deg, rgba(5,8,15,.96) 0%, rgba(5,8,15,.72) 45%, rgba(5,8,15,.22) 100%), url('" . picture_ulr($heroImage) . "')" : '';
    @endphp

    <section class="hero" style="{{ $heroStyle }}">
        <div class="container hero-inner">
            <span class="eyebrow"><span></span>预言家SHOP</span>
            <h1>{{ $heroTitle }}</h1>
            <p>{!! nl2br(e($heroSubtitle)) !!}</p>
            <div class="actions">
                <a class="btn primary" href="{{ $heroLink }}">{{ $heroButton ?: '浏览商品' }}</a>
                <a class="btn ghost" href="{{ url('order-search') }}">查询订单</a>
            </div>
        </div>
    </section>

    @if(trim(strip_tags(dujiaoka_config_get('notice', ''))))
        <section class="notice-strip">
            <div class="container">
                <span>公告</span>
                <div>{!! dujiaoka_config_get('notice') !!}</div>
            </div>
        </section>
    @endif

    <section class="page-section" id="goods">
        <div class="container">
            <div class="section-head">
                <div>
                    <span class="eyebrow muted">Catalog</span>
                    <h2>精选商品</h2>
                    <p>按分类快速筛选，库存和发货方式一眼就能看明白。</p>
                </div>
                <label class="search-box">
                    <span>搜索</span>
                    <input type="search" placeholder="输入商品名" data-product-search>
                </label>
            </div>

            <div class="category-tabs" data-category-tabs>
                <button class="active" type="button" data-group-target="all">全部</button>
                @foreach($groups as $group)
                    <button type="button" data-group-target="group-{{ $group['id'] }}">{{ $group['gp_name'] }}</button>
                @endforeach
            </div>

            <div class="product-grid">
                @forelse($groups as $group)
                    @foreach(($group['goods'] ?? []) as $goods)
                        @php
                            $skus = collect($goods['active_skus'] ?? []);
                            $realSkus = $skus->filter(function ($sku) {
                                return strtoupper((string)($sku['sku_code'] ?? '')) !== 'DEFAULT';
                            })->values();
                            if ($realSkus->isNotEmpty()) {
                                $skus = $realSkus;
                            }
                            $isAuto = (int)$goods['type'] === \App\Models\Goods::AUTOMATIC_DELIVERY;
                            $stock = $isAuto ? (int)$skus->sum('carmis_count') : (int)$skus->sum('in_stock');
                            if ($skus->isEmpty()) {
                                $stock = $isAuto ? (int)($goods['carmis_count'] ?? $goods['in_stock']) : (int)$goods['in_stock'];
                            }
                            $prices = $skus->pluck('actual_price')->filter(function ($price) { return $price !== null; });
                            $price = $prices->isNotEmpty() ? $prices->min() : $goods['actual_price'];
                        @endphp
                        <article class="product-card" data-group="group-{{ $group['id'] }}" data-product-name="{{ $goods['gd_name'] }}">
                            <a href="{{ url("/buy/{$goods['id']}") }}" class="product-image">
                                <img src="{{ picture_ulr($goods['picture']) }}" alt="{{ $goods['gd_name'] }}">
                            </a>
                            <div class="product-body">
                                <div class="meta-line">
                                    <span>分类 · {{ $group['gp_name'] }}</span>
                                </div>
                                <h3><a href="{{ url("/buy/{$goods['id']}") }}">{{ $goods['gd_name'] }}</a></h3>
                                <div class="tags">
                                    <span class="tag {{ $isAuto ? 'blue' : 'amber' }}">{{ $isAuto ? '自动发货' : '人工处理' }}</span>
                                    <span class="tag green">库存 {{ $stock }}</span>
                                    @if($skus->count() > 1)
                                        <span class="tag">多规格</span>
                                    @endif
                                </div>
                                <div class="card-bottom">
                                    <div>
                                        <small>价格</small>
                                        <strong>{{ number_format((float)$price, 2) }} CNY</strong>
                                    </div>
                                    <a class="icon-btn" href="{{ url("/buy/{$goods['id']}") }}" aria-label="购买 {{ $goods['gd_name'] }}">→</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                @empty
                    <div class="empty-state">暂无商品</div>
                @endforelse
            </div>
        </div>
    </section>
@stop
