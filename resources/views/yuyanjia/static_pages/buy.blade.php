@extends('yuyanjia.layouts.default')

@section('content')
    @php
        $skus = collect($active_skus ?? []);
        $realSkus = $skus->filter(function ($sku) {
            return strtoupper((string)($sku['sku_code'] ?? '')) !== 'DEFAULT';
        })->values();
        if ($realSkus->isNotEmpty()) {
            $skus = $realSkus;
        }
        if ($skus->isEmpty()) {
            $skus = collect([[
                'id' => '',
                'sku_name' => '默认规格',
                'sku_code' => 'DEFAULT',
                'actual_price' => $actual_price,
                'picture' => $picture,
                'in_stock' => $in_stock,
                'carmis_count' => $carmis_count ?? $in_stock,
            ]]);
        }
        $firstSku = $skus->first();
        $isAuto = (int)$type === \App\Models\Goods::AUTOMATIC_DELIVERY;
        $firstStock = $isAuto ? (int)($firstSku['carmis_count'] ?? 0) : (int)($firstSku['in_stock'] ?? 0);
        $firstPicture = $firstSku['picture'] ?: $picture;
    @endphp

    <section class="page-section buy-section">
        <div class="container buy-layout">
            <div class="product-preview">
                <img id="skuPicture" src="{{ picture_ulr($firstPicture) }}" alt="{{ $gd_name }}">
                <div class="preview-tags">
                    <span class="tag {{ $isAuto ? 'blue' : 'amber' }}">{{ $isAuto ? '自动发货' : '人工处理' }}</span>
                    <span class="tag green">当前库存 <strong data-sku-stock>{{ $firstStock }}</strong></span>
                </div>
            </div>

            <div class="buy-panel">
                <a class="back-link" href="{{ url('/') }}">← 返回商品</a>
                <h1>{{ $gd_name }}</h1>
                <p class="lead">{{ $buy_limit_num > 0 ? '本商品限购 ' . $buy_limit_num . ' 件。' : '选择规格后填写邮箱，下单后可在订单查询里找回。' }}</p>

                @if(!empty($wholesale_price_cnf) && is_array($wholesale_price_cnf))
                    <div class="discount-row">
                        @foreach($wholesale_price_cnf as $ws)
                            <span>满 {{ $ws['number'] }} 件，单价 {{ $ws['price'] }} CNY</span>
                        @endforeach
                    </div>
                @endif

                <form action="{{ url('create-order') }}" method="post" class="buy-form" data-buy-form>
                    {{ csrf_field() }}
                    <input type="hidden" name="gid" value="{{ $id }}">
                    <input type="hidden" name="sku_id" value="{{ $firstSku['id'] }}" data-sku-input>

                    <div class="field-group">
                        <label>规格</label>
                        <div class="sku-grid">
                            @foreach($skus as $index => $sku)
                                @php
                                    $skuStock = $isAuto ? (int)($sku['carmis_count'] ?? 0) : (int)($sku['in_stock'] ?? 0);
                                    $skuPicture = $sku['picture'] ?: $picture;
                                @endphp
                                <button
                                    type="button"
                                    class="sku-option {{ $index === 0 ? 'active' : '' }}"
                                    data-sku-option
                                    data-sku-id="{{ $sku['id'] }}"
                                    data-sku-price="{{ $sku['actual_price'] }}"
                                    data-sku-stock="{{ $skuStock }}"
                                    data-sku-picture="{{ picture_ulr($skuPicture) }}"
                                >
                                    <span>{{ $sku['sku_name'] }}</span>
                                    <strong>{{ number_format((float)$sku['actual_price'], 2) }} CNY</strong>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-grid">
                        <label>
                            <span>邮箱</span>
                            <input type="email" name="email" required placeholder="用于接收卡密和查询订单">
                        </label>
                        <label>
                            <span>购买数量</span>
                            <input type="number" name="by_amount" min="1" value="1" data-buy-amount required>
                        </label>
                        @if(isset($open_coupon))
                            <label>
                                <span>优惠码</span>
                                <input type="text" name="coupon_code" placeholder="没有可留空">
                            </label>
                        @endif
                        @if(dujiaoka_config_get('is_open_search_pwd') == \App\Models\Goods::STATUS_OPEN)
                            <label>
                                <span>查询密码</span>
                                <input type="text" name="search_pwd" required placeholder="查询订单时使用">
                            </label>
                        @endif
                        @if($type == \App\Models\Goods::MANUAL_PROCESSING && is_array($other_ipu))
                            @foreach($other_ipu as $ipu)
                                @php
                                    preg_match_all('/(.*?)\[(.*?)\]/m', $ipu['desc'], $matches, PREG_SET_ORDER, 0);
                                    $optionText = $matches[0][2] ?? '';
                                    $inputName = $optionText ? ($matches[0][1] ?? $ipu['desc']) : $ipu['desc'];
                                    $options = $optionText ? explode('|', $optionText) : [];
                                @endphp
                                <label>
                                    <span>{{ $inputName }}</span>
                                    @if(count($options))
                                        <select name="{{ $ipu['field'] }}" @if($ipu['rule'] !== false) required @endif>
                                            @foreach($options as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="{{ $ipu['field'] }}" @if($ipu['rule'] !== false) required @endif placeholder="{{ $ipu['placeholder'] }}">
                                    @endif
                                </label>
                            @endforeach
                        @endif
                        @if(dujiaoka_config_get('is_open_img_code') == \App\Models\Goods::STATUS_OPEN)
                            <label class="captcha-field">
                                <span>验证码</span>
                                <div>
                                    <input type="text" name="img_verify_code" required>
                                    <img src="{{ captcha_src('buy') . time() }}" alt="验证码" onclick="this.src='{{ captcha_src('buy') }}' + Math.random()">
                                </div>
                            </label>
                        @endif
                    </div>

                    <div class="field-group">
                        <label>支付方式</label>
                        <input type="hidden" name="payway" value="{{ $payways[0]['id'] ?? 0 }}" data-pay-input>
                        <div class="pay-grid">
                            @foreach($payways as $index => $way)
                                <button type="button" class="pay-option {{ $index === 0 ? 'active' : '' }}" data-pay-option data-pay-id="{{ $way['id'] }}">
                                    {{ $way['pay_name'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="buy-summary">
                        <div>
                            <span>当前单价</span>
                            <strong><span data-sku-price-label>{{ number_format((float)$firstSku['actual_price'], 2) }}</span> CNY</strong>
                        </div>
                        <button class="btn primary" type="submit" data-submit-order>立即下单</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="container">
            <section class="description-panel">
                <h2>商品说明</h2>
                <div class="rich-text">{!! $description !!}</div>
            </section>
        </div>
    </section>

    @if(!empty($buy_prompt))
        <div class="toast-note" data-toast-note>
            <button type="button" data-close-toast>×</button>
            <strong>购买提醒</strong>
            <div>{!! $buy_prompt !!}</div>
        </div>
    @endif
@stop

@section('js')
    <script>
        window.YUYANJIA_BUY_LIMIT = {{ (int)$buy_limit_num }};
    </script>
@stop
