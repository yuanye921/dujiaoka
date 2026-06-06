<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Post\BatchRestore;
use App\Admin\Actions\Post\Restore;
use App\Admin\Repositories\Goods;
use App\Models\Carmis;
use App\Models\Coupon;
use App\Models\GoodsGroup as GoodsGroupModel;
use App\Models\GoodsSku;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use App\Models\Goods as GoodsModel;
use App\Service\GoodsSkuService;
use Illuminate\Support\Facades\Log;

class GoodsController extends AdminController
{


    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new Goods(['group', 'coupon', 'activeSkus']), function (Grid $grid) {
            $grid->model()->orderBy('id', 'DESC');
            $grid->column('id')->sortable();
            $grid->column('picture')->image('', 100, 100);
            $grid->column('gd_name');
            $grid->column('gd_description');
            $grid->column('gd_keywords');
            $grid->column('group.gp_name', admin_trans('goods.fields.group_id'))->display(function ($value) {
                return $this->group ? $this->group->display_name : $value;
            });
            $grid->column('type')
                ->using(GoodsModel::getGoodsTypeMap())
                ->label([
                    GoodsModel::AUTOMATIC_DELIVERY => Admin::color()->success(),
                    GoodsModel::MANUAL_PROCESSING => Admin::color()->info(),
                ]);
            $grid->column('retail_price');
            $grid->column('actual_price')->display(function ($value) {
                $skus = app(GoodsSkuService::class)->visibleSkus($this->activeSkus ?? []);
                $prices = $skus
                    ->pluck('actual_price')
                    ->filter(function ($price) {
                        return $price !== null && $price !== '';
                    });

                if ($prices->isEmpty()) {
                    return number_format((float) $value, 2);
                }

                $min = (float) $prices->min();
                $max = (float) $prices->max();

                if (abs($min - $max) < 0.005) {
                    return number_format($min, 2);
                }

                return number_format($min, 2) . ' - ' . number_format($max, 2);
            })->sortable();
            $grid->column('in_stock')->display(function () {
                if ($this->type == GoodsModel::AUTOMATIC_DELIVERY) {
                    return Carmis::query()->where('goods_id', $this->id)
                        ->where('status', Carmis::STATUS_UNSOLD)
                        ->count();
                }

                $skus = app(GoodsSkuService::class)->visibleSkus($this->activeSkus ?? []);
                return $skus->isEmpty() ? $this->in_stock : $skus->sum('in_stock');
            });
            $grid->column('sku_summary', '规格')->display(function () {
                $skus = app(GoodsSkuService::class)->visibleSkus($this->activeSkus ?? []);
                if ($skus->isEmpty()) {
                    return '默认规格';
                }

                return $skus->map(function ($sku) {
                    return ($sku['sku_name'] ?? '默认规格') . '：' . number_format((float) ($sku['actual_price'] ?? 0), 2);
                })->implode(' / ');
            });
            $grid->column('sales_volume');
            $grid->column('ord')->editable()->sortable();
            $grid->column('is_open')->switch();
            $grid->column('created_at')->sortable();
            $grid->column('updated_at');
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
                $filter->like('gd_name');
                $filter->equal('type')->select(GoodsModel::getGoodsTypeMap());
                $filter->equal('group_id')->select(GoodsGroupModel::treeOptions());
                $filter->scope(admin_trans('dujiaoka.trashed'))->onlyTrashed();
                $filter->equal('coupon.coupons_id', admin_trans('goods.fields.coupon_id'))->select(
                    Coupon::query()->pluck('coupon', 'id')
                );
            });
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                if (request('_scope_') == admin_trans('dujiaoka.trashed')) {
                    $actions->append(new Restore(GoodsModel::class));
                }
            });
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                if (request('_scope_') == admin_trans('dujiaoka.trashed')) {
                    $batch->add(new BatchRestore(GoodsModel::class));
                }
            });
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, new Goods(), function (Show $show) {
            $show->id('id');
            $show->field('gd_name');
            $show->field('gd_description');
            $show->field('gd_keywords');
            $show->field('picture')->image();
            $show->field('retail_price');
            $show->field('actual_price');
            $show->field('in_stock');
            $show->field('ord');
            $show->field('sales_volume');
            $show->field('type')->as(function ($type) {
                if ($type == GoodsModel::AUTOMATIC_DELIVERY) {
                    return admin_trans('goods.fields.automatic_delivery');
                } else {
                    return admin_trans('goods.fields.manual_processing');
                }
            });
            $show->field('is_open')->as(function ($isOpen) {
                if ($isOpen == GoodsGroupModel::STATUS_OPEN) {
                    return admin_trans('dujiaoka.status_open');
                } else {
                    return admin_trans('dujiaoka.status_close');
                }
            });
            $show->wholesale_price_cnf()->unescape()->as(function ($wholesalePriceCnf) {
                return  "<textarea class=\"form-control field_wholesale_price_cnf _normal_\"  rows=\"10\" cols=\"30\">" . $wholesalePriceCnf . "</textarea>";
            });
            $show->other_ipu_cnf()->unescape()->as(function ($otherIpuCnf) {
                return  "<textarea class=\"form-control field_wholesale_price_cnf _normal_\"  rows=\"10\" cols=\"30\">" . $otherIpuCnf . "</textarea>";
            });
            $show->api_hook()->unescape()->as(function ($apiHook) {
                return  "<textarea class=\"form-control field_wholesale_price_cnf _normal_\"  rows=\"10\" cols=\"30\">" . $apiHook . "</textarea>";
            });;
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new Goods(['skus']), function (Form $form) {
            $form->display('id');
            $form->text('gd_name')->required();
            $form->text('gd_description')->required();
            $form->text('gd_keywords')->required();
            $form->select('group_id')->options(
                GoodsGroupModel::treeOptions()
            )->required();
            $form->image('picture')->autoUpload()->uniqueName()->help(admin_trans('goods.helps.picture'));
            $form->radio('type')->options(GoodsModel::getGoodsTypeMap())->default(GoodsModel::AUTOMATIC_DELIVERY)->required();
            $form->hasMany('skus', '商品规格 / SKU', function (Form\NestedForm $form) {
                $form->text('sku_name', '规格名称')->placeholder('例如：10元密钥、50元额度、半年卡')->required();
                $form->currency('actual_price', '规格售价')->default(0)->required();
                $form->number('in_stock', '规格库存')->default(0)->help('人工处理商品使用；自动发货库存来自该规格绑定的卡密数量。');
                $form->image('picture', '规格图片')->autoUpload()->uniqueName();
                $form->number('ord', '排序')->default(1);
                $form->switch('is_open', '是否启用')->default(GoodsSku::STATUS_OPEN);
                $form->text('sku_code', '规格编码')->placeholder('可留空，系统自动生成')->help('只有接口对接时才需要固定编码；普通商品不用填。');
            });
            $form->currency('retail_price', '划线价')->default(0)->help(admin_trans('goods.helps.retail_price'));
            $form->currency('actual_price', '默认售价')->default(0)->required()->help('兼容旧版字段；保存后会自动同步为启用规格里的最低售价。');
            $form->number('in_stock', '默认库存')->help('兼容旧版字段；保存后会自动同步为启用规格库存汇总。自动发货的真实库存仍看卡密数量。');
            $form->number('sales_volume');
            $form->number('buy_limit_num')->help(admin_trans('goods.helps.buy_limit_num'));
            $form->editor('buy_prompt');
            $form->editor('description');
            $form->textarea('other_ipu_cnf')->help(admin_trans('goods.helps.other_ipu_cnf'));
            $form->textarea('wholesale_price_cnf')->help(admin_trans('goods.helps.wholesale_price_cnf'));
            $form->textarea('api_hook');
            $form->number('ord')->default(1)->help(admin_trans('dujiaoka.ord'));
            $form->switch('is_open')->default(GoodsModel::STATUS_OPEN);

            $form->saved(function (Form $form) {
                $goodsID = $form->model()->id ?? null;
                if (!$goodsID) {
                    return;
                }

                $goods = GoodsModel::query()->find($goodsID);
                if ($goods) {
                    try {
                        app(GoodsSkuService::class)->syncAfterGoodsSaved($goods);
                    } catch (\Throwable $exception) {
                        Log::error('Sync goods SKU after save failed', [
                            'goods_id' => $goodsID,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });
        });
    }
}
