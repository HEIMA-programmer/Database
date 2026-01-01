<?php
/**
 * Admin申请处理API
 * 提供实时库存查询等API功能
 */
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/auth_guard.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/db_procedures.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';

requireRole('Admin');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_inventory':
        // 获取其他店铺的库存情况
        $releaseId = (int)($_GET['release_id'] ?? 0);
        $condition = $_GET['condition'] ?? '';
        $excludeShopId = (int)($_GET['exclude_shop'] ?? 0);

        if (!$releaseId || !$condition) {
            ApiResponse::error('Missing parameters', 400);
        }

        ApiResponse::handle(function() use ($pdo, $releaseId, $condition, $excludeShopId) {
            $inventory = DBProcedures::getOtherShopsInventory($pdo, $releaseId, $condition, $excludeShopId);
            ApiResponse::success(['inventory' => $inventory], 'Inventory loaded');
        }, 'api/admin/requests get_inventory');
        break;

    default:
        ApiResponse::error('Unknown action', 400);
        break;
}
