<?php
/**
 * 报表详情API
 * 仅Manager可访问，按需返回特定类型和值的销售详情
 * 【修复】使用ApiResponse::requireRole()返回JSON错误而非重定向
 */
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/db_procedures.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';

// 【修复】使用API专用的角色验证，返回JSON错误而非重定向
if (!ApiResponse::requireRole('Manager')) {
    exit;
}

// 验证请求方法
if (!ApiResponse::requireMethod('POST')) {
    exit;
}

$shopId = $_SESSION['user']['ShopID'] ?? $_SESSION['shop_id'] ?? null;
$type = $_POST['type'] ?? '';
$value = $_POST['value'] ?? '';

if (!$shopId) {
    ApiResponse::error('Invalid session', 401);
}

if (!ApiResponse::requireParams(['type' => $type, 'value' => $value])) {
    exit;
}

ApiResponse::handle(function() use ($pdo, $shopId, $type, $value) {
    $data = [];

    if ($type === 'genre') {
        // 获取特定Genre的销售详情
        $data = DBProcedures::getSalesByGenreDetail($pdo, $shopId, $value);
    } elseif ($type === 'month') {
        // 获取特定月份的销售详情
        $data = DBProcedures::getMonthlySalesDetail($pdo, $shopId, $value);
    } else {
        ApiResponse::error('Invalid type');
    }

    ApiResponse::success($data);
}, 'api/manager/report_details');
