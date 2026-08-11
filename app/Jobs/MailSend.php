<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class MailSend implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大尝试次数。
     *
     * @var int
     */
    public $tries = 2;

    /**
     * 任务运行的超时时间。
     *
     * @var int
     */
    public $timeout = 30;

    private $to;

    private $content;

    private $title;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $to, string $title, string $content)
    {
        $this->to = $to;
        $this->title = $title;
        $this->content = $content;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $body = $this->content;
        $title = $this->title;
        if ($this->shouldRenderVerificationTemplate($title, $body)) {
            $body = $this->renderVerificationTemplate($title, $body);
        }
        $sysConfig = \function_exists('dujiaoka_config_all') ? \dujiaoka_config_all() : (cache('system-setting') ?: []);
        $mailConfig = [
            'driver' => $sysConfig['driver'] ?? 'smtp',
            'host' => $sysConfig['host'] ?? '',
            'port' => $sysConfig['port'] ?? '465',
            'username' => $sysConfig['username'] ?? '',
            'from'      =>  [
                'address'   =>   $sysConfig['from_address'] ?? '',
                'name'      =>  $sysConfig['from_name'] ?? '独角发卡'
            ],
            'password' => $sysConfig['password'] ?? '',
            'encryption' => $sysConfig['encryption'] ?? 'ssl'
        ];
        $to = $this->to;
        //  覆盖 mail 配置
        config([
            'mail'  =>  array_merge(config('mail'), $mailConfig)
        ]);
        // 重新注册驱动
        (new MailServiceProvider(app()))->register();
        Mail::send(['html' => 'email.mail'], ['body' => $body], function ($message) use ($to, $title){
            $message->to($to)->subject($title);
        });
    }

    private function shouldRenderVerificationTemplate(string $title, string $body)
    {
        if (strpos($body, 'data-mail-template="verification-code"') !== false) {
            return false;
        }

        return strpos($title, '验证码') !== false && strpos($body, '验证码') !== false;
    }

    private function renderVerificationTemplate(string $title, string $body)
    {
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)), ENT_QUOTES, 'UTF-8')));
        $code = null;
        if (preg_match('/验证码[：:]\s*([0-9A-Za-z]{4,12})/u', $plain, $matches)) {
            $code = $matches[1];
        } elseif (preg_match('/\b([0-9]{6})\b/', $plain, $matches)) {
            $code = $matches[1];
        }

        if (!$code) {
            return $body;
        }

        $minutes = 10;
        if (preg_match('/([0-9]+)\s*分钟/u', $plain, $matches)) {
            $minutes = max(1, (int) $matches[1]);
        }

        $heading = $title;
        $subheading = '请使用下方验证码完成验证。';
        $intro = '您正在进行邮箱验证。';
        if (strpos($title, '历史订单') !== false) {
            $heading = '历史订单找回验证码';
            $subheading = '请使用下方验证码查看购买邮箱下的历史订单。';
            $intro = '您正在查看购买邮箱下的历史订单。';
        } elseif (strpos($title, '解锁码') !== false) {
            $heading = '解锁码找回验证码';
            $subheading = '请使用下方验证码完成浏览器授权转移。';
            $intro = '您正在转移一张解锁码 Plus 的浏览器授权。';
        }

        return view('email.verification_code', [
            'heading' => $heading,
            'subheading' => $subheading,
            'intro' => $intro,
            'code' => $code,
            'minutes' => $minutes,
        ])->render();
    }
}
