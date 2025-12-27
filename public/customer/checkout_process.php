<?php
/**
 * 结账流程处理
 * 【修复】确保Session正确启动和持久化
 */

// 【修复1】显式启动session，不依赖其他文件
session_start();

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

// 必须登录
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    error_log("Checkout access denied: Session not found or invalid role");
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

// 【修复】获取用户选择的订单类型
$orderType = $_POST['order_type'] ?? 'Online';
if (!in_array($orderType, ['Online', 'InStore'])) {
    $orderType = 'Online';
}

// 【修复】使用用户选择的店铺ID，如果没有选择则使用仓库
$selectedShopId = $_SESSION['selected_shop_id'] ?? null;
if (!$selectedShopId) {
    // 如果没有选择店铺，使用仓库
    $selectedShopId = getShopIdByType($pdo, 'Warehouse');
    if (!$selectedShopId) {
        flash("System Error: Warehouse configuration missing. Please contact support.", 'danger');
        header("Location: cart.php");
        exit();
    }
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

    // 【修复】3. 使用存储过程创建客户订单（使用用户选择的店铺）
    $orderId = DBProcedures::createCustomerOrder($pdo, $customerId, $selectedShopId, null, $orderType);

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
    error_log("Order #$orderId created successfully for customer #$customerId");

    // 7. 【修复2】清空购物车并立即持久化session
    $cartItemCount = count($_SESSION['cart']);
    unset($_SESSION['cart']);

    // 强制写入session，确保购物车清空持久化
    session_write_close();
    error_log("Shopping cart cleared: $cartItemCount items removed for customer #$customerId");

    // 重新启动session以便flash消息能写入
    session_start();

    // 8. 重定向到支付页面
    flash("Order #$orderId created! Please complete your payment.", 'info');
    header("Location: pay.php?order_id=$orderId");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 【修复3】详细记录错误信息
    error_log("Checkout failed for customer #$customerId: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    flash("Order failed: " . $e->getMessage(), 'danger');
    header("Location: cart.php");
    exit();
}
?>
