# 历史订单邮箱找回

## 用途

站点改版前的订单可能没有当前查询密码，或者因为商品、SKU 已经删除而无法在前台找到。本功能在订单查询页增加“找回历史订单”入口：

1. 用户输入购买邮箱。
2. 系统向该邮箱发送 6 位验证码。
3. 验证通过后，当前浏览器会话可在 30 分钟内分页查看该邮箱下的全部订单。

新订单原有的“邮箱 + 查询密码”流程保持不变。系统不会开放只凭邮箱直接查看卡密。

## 安全限制

- 验证码 10 分钟有效，只能使用一次，最多尝试 5 次。
- 同一邮箱 60 秒只能请求一次，每小时最多 5 次；同一 IP 每小时最多 20 次。
- 无论邮箱是否存在订单，页面都返回相同提示并投递相同类型的队列任务，避免泄露邮箱是否在数据库中。
- 队列中只保存挑战编号；验证码在数据库中加密保存，邮件成功发送后立即清除密文。
- 验证通过后，Laravel 会话中只保存挑战编号，不保存邮箱或验证码。

## 部署

在项目目录执行：

```sh
/www/server/php/74/bin/php artisan migrate --force
/www/server/php/74/bin/php artisan route:clear
/www/server/php/74/bin/php artisan config:clear
/www/server/php/74/bin/php artisan cache:clear
/www/server/php/74/bin/php artisan view:clear
/www/server/php/74/bin/php artisan queue:restart
```

保持现有队列进程运行。`ORDER_RECOVERY_SESSION_MINUTES` 可选，默认是 `30`。

## 验收

- 用一个确实存在的老订单邮箱申请验证码，确认邮件送达。
- 输入错误验证码应失败；输入正确验证码后应显示该邮箱的全部订单。
- 超过 20 笔订单时应能翻页。
- 删除过的旧商品或旧 SKU 仍应显示对应订单。
- 用不存在的邮箱申请时只显示通用提示，不能暴露“邮箱不存在”。
