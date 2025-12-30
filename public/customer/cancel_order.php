<?php
/**
 * 取消订单 API
 * 【新增】用于处理客户手动取消和自动超时取消未支付订单
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

header('Content-Type: application/json');

$customerId = $_SESSION['user_id'];
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$autoCancel = isset($_POST['auto_cancel']) && $_POST['auto_cancel'] == '1';

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

try {
    // 【架构重构Phase3】使用 DBProcedures::validateOrderForCancel 替换直接SQL查询
    $order = DBProcedures::validateOrderForCancel($pdo, $orderId, $customerId);

    if (!$order || $order['OrderStatus'] !== 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Order not found or cannot be cancelled']);
        exit();
    }

    // 调用取消订单存储过程（会释放预留库存）
    $result = DBProcedures::cancelOrder($pdo, $orderId);

    if ($result) {
        $message = $autoCancel ? '订单因支付超时已自动取消' : '订单已成功取消';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
    }

} catch (Exception $e) {
    error_log("Cancel order error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error']);
}
