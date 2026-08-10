@php
    $brand = trim((string) dujiaoka_config_get('text_logo', '预言家SHOP'));
@endphp
<div style="margin:0;padding:24px 0;background:#f3f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Microsoft YaHei',Arial,sans-serif;color:#07111f;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:0 12px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="640" style="width:640px;max-width:100%;border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid #dce3ec;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:30px 28px;background:#081120;color:#ffffff;">
                            <div style="margin:0 0 12px;color:#62b7ff;font-size:14px;line-height:1.4;">{{ $brand }}</div>
                            <h1 style="margin:0;font-size:28px;line-height:1.25;font-weight:800;color:#ffffff;">{{ $heading }}</h1>
                            <p style="margin:14px 0 0;font-size:14px;line-height:1.7;color:#dbeafe;">{{ $subheading ?? '请在有效时间内完成验证。' }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 28px 26px;background:#ffffff;">
                            <p style="margin:0 0 22px;font-size:16px;line-height:1.8;color:#111827;">{{ $intro }}</p>
                            <div style="margin:0 0 22px;padding:18px 20px;background:#f7fbff;border:1px solid #dbeafe;border-radius:10px;">
                                <div style="margin:0 0 8px;color:#64748b;font-size:13px;line-height:1.4;">验证码</div>
                                <div style="font-size:32px;line-height:1.2;letter-spacing:8px;font-weight:800;color:#081120;font-family:Consolas,'SFMono-Regular',Menlo,monospace;">{{ $code }}</div>
                            </div>
                            <p style="margin:0;color:#475569;font-size:14px;line-height:1.8;">验证码将在 {{ $minutes }} 分钟后失效。如果不是您本人操作，请忽略这封邮件。</p>
                            <div style="margin-top:24px;padding-top:18px;border-top:1px solid #e5eaf1;color:#94a3b8;font-size:12px;line-height:1.7;">
                                这是一封自动发送的验证邮件，请勿直接回复。
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
