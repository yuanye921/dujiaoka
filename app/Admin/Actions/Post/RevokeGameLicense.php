<?php

namespace App\Admin\Actions\Post;

use App\Models\GameLicense;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;

class RevokeGameLicense extends RowAction
{
    protected $title = '撤销授权';

    public function handle(Request $request)
    {
        $license = GameLicense::query()->findOrFail($this->getKey());
        $admin = Admin::user();
        app('Service\GameLicenseService')->revoke($license, [
            'admin_id' => $admin ? $admin->getAuthIdentifier() : null,
            'admin_name' => $admin ? $admin->name : null,
        ]);
        return $this->response()->success('授权已撤销，当前浏览器令牌已失效。')->refresh();
    }

    public function confirm()
    {
        return ['确认撤销这张码的授权吗？'];
    }
}
