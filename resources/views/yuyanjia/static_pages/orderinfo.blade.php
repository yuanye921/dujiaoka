@extends('yuyanjia.layouts.default')

@section('content')
    <section class="page-section">
        <div class="container narrow">
            <div class="section-head simple">
                <div>
                    <span class="eyebrow muted">Orders</span>
                    <h1>订单详情</h1>
                </div>
                <a class="btn ghost" href="{{ url('order-search') }}">继续查询</a>
            </div>

            @foreach($orders as $order)
                <article class="panel order-panel">
                    <div class="order-title">
                        <div>
                            <span>订单号</span>
                            <h2>{{ $order['order_sn'] }}</h2>
                        </div>
                        @switch($order['status'])
                            @case(\App\Models\Order::STATUS_WAIT_PAY)
                                <span class="status muted">待支付</span>
                                @break
                            @case(\App\Models\Order::STATUS_COMPLETED)
                                <span class="status success">已完成</span>
                                @break
                            @case(\App\Models\Order::STATUS_ABNORMAL)
                                <span class="status danger">异常</span>
                                @break
                            @case(\App\Models\Order::STATUS_EXPIRED)
                                <span class="status muted">已过期</span>
                                @break
                            @default
                                <span class="status">处理中</span>
                        @endswitch
                    </div>

                    <div class="detail-list two-col">
                        <div><span>商品</span><strong>{{ $order['title'] }}</strong></div>
                        @if(!empty($order['sku']))
                            <div><span>规格</span><strong>{{ $order['sku']['sku_name'] ?? '' }}</strong></div>
                        @endif
                        <div><span>数量</span><strong>{{ $order['buy_amount'] }}</strong></div>
                        <div><span>邮箱</span><strong>{{ $order['email'] }}</strong></div>
                        <div><span>金额</span><strong>{{ number_format((float)$order['actual_price'], 2) }} CNY</strong></div>
                        <div><span>支付方式</span><strong>{{ $order['pay']['pay_name'] ?? '' }}</strong></div>
                        <div><span>创建时间</span><strong>{{ $order['created_at'] }}</strong></div>
                    </div>

                    <label class="card-secret">
                        <span>卡密 / 订单信息</span>
                        <textarea readonly data-copy-source="order-{{ $order['order_sn'] }}">{{ $order['info'] }}</textarea>
                    </label>
                    <div class="actions">
                        <button class="btn secondary" type="button" data-copy-target="order-{{ $order['order_sn'] }}">复制内容</button>
                        @if($order['status'] == \App\Models\Order::STATUS_WAIT_PAY)
                            <a class="btn primary" href="{{ url('bill', ['orderSN' => $order['order_sn']]) }}">去支付</a>
                        @endif
                    </div>
                </article>
            @endforeach
            @include('common.orderRecoveryPagination')
        </div>
    </section>
@stop
