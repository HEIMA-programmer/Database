<?php
/**
 * 订单详情查询API
 * 仅Staff可访问，按需返回特定订单的详情数据
 */
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/auth_guard.php';
require_once __DIR__ . '/../../../includes/db_procedures.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';

requireRole('Staff');

// 验证请求方法
if (!ApiResponse::requireMethod('POST')) {
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$shopId = $_SESSION['shop_id'] ?? 0;

if (!ApiResponse::requireParams(['order_id' => $orderId > 0 ? $orderId : null])) {
    exit;
}

ApiResponse::handle(function() use ($pdo, $orderId, $shopId) {
    // 验证订单属于当前店铺
    $orderInfo = DBProcedures::getOrderBasicInfo($pdo, $orderId);

    if (!$orderInfo || $orderInfo['ShopID'] != $shopId) {
        ApiResponse::error('Order not found', 404);
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

    ApiResponse::success([
        'info' => [
            'customer' => $orderInfo['CustomerName'] ?? 'Walk-in',
            'date' => $orderInfo['OrderDate'],
            'status' => $orderInfo['OrderStatus'],
            'total' => (float)$orderInfo['TotalAmount']
        ],
        'items' => $safeItems
    ]);
}, 'api/staff/order_detail');
