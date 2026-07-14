<section id="historical-order-recovery" style="max-width:760px;margin:28px auto;padding:24px;border:1px solid rgba(127,127,127,.25);border-radius:12px;background:rgba(255,255,255,.04)">
    <h2 style="margin-top:0">找回历史订单</h2>
    <p>适合站点改版前购买、没有查询密码或原查询密码无法使用的订单。验证购买邮箱后，可临时查看该邮箱下的全部历史订单。</p>

    @if(!empty($orderRecoveryError))
        <div style="margin:12px 0;padding:10px 12px;border-radius:8px;background:#fff0f0;color:#b42318">{{ $orderRecoveryError }}</div>
    @endif
    @if(!empty($orderRecoveryMessage))
        <div style="margin:12px 0;padding:10px 12px;border-radius:8px;background:#edfdf3;color:#067647">{{ $orderRecoveryMessage }}</div>
    @endif

    @if(!empty($orderRecoveryChallengeId))
        <form action="{{ url('order-recovery/confirm') }}" method="post">
            {{ csrf_field() }}
            <input type="hidden" name="challenge_id" value="{{ $orderRecoveryChallengeId }}">
            <label style="display:block;margin-bottom:12px">
                <span style="display:block;margin-bottom:6px">发送至 {{ $orderRecoveryMaskedEmail ?: '购买邮箱' }} 的6位验证码</span>
                <input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" style="width:100%;max-width:320px;padding:10px 12px;border:1px solid #ccc;border-radius:8px">
            </label>
            <button type="submit" style="padding:10px 18px;border:0;border-radius:8px;cursor:pointer">验证并查看历史订单</button>
            <a href="{{ url('order-search') }}#historical-order-recovery" style="margin-left:12px">重新发送</a>
        </form>
    @else
        <form action="{{ url('order-recovery/request') }}" method="post">
            {{ csrf_field() }}
            <label style="display:block;margin-bottom:12px">
                <span style="display:block;margin-bottom:6px">购买时填写的邮箱</span>
                <input type="email" name="recovery_email" required autocomplete="email" style="width:100%;max-width:420px;padding:10px 12px;border:1px solid #ccc;border-radius:8px">
            </label>
            <button type="submit" style="padding:10px 18px;border:0;border-radius:8px;cursor:pointer">发送邮箱验证码</button>
        </form>
    @endif
</section>
