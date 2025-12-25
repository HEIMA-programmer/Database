<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

// 必须登录
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../login.php");
    exit();
}

// 购物车检查
if (empty($_SESSION['cart'])) {
    flash("Your cart is empty.", 'warning');
    header("Location: cart.php");
    exit();
}

$customerId = $_SESSION['user_id'];
$cart = $_SESSION['cart'];

// 动态获取线上仓库ID
$warehouseId = getShopIdByType($pdo, 'Warehouse');
if (!$warehouseId) {
    flash("System Error: Warehouse configuration missing. Please contact support.", 'danger');
    header("Location: cart.php");
    exit();
}

try {
    // 1. 开启事务
    $pdo->beginTransaction();

    // 2. 从视图获取购物车商品信息并计算总价
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT StockItemID, UnitPrice, Title FROM vw_customer_catalog WHERE StockItemID IN ($placeholders)");
    $stmt->execute($cart);
    $cartItems = $stmt->fetchAll();

    if (empty($cartItems)) {
        throw new Exception("Cart items invalid.");
    }

    $totalAmount = 0;
    foreach ($cartItems as $item) {
        $totalAmount += $item['UnitPrice'];
    }

    // 3. 使用存储过程创建客户订单
    $orderId = DBProcedures::createCustomerOrder($pdo, $customerId, $warehouseId, null, 'Online');

    if (!$orderId) {
        throw new Exception("Failed to create order.");
    }

    // 4. 使用存储过程添加订单商品并预留库存
    foreach ($cartItems as $item) {
        $success = DBProcedures::addOrderItem($pdo, $orderId, $item['StockItemID'], $item['UnitPrice']);

        if (!$success) {
            throw new Exception("Item '{$item['Title']}' is no longer available.");
        }
    }

    // 5. 【修复】在线订单保持 Pending 状态，等待用户支付
    // 不在此处调用 completeOrder，让用户去支付页面完成
    // 积分和会员升级由 pay.php 中的 completeOrder 触发器自动处理

    // 6. 提交事务
    $pdo->commit();

    // 7. 清空购物车
    unset($_SESSION['cart']);

    // 8. 重定向到支付页面
    flash("Order #$orderId created! Please complete your payment.", 'info');
    header("Location: pay.php?order_id=$orderId");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash("Order failed: " . $e->getMessage(), 'danger');
    header("Location: cart.php");
    exit();
}
?>
