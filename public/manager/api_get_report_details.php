<?php
/**
 * 【安全修复】报表详情API
 * 仅Manager可访问，按需返回特定类型和值的销售详情
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Manager');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$shopId = $_SESSION['user']['ShopID'] ?? $_SESSION['shop_id'] ?? null;
$type = $_POST['type'] ?? '';
$value = $_POST['value'] ?? '';

if (!$shopId) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

if (empty($type) || empty($value)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $data = [];

    if ($type === 'genre') {
        // 获取特定Genre的销售详情
        $data = DBProcedures::getSalesByGenreDetail($pdo, $shopId, $value);
    } elseif ($type === 'month') {
        // 获取特定月份的销售详情
        $data = DBProcedures::getMonthlySalesDetail($pdo, $shopId, $value);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    error_log("api_get_report_details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
