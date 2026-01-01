<?php
/**
 * 订单详情查询API
 * 仅Staff可访问，按需返回特定订单的详情数据
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Staff');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$shopId = $_SESSION['shop_id'] ?? 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // 验证订单属于当前店铺
    $orderInfo = DBProcedures::getOrderBasicInfo($pdo, $orderId);

    if (!$orderInfo || $orderInfo['ShopID'] != $shopId) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // 获取订单商品详情
    $items = DBProcedures::getOrderLineDetail($pdo, $orderId);

    // 只返回必要字段
    $safeItems = [];
    foreach ($items as $item) {
        $safeItems[] = [
            'title' => $item['Title'],
            'artist' => $item['ArtistName'],
            'condition' => $item['ConditionGrade'],
            'genre' => $item['Genre'] ?? '-',
            'year' => $item['ReleaseYear'] ?? '-',
            'price' => (float)$item['PriceAtSale']
        ];
    }

    echo json_encode([
        'success' => true,
        'info' => [
            'customer' => $orderInfo['CustomerName'] ?? 'Walk-in',
            'date' => $orderInfo['OrderDate'],
            'status' => $orderInfo['OrderStatus'],
            'total' => (float)$orderInfo['TotalAmount']
        ],
        'items' => $safeItems
    ]);

} catch (Exception $e) {
    error_log("api_get_order_detail error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
