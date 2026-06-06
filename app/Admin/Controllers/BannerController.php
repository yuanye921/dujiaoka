<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Post\BatchRestore;
use App\Admin\Actions\Post\Restore;
use App\Admin\Repositories\Banner;
use App\Models\Banner as BannerModel;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

class BannerController extends AdminController
{
    protected function grid()
    {
        return Grid::make(new Banner(), function (Grid $grid) {
            $grid->model()->orderBy('ord', 'DESC')->orderBy('id', 'DESC');
            $grid->column('id')->sortable();
            $grid->column('image', '图片')->image('', 180, 70);
            $grid->column('title', '标题');
            $grid->column('button_text', '按钮文字');
            $grid->column('link', '跳转链接')->limit(40);
            $grid->column('is_open', '状态')->select(BannerModel::getIsOpenMap());
            $grid->column('ord', '排序')->sortable();
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();
            $grid->filter(function (Grid\Filter $filter) {
                $filter->like('title', '标题');
                $filter->equal('is_open', '状态')->select(BannerModel::getIsOpenMap());
                $filter->scope(admin_trans('dujiaoka.trashed'))->onlyTrashed();
            });
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                if (request('_scope_') == admin_trans('dujiaoka.trashed')) {
                    $actions->append(new Restore(BannerModel::class));
                }
            });
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                if (request('_scope_') == admin_trans('dujiaoka.trashed')) {
                    $batch->add(new BatchRestore(BannerModel::class));
                }
            });
        });
    }

    protected function detail($id)
    {
        return Show::make($id, new Banner(), function (Show $show) {
            $show->field('id');
            $show->field('title', '标题');
            $show->field('subtitle', '副标题');
            $show->field('button_text', '按钮文字');
            $show->field('image', '图片')->image();
            $show->field('link', '跳转链接');
            $show->field('is_open', '状态')->using(BannerModel::getIsOpenMap());
            $show->field('ord', '排序');
            $show->field('created_at');
            $show->field('updated_at');
        });
    }

    protected function form()
    {
        return Form::make(new Banner(), function (Form $form) {
            $form->display('id');
            $form->text('title', '标题')->required()->help('显示在横幅左侧的大标题。');
            $form->textarea('subtitle', '副标题')->rows(3)->help('可留空；支持换行。');
            $form->text('button_text', '按钮文字')->default('了解更多')->help('可留空；按钮文字和跳转链接都填写时才显示按钮。');
            $form->image('image', '图片')->autoUpload()->uniqueName()->help('建议使用横图，例如 1600x450。');
            $form->text('link', '跳转链接')->default('/')->help('可以填站内路径，例如 /buy/1，也可以填完整网址。');
            $form->number('ord', '排序')->default(1)->help('数字越大越靠前。');
            $form->switch('is_open', '是否启用')->default(BannerModel::STATUS_OPEN);
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
