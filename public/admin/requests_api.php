<?php
/**
 * 【架构重构Phase2】Admin申请处理API
 * 提供实时库存查询等API功能
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_inventory':
        // 获取其他店铺的库存情况
        $releaseId = (int)($_GET['release_id'] ?? 0);
        $condition = $_GET['condition'] ?? '';
        $excludeShopId = (int)($_GET['exclude_shop'] ?? 0);

        if (!$releaseId || !$condition) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        try {
            // 【架构重构Phase2】使用DBProcedures替换直接SQL
            $inventory = DBProcedures::getOtherShopsInventory($pdo, $releaseId, $condition, $excludeShopId);

            echo json_encode(['success' => true, 'inventory' => $inventory]);
        } catch (PDOException $e) {
            error_log("requests_api.php get_inventory Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
?>
