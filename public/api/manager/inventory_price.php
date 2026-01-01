<?php
/**
 * 库存价格查询API
 * 仅Manager可访问，按需返回特定专辑的库存信息
 */
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/auth_guard.php';
require_once __DIR__ . '/../../../includes/db_procedures.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';

requireRole('Manager');

// 验证请求方法
if (!ApiResponse::requireMethod('POST')) {
    exit;
}

$shopId = $_SESSION['user']['ShopID'] ?? $_SESSION['shop_id'] ?? null;
$releaseId = (int)($_POST['release_id'] ?? 0);
$condition = $_POST['condition'] ?? '';

if (!$shopId) {
    ApiResponse::error('Invalid session', 401);
}

ApiResponse::handle(function() use ($pdo, $shopId, $releaseId, $condition) {
    // 验证参数
    if ($releaseId <= 0) {
        ApiResponse::error('Missing release_id parameter', 400);
    }

    // 获取当前店铺的库存信息
    $shopInventory = DBProcedures::getShopInventoryGrouped($pdo, $shopId);

    // 如果同时指定了专辑ID和condition，返回具体价格和数量
    if (!empty($condition)) {
        foreach ($shopInventory as $inv) {
            if ($inv['ReleaseID'] == $releaseId && $inv['ConditionGrade'] == $condition) {
                ApiResponse::success([
                    'price' => $inv['UnitPrice'],
                    'quantity' => $inv['Quantity']
                ]);
            }
        }
        ApiResponse::error('Inventory not found', 404);
    }

    // 只指定了专辑ID，返回该专辑的所有condition
    $conditions = array_filter($shopInventory, fn($inv) => $inv['ReleaseID'] == $releaseId);
    $result = [];
    foreach ($conditions as $inv) {
        $result[] = [
            'condition' => $inv['ConditionGrade'],
            'quantity' => $inv['Quantity'],
            'price' => $inv['UnitPrice']
        ];
    }
    ApiResponse::success(['conditions' => $result]);
}, 'api/manager/inventory_price');
