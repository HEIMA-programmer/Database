<?php
/**
 * 库存价格查询API
 * 仅Admin可访问，按需返回特定专辑的库存价格数据
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$releaseId = (int)($_POST['release_id'] ?? 0);

if ($releaseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid release ID']);
    exit;
}

try {
    // 通过存储过程/视图获取数据
    $stockData = DBProcedures::getStockPriceByCondition($pdo, $releaseId);

    if (empty($stockData)) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }

    // 只返回前端需要的字段，隐藏敏感信息
    $safeData = [];
    foreach ($stockData as $row) {
        $safeData[] = [
            'condition' => $row['ConditionGrade'],
            'shop' => $row['ShopName'],
            'qty' => (int)$row['Quantity'],
            'price' => (float)$row['MinPrice']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $safeData
    ]);

} catch (Exception $e) {
    error_log("api_get_stock_prices error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
