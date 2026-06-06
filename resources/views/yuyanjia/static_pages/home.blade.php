@extends('yuyanjia.layouts.default')

@section('content')
    @php
        $groups = collect($data ?? []);
        $banners = collect($banners ?? []);
        $banner = $banners->first();

        $noticeHtml = trim((string) dujiaoka_config_get('notice', ''));
        $hasNotice = trim(strip_tags($noticeHtml)) !== '';

        $bannerImage = $banner ? trim((string) data_get($banner, 'image', '')) : '';
        $bannerTitle = $banner ? trim((string) data_get($banner, 'title', '')) : '';
        $bannerSubtitle = $banner ? trim((string) data_get($banner, 'subtitle', '')) : '';
        $bannerButton = $banner ? trim((string) data_get($banner, 'button_text', '')) : '';
        $bannerLink = $banner ? trim((string) data_get($banner, 'link', '')) : '';
        $bannerStyle = $bannerImage !== ''
            ? "background-image: url('" . picture_ulr($bannerImage) . "')"
            : '';
    @endphp

    @if($hasNotice)
        <section class="notice-bar">
            <div class="container">
                <button type="button" class="notice-bar-button" data-open-notice>
                    <span class="notice-dot">i</span>
                    <strong>支付与售后须知</strong>
                    <em>查看公告</em>
                </button>
            </div>
        </section>

        <div class="notice-modal" data-notice-modal aria-hidden="true">
            <div class="notice-backdrop" data-close-notice></div>
            <section class="notice-dialog" role="dialog" aria-modal="true" aria-label="支付与售后须知">
                <header class="notice-dialog-head">
                    <span class="notice-dot">i</span>
                    <h2>支付与售后须知</h2>
                    <button type="button" class="notice-close" data-close-notice aria-label="关闭公告">×</button>
                </header>
                <div class="notice-dialog-body">
                    <div class="rich-text notice-rich-text">{!! $noticeHtml !!}</div>
                </div>
                <footer class="notice-dialog-foot">
                    <button type="button" class="notice-muted-btn" data-notice-today>今日不再提示</button>
                    <button type="button" class="notice-muted-btn" data-notice-forever>不再提示</button>
                    <button type="button" class="btn primary" data-close-notice>关闭</button>
                </footer>
            </section>
        </div>
    @endif

    @if($banner)
        <section class="banner-section">
            <div class="container">
                <div class="banner-card" style="{{ $bannerStyle }}">
                    <div class="banner-copy">
                        @if($bannerTitle !== '')
                            <h1>{{ $bannerTitle }}</h1>
                        @endif
                        @if($bannerSubtitle !== '')
                            <p>{!! nl2br(e($bannerSubtitle)) !!}</p>
                        @endif
                        @if($bannerButton !== '' && $bannerLink !== '')
                            <a class="btn primary" href="{{ $bannerLink }}">{{ $bannerButton }}</a>
                        @endif
                    </div>
                </div>
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
                            $skus = app(\App\Service\GoodsSkuService::class)->payableSkus($goods['active_skus'] ?? []);
                            $isAuto = (int)$goods['type'] === \App\Models\Goods::AUTOMATIC_DELIVERY;
                            $stock = $isAuto ? (int)$skus->sum('carmis_count') : (int)$skus->sum('in_stock');
                            if ($skus->isEmpty()) {
                                $stock = $isAuto ? (int)($goods['carmis_count'] ?? $goods['in_stock']) : (int)$goods['in_stock'];
                            }
                            $prices = $skus->pluck('actual_price')->filter(function ($price) { return $price !== null; });
                            $price = $prices->isNotEmpty() ? $prices->min() : $goods['actual_price'];

                            $cardImage = trim((string)($goods['picture'] ?? ''));
                            if ($cardImage === '' || strpos($cardImage, 'assets/common/images/default') !== false) {
                                $skuImage = $skus->pluck('picture')->filter(function ($picture) {
                                    return trim((string)$picture) !== '';
                                })->first();
                                $cardImage = trim((string)$skuImage);
                            }
                            $cardImageUrl = picture_ulr($cardImage);
                            $buyUrl = url("/buy/{$goods['id']}");
                        @endphp
                        <article class="product-card" data-group="group-{{ $group['id'] }}" data-product-name="{{ $goods['gd_name'] }}">
                            <a href="{{ $buyUrl }}" class="product-image">
                                <img src="{{ $cardImageUrl }}" alt="{{ $goods['gd_name'] }}">
                            </a>
                            <div class="product-body">
                                <div class="meta-line">
                                    <span>分类 · {{ $group['gp_name'] }}</span>
                                </div>
                                <h3><a href="{{ $buyUrl }}">{{ $goods['gd_name'] }}</a></h3>
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
                                    <div class="card-actions">
                                        <button
                                            type="button"
                                            class="icon-btn cart-add-btn"
                                            aria-label="加入购物车 {{ $goods['gd_name'] }}"
                                            data-cart-add
                                            data-cart-id="{{ $goods['id'] }}"
                                            data-cart-name="{{ $goods['gd_name'] }}"
                                            data-cart-category="{{ $group['gp_name'] }}"
                                            data-cart-price="{{ number_format((float)$price, 2, '.', '') }}"
                                            data-cart-stock="{{ $stock }}"
                                            data-cart-image="{{ $cardImageUrl }}"
                                            data-cart-url="{{ $buyUrl }}"
                                        >🛒</button>
                                        <a class="icon-btn" href="{{ $buyUrl }}" aria-label="购买 {{ $goods['gd_name'] }}">→</a>
                                    </div>
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
