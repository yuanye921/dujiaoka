# 旧版独立 Plus 商品补录

旧站中的“解锁码 Plus”曾经是独立商品。站点升级 SKU 后，这些历史订单被放进了 `DEFAULT` 规格，因此不会被只识别 `GAME_PLUS` 的普通补录命令扫描到。

系统不会只凭 `YYJP` 卡密格式自动判断商品，以免把普通解锁码误当成 Plus。请先执行只读候选扫描：

```powershell
php artisan licenses:backfill-plus --list-candidates
```

根据商品名称确认旧 Plus 的 `Goods ID` 后，先预演：

```powershell
php artisan licenses:backfill-plus --dry-run --legacy-goods-id=12
```

如果曾经有多个独立 Plus 商品，可以重复填写参数：

```powershell
php artisan licenses:backfill-plus --dry-run --legacy-goods-id=12 --legacy-goods-id=34
```

核对统计无误后，去掉 `--dry-run` 正式写入。也可以在 `.env` 中长期配置多个商品 ID：

```text
LICENSE_LEGACY_PLUS_GOODS_IDS=12,34
```

修改 `.env` 后需要执行 `php artisan config:clear`。补录可重复执行，已存在的授权不会重复创建。补录后的旧订单仍需先通过原订单邮箱验证码，才能绑定新浏览器。
