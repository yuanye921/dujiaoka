<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Post\AllowLicenseRecovery;
use App\Admin\Actions\Post\RevokeGameLicense;
use App\Admin\Repositories\GameLicense;
use App\Admin\Tools\GameLicensePaginator;
use App\Models\GameLicense as GameLicenseModel;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Show;
use Illuminate\Http\Request;

class GameLicenseController extends AdminController
{
    private const SEARCH_SESSION_KEY = 'admin.game_license.search_filters';

    public function search(Request $request)
    {
        $filters = [];

        foreach ($request->all() as $key => $value) {
            if (strpos((string) $key, 'game_license_') !== 0) {
                continue;
            }

            $value = $this->sanitizeSearchValue($value);
            if ($value !== null && $value !== '' && $value !== []) {
                $filters[$key] = $value;
            }
        }

        $request->session()->put(self::SEARCH_SESSION_KEY, $filters);

        return redirect(admin_url('game-license'));
    }

    public function clearSearch(Request $request)
    {
        $request->session()->forget(self::SEARCH_SESSION_KEY);

        return redirect(admin_url('game-license'));
    }

    public function indexPage(Content $content, $page, $perPage)
    {
        $page = max(1, (int) $page);
        $perPage = (int) $perPage;
        $perPage = in_array($perPage, [20, 50, 100, 200], true) ? $perPage : 20;

        request()->query->set('game_license_page', $page);
        request()->query->set('game_license_per_page', $perPage);

        return $this->index($content);
    }

    protected function grid()
    {
        $this->applyStoredSearchFilters();

        return Grid::make(new GameLicense(['order', 'carmis', 'sku']), function (Grid $grid) {
            $grid->setName('game_license');
            $grid->setResource('game-license');
            $grid->setPaginatorClass(GameLicensePaginator::class);
            $pageName = $grid->model()->getPageName();
            $perPageName = $grid->model()->getPerPageName();
            $currentPage = max(1, (int) request()->query($pageName, request()->query('page', 1)));
            $requestedPerPage = (int) request()->query($perPageName, request()->query('per_page', 20));
            $perPage = in_array($requestedPerPage, [20, 50, 100, 200], true) ? $requestedPerPage : 20;

            // Resolve these values before Dcat builds its query. Otherwise this Dcat
            // release can keep the first resolved page in memory for the whole grid.
            $grid->model()->setCurrentPage($currentPage);
            $grid->paginate($perPage);
            $grid->model()->orderBy('id', 'desc');
            $grid->showPagination();
            $grid->perPages([20, 50, 100, 200]);
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
                $filter->setAction(admin_url('game-license-search'));
                $filter->view('admin.game-license-filter');
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

    private function applyStoredSearchFilters(): void
    {
        $filters = session()->get(self::SEARCH_SESSION_KEY, []);
        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $key => $value) {
            if (strpos((string) $key, 'game_license_') === 0) {
                request()->query->set($key, $value);
            }
        }
    }

    private function sanitizeSearchValue($value)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $item = $this->sanitizeSearchValue($item);
                if ($item !== null && $item !== '' && $item !== []) {
                    $sanitized[$key] = $item;
                }
            }

            return $sanitized;
        }

        if (! is_scalar($value)) {
            return null;
        }

        return mb_substr(trim((string) $value), 0, 255);
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
