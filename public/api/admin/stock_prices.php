<?php
/**
 * 库存价格查询API
 * 仅Admin可访问，按需返回特定专辑的库存价格数据
 * 【修复】使用ApiResponse::requireRole()返回JSON错误而非重定向
 */
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/db_procedures.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';

// 【修复】使用API专用的角色验证，返回JSON错误而非重定向
if (!ApiResponse::requireRole('Admin')) {
    exit;
}

// 验证请求方法
if (!ApiResponse::requireMethod('POST')) {
    exit;
}

$releaseId = (int)($_POST['release_id'] ?? 0);

if (!ApiResponse::requireParams(['release_id' => $releaseId > 0 ? $releaseId : null])) {
    exit;
}

ApiResponse::handle(function() use ($pdo, $releaseId) {
    // 通过存储过程/视图获取数据
    $stockData = DBProcedures::getStockPriceByCondition($pdo, $releaseId);

    if (empty($stockData)) {
        ApiResponse::success([]);
        return;
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

    ApiResponse::success($safeData);
}, 'api/admin/stock_prices');
