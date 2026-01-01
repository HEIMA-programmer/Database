<?php
/**
 * 【安全修复】库存价格查询API
 * 仅Manager可访问，按需返回特定专辑的库存信息
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
$releaseId = (int)($_POST['release_id'] ?? 0);
$condition = $_POST['condition'] ?? '';

if (!$shopId) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

try {
    // 获取当前店铺的库存信息
    $shopInventory = DBProcedures::getShopInventoryGrouped($pdo, $shopId);

    // 如果指定了专辑ID，返回该专辑的所有condition
    if ($releaseId > 0) {
        $conditions = array_filter($shopInventory, fn($inv) => $inv['ReleaseID'] == $releaseId);
        $result = [];
        foreach ($conditions as $inv) {
            $result[] = [
                'condition' => $inv['ConditionGrade'],
                'quantity' => $inv['Quantity'],
                'price' => $inv['UnitPrice']
            ];
        }
        echo json_encode(['success' => true, 'conditions' => $result]);
        exit;
    }

    // 如果指定了专辑ID和condition，返回具体价格和数量
    if ($releaseId > 0 && !empty($condition)) {
        foreach ($shopInventory as $inv) {
            if ($inv['ReleaseID'] == $releaseId && $inv['ConditionGrade'] == $condition) {
                echo json_encode([
                    'success' => true,
                    'price' => $inv['UnitPrice'],
                    'quantity' => $inv['Quantity']
                ]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Inventory not found']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Missing parameters']);

} catch (Exception $e) {
    error_log("api_get_inventory_price error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
