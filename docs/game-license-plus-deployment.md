# 解锁码 Plus 授权服务部署手册

这套服务把发卡网当作唯一的“钥匙总台”。Supabase 不参与新授权，也不需要创建用户账号。

## 上线前检查

1. 先备份线上数据库和四个项目当前版本。不要在没有备份时执行迁移。
2. 确认 Plus 商品的 SKU 名称为 `解锁码plus`。迁移会为它写入稳定标识 `GAME_PLUS`，后续代码不依赖数字 ID。
3. 在与线上配置一致的测试环境安装 PHP 依赖并执行测试：

   ```powershell
   composer install --no-interaction
   php artisan config:clear
   vendor\bin\phpunit tests\Feature\GameLicenseServiceTest.php
   ```

4. 为四个 pepper 分别生成不同的随机值，妥善保存在服务器环境变量中。可在装有 PHP 的服务器执行四次：

   ```powershell
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

   配置项为 `LICENSE_CODE_PEPPER`、`LICENSE_TOKEN_PEPPER`、`LICENSE_OTP_PEPPER`、`LICENSE_PRIVACY_PEPPER`。这些值上线后不能随意更换，否则已有卡密和设备令牌将无法识别。

## 推荐上线顺序

1. 先保持 `LICENSE_SERVICE_ENABLED=false`，部署发卡网代码并执行：

   ```powershell
   php artisan migrate --force
   php artisan config:clear
   ```

2. 确保邮件队列工作进程常驻。当前项目使用 Redis 时可先用测试进程验证：

   ```powershell
   php artisan queue:work redis --queue=default --tries=3 --timeout=60
   ```

   正式环境应交给 Supervisor、systemd 或服务器面板守护，不能只靠临时终端窗口。

3. 只读预演历史订单。它不会写数据库：

   ```powershell
   php artisan licenses:backfill-plus --dry-run
   ```

   逐项处理 `missing`、`duplicate`、`malformed`。有异常时命令会以退出码 2 结束，这是提醒人工核对，不是程序崩溃。

4. 异常处理完后正式回填。命令可重复执行，已存在记录会跳过：

   ```powershell
   php artisan licenses:backfill-plus
   ```

5. 用专门测试订单验收：自动发货、首次激活、错误游戏、第二浏览器邮箱找回、旧浏览器失效、验证码错误/过期/重复使用、撤销授权、30分钟人工找回、邮件队列停止和恢复、CORS 预检。

6. 测试通过后设置 `LICENSE_SERVICE_ENABLED=true`，再刷新配置：

   ```powershell
   php artisan config:clear
   php artisan config:cache
   ```

7. 依次发布“魔法世界”“主播模拟器”“后宫·风华录”。每发布一款先观察错误日志、验证码邮件和后台授权事件，再发布下一款。

8. 三款稳定后，停止旧 Supabase RPC 接受新激活，并停止向 Supabase 导入新的 Plus 码。不要删除旧数据，先保留一段观察期。

## 日常处理

- 后台“Game Licenses”可按订单号、订单邮箱、完整卡密、游戏和状态查询；页面只显示打码卡密与邮箱。
- “撤销授权”会立即废除设备令牌。旧浏览器下次联网复查时会立刻锁定。
- “允许一次人工找回”只开放30分钟，并在首次重新绑定后自动关闭。管理员身份会写入事件记录。
- 验证码10分钟有效、最多尝试5次；同一码60秒一次、每小时5次，同一 IP 每小时20次。
- 邮件队列任务只携带挑战编号，验证码在数据库中加密保存；邮件成功后密文会被清除。

## 故障和回滚

- 授权服务临时故障时，最近验证成功的浏览器最多继续24小时；明确的撤销或转移响应不会使用宽限。
- 紧急停用新接口时先设置 `LICENSE_SERVICE_ENABLED=false` 并刷新配置。已有浏览器进入24小时故障宽限，新激活会暂停。
- 不要直接回滚授权表迁移；那会删除授权、挑战和审计记录。代码回滚前先导出这三张表，并同步回滚三款游戏前端。
- 邮件发送失败时不要手工读取或发送数据库里的密文。先恢复队列/邮件配置，再让队列自动重试；确需人工处理时使用后台30分钟找回。
- 上线前保留的数据库备份是最终恢复点。恢复数据库后，三款游戏也必须回到与该数据库状态相配套的版本。

## Supabase 下线边界

新前端不再调用旧 Supabase 激活 RPC。确认三款生产版本都已更新、发卡网授权事件持续正常、历史迁移异常已清零后，才关闭旧 RPC 的新激活能力。Owlpost 使用的其他 Supabase 表和功能不在本次改造范围内。
