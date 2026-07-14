@extends('yuyanjia.layouts.default')

@section('content')
    <section class="page-section compact-section">
        <div class="container narrow">
            <div class="panel">
                <span class="eyebrow"><span></span>订单查询</span>
                <h1>找回你的订单</h1>
                <p class="lead">可以用订单号、邮箱，或者当前浏览器缓存查询。</p>

                <div class="query-grid">
                    <form action="{{ url('search-order-by-sn') }}" method="post" class="mini-form">
                        {{ csrf_field() }}
                        <h2>订单号查询</h2>
                        <label>
                            <span>订单号</span>
                            <input type="text" name="order_sn" required>
                        </label>
                        <button class="btn secondary" type="submit">查询订单</button>
                    </form>

                    <form action="{{ url('search-order-by-email') }}" method="post" class="mini-form">
                        {{ csrf_field() }}
                        <h2>邮箱查询</h2>
                        <label>
                            <span>邮箱</span>
                            <input type="email" name="email" required>
                        </label>
                        @if(dujiaoka_config_get('is_open_search_pwd', \App\Models\BaseModel::STATUS_CLOSE) == \App\Models\BaseModel::STATUS_OPEN)
                            <label>
                                <span>查询密码</span>
                                <input type="password" name="search_pwd" required>
                            </label>
                        @endif
                        <button class="btn secondary" type="submit">查询订单</button>
                    </form>

                    <form action="{{ url('search-order-by-browser') }}" method="post" class="mini-form">
                        {{ csrf_field() }}
                        <h2>浏览器查询</h2>
                        <p>适合刚刚在这台设备下过的订单。</p>
                        <button class="btn secondary" type="submit">读取缓存订单</button>
                    </form>
                </div>
            </div>
            @include('common.orderRecovery')
        </div>
    </section>
@stop
