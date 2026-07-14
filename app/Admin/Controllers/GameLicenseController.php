<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Post\AllowLicenseRecovery;
use App\Admin\Actions\Post\RevokeGameLicense;
use App\Admin\Repositories\GameLicense;
use App\Models\GameLicense as GameLicenseModel;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

class GameLicenseController extends AdminController
{
    protected function grid()
    {
        return Grid::make(new GameLicense(['order', 'carmis', 'sku']), function (Grid $grid) {
            $grid->model()->orderBy('id', 'desc');
            $grid->showPagination();
            $grid->paginate(20);
            $grid->perPages([20, 50, 100, 200]);

            // This Dcat version routes every link through PJAX. On this wide grid the
            // paginator can silently stop after a PJAX timeout, so use a normal page
            // navigation for the two footer controls only.
            Admin::script(<<<'JS'
if (!window.gameLicensePaginatorFallbackBound) {
    window.gameLicensePaginatorFallbackBound = true;
    document.addEventListener('click', function (event) {
        var target = event.target;
        var link = target && target.closest ? target.closest('a') : null;
        var footer = link && link.closest ? link.closest('.box-footer') : null;
        if (!link || !footer || !link.href) {
            return;
        }

        var url = new URL(link.href, window.location.href);
        if (!url.searchParams.has('page') && !url.searchParams.has('per_page')) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(url.toString());
    }, true);
}
JS
            );
            $grid->column('id')->sortable();
            $grid->column('carmis.carmi', '卡密')->display(function ($code) {
                return app('Service\GameLicenseService')->maskCode((string) $code);
            });
            $grid->column('order.order_sn', '订单号')->copyable();
            $grid->column('order.email', '订单邮箱')->display(function ($email) {
                return app('Service\GameLicenseService')->maskEmail((string) $email);
            });
            $grid->column('game_id', '绑定游戏')->using(config('licenses.games', []));
            $grid->column('status', '状态')->label([
                GameLicenseModel::STATUS_ACTIVE => 'success',
                GameLicenseModel::STATUS_REVOKED => 'danger',
                GameLicenseModel::STATUS_QUARANTINED => 'warning',
            ]);
            $grid->column('binding_version', '转移版本');
            $grid->column('claimed_at', '首次激活');
            $grid->column('last_verified_at', '最近验证');
            $grid->column('recovery_override_until', '人工找回窗口');
            $grid->column('is_legacy', '历史订单')->bool();
            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableBatchDelete();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('order.order_sn', '订单号');
                $filter->equal('order.email', '订单邮箱');
                $filter->equal('game_id', '绑定游戏')->select(config('licenses.games', []));
                $filter->equal('status', '状态')->select([
                    GameLicenseModel::STATUS_ACTIVE => '有效',
                    GameLicenseModel::STATUS_REVOKED => '已撤销',
                    GameLicenseModel::STATUS_QUARANTINED => '待核查',
                ]);
                $filter->where('raw_code', function ($query) {
                    $service = app('Service\GameLicenseService');
                    $normalized = $service->normalizeCode((string) $this->input);
                    if ($service->isValidCode($normalized)) {
                        $query->where('code_hash', $service->codeHash($normalized));
                        return;
                    }

                    $suffix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', substr($normalized, -8)));
                    if ($suffix !== '') {
                        $query->whereHas('carmis', function ($carmis) use ($suffix) {
                            $carmis->where('carmi', 'like', '%-' . $suffix);
                        });
                    }
                }, '卡密（完整或末段）');
            });

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->append(new AllowLicenseRecovery());
                $actions->append(new RevokeGameLicense());
            });
        });
    }

    protected function detail($id)
    {
        return Show::make($id, new GameLicense(['order', 'carmis', 'sku']), function (Show $show) {
            $show->field('id');
            $show->field('carmis.carmi', '卡密')->as(function ($code) {
                return app('Service\GameLicenseService')->maskCode((string) $code);
            });
            $show->field('order.order_sn', '订单号');
            $show->field('order.email', '订单邮箱')->as(function ($email) {
                return app('Service\GameLicenseService')->maskEmail((string) $email);
            });
            $show->field('game_id', '绑定游戏')->using(config('licenses.games', []));
            $show->field('status', '状态');
            $show->field('binding_version', '绑定版本');
            $show->field('claimed_at', '首次激活');
            $show->field('last_verified_at', '最近验证');
            $show->field('recovery_override_until', '人工找回窗口');
            $show->field('created_at');
            $show->disableEditButton();
            $show->disableDeleteButton();
        });
    }

    protected function form()
    {
        return Form::make(new GameLicense(), function (Form $form) {
            $form->display('id');
            $form->display('game_id', '绑定游戏');
            $form->display('status', '状态');
            $form->display('claimed_at', '首次激活');
            $form->disableDeleteButton();
            $form->disableSubmitButton();
        });
    }
}
