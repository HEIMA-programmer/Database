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

    // 5. 计算积分（暂不完成订单，等待支付）
    $pointsEarned = floor($totalAmount);

    // 6. 使用存储过程完成订单
    $success = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);

    if (!$success) {
        throw new Exception("Failed to complete order.");
    }

    // 7. 使用改进后的函数处理积分和升级（内部使用存储过程）
    $result = addPointsAndCheckUpgrade($pdo, $customerId, $totalAmount);

    // 8. 提交事务
    $pdo->commit();

    // 构建成功消息
    $msg = "Order placed successfully! Order ID: #$orderId.";
    if ($result && $result['points_earned'] > 0) {
        $msg .= " You earned {$result['points_earned']} points!";
    }
    if ($result && $result['upgraded']) {
        $msg .= " 🌟 Congratulations! You've been upgraded to {$result['new_tier_name']} Tier!";
    }

    // 清空购物车
    unset($_SESSION['cart']);
    flash($msg, 'success');
    header("Location: orders.php");
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
