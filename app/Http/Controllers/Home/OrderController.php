<?php

namespace App\Http\Controllers\Home;

use App\Exceptions\RuleValidationException;
use App\Http\Controllers\BaseController;
use App\Models\Order;
use App\Service\OrderProcessService;
use App\Service\OrderRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;


/**
 * 订单控制器
 *
 * Class OrderController
 * @package App\Http\Controllers\Home
 * @author: Assimon
 * @email: Ashang@utf8.hk
 * @blog: https://utf8.hk
 * Date: 2021/5/30
 */
class OrderController extends BaseController
{


    /**
     * 订单服务层
     * @var \App\Service\OrderService
     */
    private $orderService;

    /**
     * 订单处理层.
     * @var OrderProcessService
     */
    private $orderProcessService;

    /**
     * @var OrderRecoveryService
     */
    private $orderRecoveryService;

    public function __construct()
    {
        $this->orderService = app('Service\OrderService');
        $this->orderProcessService = app('Service\OrderProcessService');
        $this->orderRecoveryService = app('Service\OrderRecoveryService');
    }

    /**
     * 创建订单
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Validation\ValidationException
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function createOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->orderService->validatorCreateOrder($request);
            $goods = $this->orderService->validatorGoods($request);
            $sku = $this->orderService->validatorGoodsSku($request, $goods);
            $this->orderService->validatorLoopCarmis($request, $sku);
            // 设置商品
            $this->orderProcessService->setGoods($goods);
            $this->orderProcessService->setSku($sku);
            // 优惠码
            $coupon = $this->orderService->validatorCoupon($request);
            // 设置优惠码
            $this->orderProcessService->setCoupon($coupon);
            $otherIpt = $this->orderService->validatorChargeInput($goods, $request);
            $this->orderProcessService->setOtherIpt($otherIpt);
            // 数量
            $this->orderProcessService->setBuyAmount($request->input('by_amount'));
            // 支付方式
            $this->orderProcessService->setPayID($request->input('payway'));
            // 下单邮箱
            $this->orderProcessService->setEmail($request->input('email'));
            // ip地址
            $this->orderProcessService->setBuyIP($request->getClientIp());
            // 查询密码
            $this->orderProcessService->setSearchPwd($request->input('search_pwd', ''));
            // 创建订单
            $order = $this->orderProcessService->createOrder();
            DB::commit();
            // 设置订单cookie
            $this->queueCookie($order->order_sn);
            return redirect(url('/bill', ['orderSN' => $order->order_sn]));
        } catch (RuleValidationException $exception) {
            DB::rollBack();
            return $this->err($exception->getMessage());
        }
    }

    /**
     * 设置订单cookie.
     * @param string $orderSN 订单号.
     */
    private function queueCookie(string $orderSN) : void
    {
        // 设置订单cookie
        $cookies = Cookie::get('dujiaoka_orders');
        if (empty($cookies)) {
            Cookie::queue('dujiaoka_orders', json_encode([$orderSN]));
        } else {
            $cookies = json_decode($cookies, true);
            array_push($cookies, $orderSN);
            Cookie::queue('dujiaoka_orders', json_encode($cookies));
        }
    }

    /**
     * 结账
     *
     * @param string $orderSN
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function bill(string $orderSN)
    {
        $order = $this->orderService->detailOrderSN($orderSN);
        if (empty($order)) {
            return $this->err(__('dujiaoka.prompt.order_does_not_exist'));
        }
        if ($order->status == Order::STATUS_EXPIRED) {
            return $this->err(__('dujiaoka.prompt.order_is_expired'));
        }
        return $this->render('static_pages/bill', $order, __('dujiaoka.page-title.bill'));
    }

    /**
     * 支付平台同步回跳页。
     */
    public function paySuccess(Request $request)
    {
        $orderSN = $this->resolveSuccessOrderSN($request);
        if ($orderSN) {
            sleep(2);
            return redirect(url('detail-order-sn', ['orderSN' => $orderSN]));
        }

        return redirect(url('order-search'));
    }

    private function resolveSuccessOrderSN(Request $request): ?string
    {
        foreach (['order_id', 'orderSN', 'order_sn', 'out_trade_no', 'param'] as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '' && $this->orderService->detailOrderSN($value)) {
                return $value;
            }
        }

        $cookies = Cookie::get('dujiaoka_orders');
        $orderSNS = json_decode($cookies ?: '[]', true);
        if (! is_array($orderSNS)) {
            return null;
        }

        $orderSNS = array_reverse(array_values(array_filter($orderSNS)));
        foreach ($orderSNS as $orderSN) {
            $orderSN = trim((string) $orderSN);
            if ($orderSN !== '' && $this->orderService->detailOrderSN($orderSN)) {
                return $orderSN;
            }
        }

        return null;
    }


    /**
     * 订单状态监测
     *
     * @param string $orderSN 订单号
     * @return \Illuminate\Http\JsonResponse
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function checkOrderStatus(string $orderSN)
    {
        $order = $this->orderService->detailOrderSN($orderSN);
        // 订单不存在或者已经过期
        if (!$order || $order->status == Order::STATUS_EXPIRED) {
            return response()->json(['msg' => 'expired', 'code' => 400001]);
        }
        // 订单已经支付
        if ($order->status == Order::STATUS_WAIT_PAY) {
            return response()->json(['msg' => 'wait....', 'code' => 400000]);
        }
        // 成功
        if ($order->status > Order::STATUS_WAIT_PAY) {
            return response()->json(['msg' => 'success', 'code' => 200]);
        }
    }

    /**
     * 通过订单号展示订单详情
     *
     * @param string $orderSN 订单号.
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function detailOrderSN(string $orderSN)
    {
        $order = $this->orderService->detailOrderSN($orderSN);
        // 订单不存在或者已经过期
        if (!$order) {
            return $this->err(__('dujiaoka.prompt.order_does_not_exist'));
        }
        return $this->render('static_pages/orderinfo', ['orders' => [$order]], __('dujiaoka.page-title.order-detail'));
    }

    /**
     * 订单号查询
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function searchOrderBySN(Request $request)
    {
        return $this->detailOrderSN($request->input('order_sn'));
    }

    /**
     * 通过邮箱查询
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function searchOrderByEmail(Request $request)
    {
        if (
            !$request->has('email') ||
            (
                dujiaoka_config_get('is_open_search_pwd', \App\Models\BaseModel::STATUS_CLOSE) == \App\Models\BaseModel::STATUS_OPEN &&
                !$request->has('search_pwd')
            )
        ) {
            return $this->err(__('dujiaoka.prompt.server_illegal_request'));
        }
        $orders = $this->orderService->withEmailAndPassword($request->input('email'), $request->input('search_pwd',''));
        if ($orders->count() === 0) {
            return $this->err(__('dujiaoka.prompt.no_related_order_found'));
        }
        return $this->render('static_pages/orderinfo', ['orders' => $orders], __('dujiaoka.page-title.order-detail'));
    }

    /**
     * 向购买邮箱发送历史订单找回验证码。
     */
    public function requestOrderRecovery(Request $request)
    {
        $result = $this->orderRecoveryService->request(
            (string) $request->input('recovery_email', ''),
            (string) $request->getClientIp(),
            (string) $request->userAgent()
        );

        return $this->render('static_pages/searchOrder', [
            'orderRecoveryError' => $result['ok'] ? '' : $result['message'],
            'orderRecoveryMessage' => $result['ok'] ? $result['message'] : '',
            'orderRecoveryChallengeId' => $result['challenge_id'] ?? '',
            'orderRecoveryMaskedEmail' => $result['masked_email'] ?? '',
        ], __('dujiaoka.page-title.order-search'));
    }

    /**
     * 确认邮箱验证码并为当前会话签发临时查看权限。
     */
    public function confirmOrderRecovery(Request $request)
    {
        $result = $this->orderRecoveryService->confirm(
            (string) $request->input('challenge_id', ''),
            (string) $request->input('otp', '')
        );

        if (!$result['ok']) {
            return $this->render('static_pages/searchOrder', [
                'orderRecoveryError' => $result['message'],
                'orderRecoveryMessage' => '',
                'orderRecoveryChallengeId' => $result['challenge_id'] ?? '',
                'orderRecoveryMaskedEmail' => $result['masked_email'] ?? '',
            ], __('dujiaoka.page-title.order-search'));
        }

        $request->session()->put('order_recovery_challenge_id', $result['challenge_id']);
        return redirect(url('order-recovery/results'));
    }

    /**
     * 显示邮箱验证通过后的全部历史订单。
     */
    public function orderRecoveryResults(Request $request)
    {
        $challengeId = (string) $request->session()->get('order_recovery_challenge_id', '');
        $email = $this->orderRecoveryService->verifiedEmail(
            $challengeId,
            (int) config('licenses.order_recovery_session_minutes', 30)
        );
        if (!$email) {
            $request->session()->forget('order_recovery_challenge_id');
            return $this->err('历史订单查看权限已失效，请重新接收邮箱验证码。', url('order-search'));
        }

        $orders = $this->orderService->verifiedEmailOrders($email, 20);
        if ($orders->count() === 0) {
            return $this->err(__('dujiaoka.prompt.no_related_order_found'), url('order-search'));
        }

        return $this->render('static_pages/orderinfo', [
            'orders' => $orders,
            'orderRecoveryVerified' => true,
        ], __('dujiaoka.page-title.order-detail'));
    }

    /**
     * 通过浏览器缓存查询
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function searchOrderByBrowser(Request $request)
    {
        $cookies = Cookie::get('dujiaoka_orders');
        if (empty($cookies)) {
            return $this->err(__('dujiaoka.prompt.no_related_order_found_for_cache'));
        }
        $orderSNS = json_decode($cookies, true);
        $orders = $this->orderService->byOrderSNS($orderSNS);
        return $this->render('static_pages/orderinfo', ['orders' => $orders], __('dujiaoka.page-title.order-detail'));
    }

    /**
     * 订单查询页
     *
     * @param Request $request
     * @return mixed
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function orderSearch(Request $request)
    {
        return $this->render('static_pages/searchOrder', [], __('dujiaoka.page-title.order-search'));
    }

}
