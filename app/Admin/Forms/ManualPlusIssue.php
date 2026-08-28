<?php

namespace App\Admin\Forms;

use App\Service\ManualPlusIssueService;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Throwable;

class ManualPlusIssue extends Form
{
    public function handle(array $input)
    {
        $admin = Admin::user();

        try {
            app(ManualPlusIssueService::class)->issue(
                (int) ($input['carmis_id'] ?? 0),
                (string) ($input['email'] ?? ''),
                [
                    'admin_id' => $admin ? $admin->getAuthIdentifier() : null,
                    'ip' => request()->ip(),
                ]
            );
        } catch (Throwable $exception) {
            return $this->response()->error($exception->getMessage());
        }

        return $this->response()
            ->success('Plus 卡密已发放，订单与游戏授权均已登记。')
            ->location(admin_url('carmis'));
    }

    public function form()
    {
        $this->confirm('确认把这张 Plus 卡密发放给该邮箱吗？确认后卡密会变为已售。');
        $this->display('code', 'Plus 卡密');
        $this->hidden('carmis_id')->required();
        $this->email('email', '购买者邮箱')
            ->rules('required|email|max:200')
            ->required()
            ->help('用于记录卡密归属，以及玩家日后找回购买内容。');
    }
}
