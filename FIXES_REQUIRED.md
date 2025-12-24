# 代码修复清单

## 🔴 紧急修复（必须立即执行）

### 1. 执行SQL修复脚本

按顺序执行以下SQL文件：

```bash
mysql -u root -p your_database < sql/fixes/01_fix_duplicate_points.sql
mysql -u root -p your_database < sql/fixes/02_fix_order_completion_consistency.sql
mysql -u root -p your_database < sql/fixes/03_add_polymorphic_fk_validation.sql
mysql -u root -p your_database < sql/fixes/04_add_timeout_mechanism.sql
```

### 2. 修改 PHP 文件

#### 📄 public/customer/pay.php

**位置:** 第 28-47 行

**原代码:**
```php
// 处理支付请求 (模拟支付)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';

    if (!in_array($paymentMethod, ['alipay', 'wechat', 'card'])) {
        flash("Invalid payment method.", 'danger');
    } else {
        try {
            // 更新订单状态为已支付
            $updateSql = "UPDATE CustomerOrder SET OrderStatus = 'Paid' WHERE OrderID = ?";
            $pdo->prepare($updateSql)->execute([$orderId]);

            flash("Payment successful! Your order is now being processed.", 'success');
            header("Location: order_detail.php?id=$orderId");
            exit();
        } catch (Exception $e) {
            flash("Payment failed: " . $e->getMessage(), 'danger');
        }
    }
}
```

**修改为:**
```php
// 处理支付请求 (模拟支付)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';

    if (!in_array($paymentMethod, ['alipay', 'wechat', 'card'])) {
        flash("Invalid payment method.", 'danger');
    } else {
        try {
            require_once __DIR__ . '/../../includes/db_procedures.php';

            $pdo->beginTransaction();

            // 验证库存仍然Reserved
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM OrderLine ol
                JOIN StockItem s ON ol.StockItemID = s.StockItemID
                WHERE ol.OrderID = ? AND s.Status = 'Reserved'
            ");
            $stmt->execute([$orderId]);
            $reservedCount = $stmt->fetchColumn();

            if ($reservedCount == 0) {
                throw new Exception("订单商品已失效，请重新下单");
            }

            // 使用存储过程完成订单（包含积分和库存状态更新）
            $pointsEarned = floor($order['TotalAmount']);
            $success = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);

            if (!$success) {
                throw new Exception("订单完成失败");
            }

            $pdo->commit();

            flash("Payment successful! Your order is now being processed.", 'success');
            header("Location: order_detail.php?id=$orderId");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash("Payment failed: " . $e->getMessage(), 'danger');
        }
    }
}
```

---

#### 📄 public/staff/pickup.php

**位置:** 第 9-18 行

**原代码:**
```php
// 处理取货操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = $_POST['order_id'];
    // 更新状态为 Completed
    $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Completed' WHERE OrderID = ? AND FulfilledByShopID = ?");
    $stmt->execute([$orderId, $shopId]);
    flash("Order #$orderId marked as collected.", 'success');
    header("Location: pickup.php");
    exit();
}
```

**修改为:**
```php
// 处理取货操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    require_once __DIR__ . '/../../includes/db_procedures.php';

    $orderId = $_POST['order_id'];

    try {
        $pdo->beginTransaction();

        // 获取订单信息
        $stmt = $pdo->prepare("
            SELECT TotalAmount FROM CustomerOrder
            WHERE OrderID = ? AND FulfilledByShopID = ? AND OrderStatus = 'Paid'
        ");
        $stmt->execute([$orderId, $shopId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("订单不存在或状态不正确");
        }

        // 使用存储过程完成订单
        $pointsEarned = floor($order['TotalAmount']);
        $success = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);

        if (!$success) {
            throw new Exception("订单完成失败");
        }

        $pdo->commit();
        flash("Order #$orderId marked as collected.", 'success');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("Error: " . $e->getMessage(), 'danger');
    }

    header("Location: pickup.php");
    exit();
}
```

---

#### 📄 public/staff/fulfillment.php

**位置:** 第 7-29 行

**原代码:**
```php
// 处理发货动作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'ship') {
            // 标记为已发货
            $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Shipped' WHERE OrderID = ? AND OrderType = 'Online' AND OrderStatus = 'Paid'");
            $stmt->execute([$orderId]);
            flash("Order #$orderId has been shipped!", 'success');
        } elseif ($action === 'complete') {
            // 标记为完成（已送达）
            $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Completed' WHERE OrderID = ? AND OrderType = 'Online' AND OrderStatus = 'Shipped'");
            $stmt->execute([$orderId]);
            flash("Order #$orderId delivery confirmed!", 'success');
        }
    } catch (PDOException $e) {
        flash("Error updating order: " . $e->getMessage(), 'danger');
    }
    header("Location: fulfillment.php");
    exit();
}
```

**修改为:**
```php
// 处理发货动作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../includes/db_procedures.php';

    $orderId = $_POST['order_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'ship') {
            // 标记为已发货（这个可以保留直接UPDATE，因为只是物流状态）
            $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Shipped' WHERE OrderID = ? AND OrderType = 'Online' AND OrderStatus = 'Paid'");
            $stmt->execute([$orderId]);
            flash("Order #$orderId has been shipped!", 'success');

        } elseif ($action === 'complete') {
            // 使用存储过程完成订单
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT TotalAmount FROM CustomerOrder
                WHERE OrderID = ? AND OrderType = 'Online' AND OrderStatus = 'Shipped'
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception("订单不存在或状态不正确");
            }

            $pointsEarned = floor($order['TotalAmount']);
            $success = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);

            if (!$success) {
                throw new Exception("订单完成失败");
            }

            $pdo->commit();
            flash("Order #$orderId delivery confirmed!", 'success');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("Error updating order: " . $e->getMessage(), 'danger');
    }
    header("Location: fulfillment.php");
    exit();
}
```

---

#### 📄 public/staff/inventory.php

**位置:** 第 9-17 行

**原代码:**
```php
// 查询本店库存
$sql = "SELECT s.*, r.Title, r.ArtistName
        FROM StockItem s
        JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
        WHERE s.ShopID = :shop AND s.Status = 'Available'
        ORDER BY s.AcquiredDate DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':shop' => $shopId]);
$inventory = $stmt->fetchAll();
```

**修改为:**
```php
// 使用视图查询本店库存
$sql = "SELECT * FROM vw_inventory_summary
        WHERE ShopID = :shop
        ORDER BY AvailableQuantity ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':shop' => $shopId]);
$inventory = $stmt->fetchAll();
```

**注意:** 由于视图返回的是汇总数据（按Release分组），需要修改显示逻辑。如果需要显示单个StockItem，可以保留原SQL，或创建新视图。

---

#### 📄 public/manager/dashboard.php

**位置:** 第 14-30 行

**原代码:**
```php
// --- Advanced SQL 2: Dead Stock Alert ---
// 找出进货超过 60 天但从未卖出过的商品
$deadStockSql = "
    SELECT
        r.Title,
        r.ArtistName,
        s.BatchNo,
        s.AcquiredDate,
        DATEDIFF(NOW(), s.AcquiredDate) as DaysInStock
    FROM StockItem s
    JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
    WHERE s.Status = 'Available'
    AND s.AcquiredDate < DATE_SUB(NOW(), INTERVAL 60 DAY)
    ORDER BY s.AcquiredDate ASC
    LIMIT 10
";
$deadStock = $pdo->query($deadStockSql)->fetchAll();
```

**修改为:**
```php
// 使用视图查询低库存商品（可以增强为死库存）
$deadStockSql = "
    SELECT
        Title,
        ArtistName,
        ShopName,
        AvailableQuantity,
        '60+' as DaysInStock
    FROM vw_low_stock_alert
    LIMIT 10
";
$deadStock = $pdo->query($deadStockSql)->fetchAll();
```

或者创建新的死库存视图：
```sql
CREATE OR REPLACE VIEW vw_dead_stock_alert AS
SELECT
    r.Title,
    r.ArtistName,
    s.BatchNo,
    s.AcquiredDate,
    DATEDIFF(NOW(), s.AcquiredDate) as DaysInStock,
    sh.Name as ShopName
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND s.AcquiredDate < DATE_SUB(NOW(), INTERVAL 60 DAY)
ORDER BY s.AcquiredDate ASC;
```

---

#### 📄 public/manager/transfer.php

**位置:** 第 64-77 行

**原代码:**
```php
// 获取当前店铺待接收的转运
$pendingTransfers = $pdo->prepare("
    SELECT it.*, s.Title, s.StockItemID as SID, si.BatchNo, si.ConditionGrade,
           fromShop.Name as FromShopName, toShop.Name as ToShopName
    FROM InventoryTransfer it
    JOIN StockItem si ON it.StockItemID = si.StockItemID
    JOIN ReleaseAlbum s ON si.ReleaseID = s.ReleaseID
    JOIN Shop fromShop ON it.FromShopID = fromShop.ShopID
    JOIN Shop toShop ON it.ToShopID = toShop.ShopID
    WHERE it.Status = 'InTransit'
    ORDER BY it.TransferDate DESC
");
$pendingTransfers->execute();
$pending = $pendingTransfers->fetchAll();
```

**修改为:**
```php
// 使用视图查询待接收的转运
$pending = $pdo->query("SELECT * FROM vw_manager_pending_transfers ORDER BY TransferDate DESC")->fetchAll();
```

---

#### 📄 includes/functions.php

**位置:** 第 119-125 行

**原代码:**
```php
function addPointsAndCheckUpgrade($pdo, $customerId, $amountSpent) {
    $pointsEarned = floor($amountSpent);

    $before = $pdo->query("SELECT TierID FROM vw_customer_profile_info WHERE CustomerID = $customerId")->fetch();

    $stmt = $pdo->prepare("UPDATE Customer SET Points = Points + ? WHERE CustomerID = ?");
    $stmt->execute([$pointsEarned, $customerId]);

    $stmt = $pdo->prepare("CALL sp_update_customer_tier(?)");
    $stmt->execute([$customerId]);

    // ...
}
```

**修改为:**
```php
function addPointsAndCheckUpgrade($pdo, $customerId, $amountSpent) {
    $pointsEarned = floor($amountSpent);

    $before = $pdo->query("SELECT TierID FROM vw_customer_profile_info WHERE CustomerID = $customerId")->fetch();

    // 删除手动更新积分的代码，触发器会自动处理
    // 注意：这个函数现在只用于检查升级情况，实际积分更新由触发器完成

    $after = $pdo->query("SELECT TierID, TierName FROM vw_customer_profile_info WHERE CustomerID = $customerId")->fetch();

    return [
        'points_earned' => $pointsEarned,
        'upgraded' => $before['TierID'] != $after['TierID'],
        'new_tier_name' => $after['TierName'] ?? 'Unknown'
    ];
}
```

**或者完全删除这个函数**，因为触发器已经处理了所有逻辑。

---

## 🟡 可选优化

### 创建额外的视图

```sql
-- 回购订单列表视图（已定义但未使用）
-- 在 admin 界面添加回购订单管理页面使用 vw_buyback_orders

-- 店铺业绩视图
-- 在 manager/dashboard.php 中使用 vw_manager_shop_performance
```

### 启用事件调度器

```bash
mysql -u root -p -e "SET GLOBAL event_scheduler = ON;"
```

---

## ✅ 验证修复

修复完成后，执行以下测试：

1. **积分测试**
   - 完成一笔订单
   - 检查客户积分是否只增加一次（而不是翻倍）
   - 检查生日月份是否有额外20%积分

2. **订单完成测试**
   - 在线支付后，检查库存状态是否变为 'Sold'
   - 店内取货后，检查客户积分是否增加
   - 物流确认后，检查会员等级是否自动升级

3. **多态外键测试**
   - 尝试插入无效的 SourceOrderID，应该被拒绝
   - 检查现有的 StockItem 是否都有有效的来源订单

4. **超时释放测试**
   - 创建订单但不支付
   - 等待30分钟后检查库存是否自动释放
   - 检查订单是否自动取消

---

## 📊 执行优先级

**第一优先级（今天完成）：**
- ✅ 01_fix_duplicate_points.sql
- ✅ public/customer/pay.php
- ✅ public/staff/pickup.php
- ✅ public/staff/fulfillment.php

**第二优先级（本周完成）：**
- ✅ 02_fix_order_completion_consistency.sql
- ✅ 03_add_polymorphic_fk_validation.sql
- ✅ includes/functions.php

**第三优先级（下周完成）：**
- ✅ 04_add_timeout_mechanism.sql
- ✅ 视图替换优化

---

## 🆘 如有问题

如果修复过程中遇到问题：
1. 立即回滚数据库 `ROLLBACK;`
2. 检查错误日志
3. 逐个执行修复而非批量执行
4. 在测试环境先验证
