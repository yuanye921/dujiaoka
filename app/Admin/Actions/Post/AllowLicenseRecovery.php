<?php

namespace App\Admin\Actions\Post;

use App\Models\GameLicense;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;

class AllowLicenseRecovery extends RowAction
{
    protected $title = '允许一次人工找回';

    public function handle(Request $request)
    {
        $license = GameLicense::query()->findOrFail($this->getKey());
        $admin = Admin::user();
        app('Service\GameLicenseService')->allowOneTimeRecovery($license, 30, [
            'admin_id' => $admin ? $admin->getAuthIdentifier() : null,
            'admin_name' => $admin ? $admin->name : null,
        ]);
        return $this->response()->success('已开放30分钟人工找回窗口。')->refresh();
    }

    public function confirm()
    {
        return ['确认允许这张码在30分钟内跳过邮箱验证并重新绑定吗？'];
    }
}
